#!/usr/bin/env bash
#
# Installs the project with Docker, from scratch or again: everything the README
# describes in "Instalación desde cero", step by step.
#
#   ./setup.sh                 install (or update) and seed what is missing
#   ./setup.sh --fresh         also empty the database first: migrate:fresh --seed
#   ./setup.sh --tests         run the test suites at the end
#   ./setup.sh --no-build      do not rebuild the Docker image
#   ./setup.sh --no-seed       migrate without seeding
#   ./setup.sh --yes           do not ask for confirmation (for --fresh)
#
# It can run as many times as needed: it only changes what is not set up yet.

set -euo pipefail

cd "$(dirname "$0")"

FRESH=0
RUN_TESTS=0
BUILD=1
SEED=1
ASSUME_YES=0

for arg in "$@"; do
    case "$arg" in
        --fresh) FRESH=1 ;;
        --tests) RUN_TESTS=1 ;;
        --no-build) BUILD=0 ;;
        --no-seed) SEED=0 ;;
        --yes | -y) ASSUME_YES=1 ;;
        -h | --help)
            sed -n '2,13p' "$0" | sed 's/^# \{0,1\}//'
            exit 0
            ;;
        *)
            echo "Unknown option: $arg (see ./setup.sh --help)" >&2
            exit 1
            ;;
    esac
done

if [ -t 1 ]; then
    BOLD=$'\033[1m'; GREEN=$'\033[32m'; YELLOW=$'\033[33m'; RED=$'\033[31m'; RESET=$'\033[0m'
else
    BOLD=''; GREEN=''; YELLOW=''; RED=''; RESET=''
fi

step=0
say() { step=$((step + 1)); printf '\n%s[%s] %s%s\n' "$BOLD" "$step" "$1" "$RESET"; }
ok() { printf '    %s✓%s %s\n' "$GREEN" "$RESET" "$1"; }
warn() { printf '    %s!%s %s\n' "$YELLOW" "$RESET" "$1"; }
die() { printf '%s✗ %s%s\n' "$RED" "$1" "$RESET" >&2; exit 1; }

compose() { docker compose --profile local "$@"; }
# Runs a command inside the backend container (no TTY: it works from scripts and CI too).
app() { compose exec -T backend "$@"; }

# Sets KEY=value in a dotenv file: replaces every line of that key, or appends it.
set_env() {
    local file=$1 key=$2 value=$3 tmp
    if grep -qE "^${key}=" "$file"; then
        tmp=$(mktemp)
        awk -v k="$key" -v v="$value" 'BEGIN { FS = OFS = "=" } $1 == k { print k "=" v; next } { print }' "$file" >"$tmp"
        cat "$tmp" >"$file"
        rm -f "$tmp"
    else
        printf '%s=%s\n' "$key" "$value" >>"$file"
    fi
}

# ---------------------------------------------------------------------------
say "Checking Docker"
command -v docker >/dev/null 2>&1 || die "Docker is not installed."
docker compose version >/dev/null 2>&1 || die "Docker Compose v2 is needed (docker compose version)."
docker info >/dev/null 2>&1 || die "Docker is not running: start it and try again."
[ -f docker-compose.yml ] || die "docker-compose.yml not found: run this from the project root."
ok "$(docker compose version --short 2>/dev/null || echo 'docker compose') is ready"

# ---------------------------------------------------------------------------
say "Preparing .env and .env.testing"
if [ ! -f .env ]; then
    cp .env.example .env
    ok ".env created from .env.example"
else
    ok ".env already exists (its Docker settings are checked below)"
fi

# .env.example already has what the Docker services expect. An older .env is not rewritten:
# it only warns when it points somewhere the containers do not serve.
env_differs=0
check_env() {
    local key=$1 expected=$2 actual
    actual=$(grep -E "^${key}=" .env | tail -n 1 | cut -d= -f2-)
    if [ "$actual" != "$expected" ]; then
        env_differs=1
        warn "${key} is '${actual}' in .env; the Docker services expect '${expected}'"
    fi
}
check_env DB_CONNECTION pgsql
check_env DB_HOST db
check_env DB_DATABASE app
check_env REDIS_HOST cache
check_env MAIL_HOST smtpServer
[ "$env_differs" -eq 0 ] && ok ".env points to the Docker services (db, cache, smtpServer)" || warn "fix those values in .env (or copy .env.example again) if the app cannot reach the services"

if [ ! -f .env.testing ]; then
    cp .env.testing.example .env.testing
    ok ".env.testing created from .env.testing.example"
else
    set_env .env.testing DB_DATABASE app
    ok ".env.testing already exists"
fi

# ---------------------------------------------------------------------------
if [ "$BUILD" -eq 1 ]; then
    say "Building the Docker image (the first time takes a few minutes)"
    compose build
    ok "image built"
else
    say "Building the Docker image"
    warn "skipped (--no-build)"
fi

# ---------------------------------------------------------------------------
say "Starting the services"
compose up -d
ok "services started"

printf '    waiting for PostgreSQL'
for _ in $(seq 1 60); do
    if compose exec -T db pg_isready -U user_db -d app >/dev/null 2>&1; then
        printf '\n'
        ok "PostgreSQL is ready"
        break
    fi
    printf '.'
    sleep 1
done
compose exec -T db pg_isready -U user_db -d app >/dev/null 2>&1 || die "PostgreSQL did not start in time: check 'docker compose logs db'."

# The schemas the app and the tests use are created when the volume is created; make sure of them for older volumes.
compose exec -T db psql -U user_db -d app -v ON_ERROR_STOP=1 -q -f /docker-entrypoint-initdb.d/schema.sql >/dev/null 2>&1 \
    && ok "schemas public and testing, and the unaccent extension, are there" \
    || warn "could not run the database init script (the migrations create what they need)"

# ---------------------------------------------------------------------------
say "Installing PHP dependencies (composer install)"
app composer install --no-interaction --prefer-dist
ok "vendor/ installed; packages/ linked as local packages"

say "Installing JavaScript dependencies (pnpm install)"
app pnpm install --frozen-lockfile || app pnpm install
ok "node_modules/ installed"

# ---------------------------------------------------------------------------
say "Application key"
if grep -qE '^APP_KEY=.+' .env; then
    ok "APP_KEY already set"
else
    app php artisan key:generate --force
    ok "APP_KEY generated"
fi
app php artisan config:clear >/dev/null
compose restart queues frontend >/dev/null 2>&1 || true
ok "queues and frontend restarted with the dependencies in place"

# ---------------------------------------------------------------------------
say "Migrations and seeders"
if [ "$FRESH" -eq 1 ]; then
    if [ "$ASSUME_YES" -ne 1 ]; then
        printf '    %s--fresh drops EVERY table of the database "app".%s Continue? [y/N] ' "$YELLOW" "$RESET"
        read -r answer
        case "$answer" in
            y | Y | yes | YES) ;;
            *) die "cancelled: nothing was dropped." ;;
        esac
    fi
    if [ "$SEED" -eq 1 ]; then
        app php artisan migrate:fresh --seed --force
        ok "database recreated and seeded"
    else
        app php artisan migrate:fresh --force
        ok "database recreated (not seeded)"
    fi
elif [ "$SEED" -eq 1 ]; then
    app php artisan migrate --seed --force
    ok "migrated and seeded (each seeder skips what already exists)"
else
    app php artisan migrate --force
    ok "migrated (not seeded)"
fi

# ---------------------------------------------------------------------------
say "Generating the front end's typed routes and permissions"
app php artisan wayfinder:generate
app php artisan permissions:export-ts
ok "resources/js/routes and resources/js/generated/permissions.ts"

# ---------------------------------------------------------------------------
if [ "$RUN_TESTS" -eq 1 ]; then
    say "Running the tests"
    app php artisan test
    app pnpm test
    app pnpm typecheck
    app bash -c 'cd packages/academic-mobility && composer install --no-interaction -q && vendor/bin/phpunit'
    app bash -c 'cd packages/api-response && composer install --no-interaction -q && vendor/bin/phpunit'
    ok "all the suites passed"
fi

# ---------------------------------------------------------------------------
printf '\n%s%sReady.%s\n\n' "$BOLD" "$GREEN" "$RESET"
cat <<'EOF'
  Application      http://localhost
  Vite             http://localhost:5173
  Mailpit (mail)   http://localhost:8025
  Horizon          http://localhost/horizon

  Sign in with     test@example.com / password   (administrator)

  Enter the container     docker compose exec backend bash
  Re-seed from scratch    ./setup.sh --fresh
EOF
