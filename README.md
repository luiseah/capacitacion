# Capacitación

Aplicación Laravel 13 + Inertia/React con un módulo de **movilidad académica** (`packages/academic-mobility`) y una respuesta de API compartida (`packages/api-response`). Todo corre en Docker: no necesitas PHP, Composer ni Node en tu máquina.

## Qué incluye

| Servicio | Para qué | Perfil | Puerto |
|---|---|---|---|
| `backend` | PHP 8.5 FPM, Composer, Node 22 y pnpm | siempre | — |
| `webserver` | Nginx: sirve la aplicación | siempre | **80** |
| `cache` | Redis (lo usa Horizon) | siempre | — |
| `queues` | Horizon (colas) | siempre | — |
| `cron` | Ejecuta el planificador cada minuto | siempre | — |
| `db` | PostgreSQL 16 | `local` | 5432 |
| `smtpServer` | Mailpit: recibe los correos de desarrollo | `local` | 1025 (SMTP) y **8025** (web) |
| `frontend` | Vite (`pnpm dev`) con recarga en caliente | `local` | **5173** |

> Los servicios con perfil `local` solo arrancan con `--profile local`. Sin él no hay base de datos.

## Requisitos

- Docker y Docker Compose v2 (`docker compose version`).
- Los puertos 80, 5173, 5432 y 8025 libres.
- Git.

## Instalación desde cero

> **Atajo:** `./setup.sh` hace todos los pasos de esta sección (entorno, imagen, servicios, dependencias, clave, migraciones, seeders y rutas tipadas) y se puede repetir sin riesgo. Opciones: `--fresh` (vacía la base y vuelve a sembrar), `--tests`, `--no-build`, `--no-seed` y `--yes`; `./setup.sh --help` las lista. Abajo está el detalle paso a paso.

### 1. Clonar y preparar el entorno

```bash
git clone <url-del-repositorio> capacitacion
cd capacitacion
cp .env.example .env
```

El `.env.example` ya viene configurado para los servicios de Docker, no necesitas editar nada: PostgreSQL (`DB_HOST=db`, base `app`), Redis (`REDIS_HOST=cache`; el host es el nombre del servicio, no `127.0.0.1`) y Mailpit (`MAIL_HOST=smtpServer`). `REDIS_PASSWORD` también la usa `docker-compose.yml` para levantar Redis, así que debe estar definida.

### 2. Construir la imagen

```bash
docker compose --profile local build
```

La primera vez tarda unos minutos.

### 3. Levantar los servicios

```bash
docker compose --profile local up -d
docker compose ps
```

Si `queues` o `frontend` se reinician en bucle es normal en este punto: todavía no están instaladas las dependencias. Se arreglan en el paso 5.

### 4. Entrar al contenedor

Todos los comandos de Laravel, Composer y pnpm se ejecutan **dentro** del contenedor `backend` (usuario `user_server`, directorio `/var/www`):

```bash
docker compose exec backend bash
```

Para salir: `exit`. También puedes lanzar un solo comando sin entrar:

```bash
docker compose exec backend php artisan about
```

### 5. Instalar dependencias (dentro del contenedor)

```bash
# PHP: instala vendor/ y enlaza los paquetes locales de packages/ (módulo de movilidad y api-response)
composer install

# JavaScript
pnpm install

# Clave de la aplicación
php artisan key:generate
```

> `packages/academic-mobility` y `packages/api-response` son paquetes de Composer locales (`"repositories": [{ "type": "path", "url": "packages/*" }]`). `composer install` los enlaza dentro de `vendor/module/`: no hay que publicarlos ni instalarlos aparte.

Reinicia lo que dependía de ellas:

```bash
exit
docker compose restart queues frontend
```

### 6. Migraciones y seeders (dentro del contenedor)

```bash
docker compose exec backend bash

php artisan migrate --seed
```

Con una base nueva eso deja **toda la aplicación llena**. Para empezar de cero en cualquier momento:

```bash
php artisan migrate:fresh --seed
```

> `migrate:fresh` **borra todas las tablas** de la base. No lo uses con datos que quieras conservar.

#### Qué crea `--seed`

| Datos | Seeder | Cantidad |
|---|---|---|
| Roles y permisos | `RolesAndPermissionsSeeder` | 2 roles, 24 permisos |
| Tipos de solicitud | `RequestTypeSeeder` | 4 |
| Instituciones (la local es la Universidad Santiago de Cali) | `InstitutionSeeder` | 40 |
| Requisitos de las movilidades | `RequirementSeeder` | 23 |
| Convenios | `AgreementSeeder` | 78 |
| Cuenta de prueba | `DatabaseSeeder` | `test@example.com` / `password` (administrador) |
| Usuarios al azar | `UserSeeder` | 50 |
| Estudiantes, profesores, administrativos | `StudentSeeder`, `ProfessorSeeder`, `AdministrativeStaffSeeder` | 35 / 12 / 8 |
| Visitantes de otras instituciones | `ExternalParticipantSeeder` | 60 |
| Solicitudes de servicio | `ServiceRequestSeeder` | 24 |
| Movilidades | `MobilitySeeder` | 24, en todos los estados |

Los datos de ejemplo (todo desde `UserSeeder` hacia abajo) **no se cargan si `APP_ENV=production`**. Todos los usuarios de ejemplo tienen la contraseña `password`.

Cada seeder se salta lo que ya existe, así que puedes volver a ejecutar `php artisan db:seed` sin duplicar nada. También puedes correr uno solo; cada uno se encarga de lo que necesita:

```bash
php artisan db:seed --class=MobilitySeeder
php artisan db:seed --class=DemoSeeder        # solo los datos de ejemplo
```

### 7. Publicar (dentro del contenedor)

En este repositorio **ya están publicados** los archivos de configuración y las migraciones de los paquetes (`config/permission.php`, `config/horizon.php`, `config/sanctum.php`, `config/fortify.php`, `config/audit.php`, `config/academic-mobility.php`, y sus migraciones). Tras clonar no hay que publicar nada más, salvo los recursos de Laravel, que Composer publica solo en cada `composer update`:

```bash
php artisan vendor:publish --tag=laravel-assets --force
```

Dos archivos se **generan** y no se versionan; créalos una vez:

```bash
# Rutas tipadas para React (resources/js/routes, actions y wayfinder)
php artisan wayfinder:generate

# Permisos en TypeScript (resources/js/generated/permissions.ts); este sí está versionado
php artisan permissions:export-ts
```

Si usas el módulo de movilidad en **otro** proyecto, estos son sus grupos de publicación:

```bash
php artisan vendor:publish --tag=academic-mobility-config       # config/academic-mobility.php
php artisan vendor:publish --tag=academic-mobility-migrations   # copia las migraciones
php artisan vendor:publish --tag=academic-mobility-react        # páginas React a resources/js/vendor/
```

> **En este proyecto no publiques `academic-mobility-migrations`:** el módulo ya carga sus migraciones desde `packages/` (`load_migrations => true`) y publicarlas las duplicaría. Tampoco publiques `academic-mobility-react`: la aplicación lee esas páginas directamente de `packages/` (alias `@academic`).

### 8. Probar que funciona

| Qué | Dónde |
|---|---|
| Aplicación | <http://localhost> |
| Vite (recarga en caliente) | <http://localhost:5173> |
| Correos de desarrollo | <http://localhost:8025> |
| Horizon (colas) | <http://localhost/horizon> |

Entra con `test@example.com` / `password`. En el menú aparecen Usuarios, Movilidad académica, Instituciones, Convenios y Requisitos.

## Idioma

La interfaz está en inglés y español. El selector **EN / ES** está en la barra superior (y en la pantalla de acceso). La elección se guarda en una cookie.

## Pruebas

Las pruebas corren en PostgreSQL, en el esquema `testing` (no tocan las tablas del esquema `public`):

```bash
cp .env.testing.example .env.testing

# el esquema `testing` lo crea el script de arranque de la base de datos al crear el volumen
# (.docker/db/pgsql/docker-entrypoint-initdb.d/schema.sql); si tu volumen es anterior:
docker compose exec db psql -U user_db -d app -f /docker-entrypoint-initdb.d/schema.sql

docker compose exec backend php artisan test

# frontend
docker compose exec backend pnpm test
docker compose exec backend pnpm typecheck

# los paquetes tienen sus propias pruebas
docker compose exec backend bash -c "cd packages/academic-mobility && composer install && vendor/bin/phpunit"
docker compose exec backend bash -c "cd packages/api-response && composer install && vendor/bin/phpunit"
```

## Laravel Boost (MCP para Claude Code)

[Laravel Boost](https://laravel.com/docs/boost) le da a Claude Code acceso al proyecto: documentación de las versiones instaladas, esquema y consultas de la base de datos, logs, último error y la URL absoluta de la aplicación. Está instalado como dependencia de desarrollo (`laravel/boost`) y su configuración vive en la raíz del repositorio, así que **al clonar no hay nada que instalar aparte**:

| Archivo | Para qué |
|---|---|
| `.mcp.json` | Registra el servidor MCP `laravel-boost` para Claude Code |
| `CLAUDE.md` | Guías de Boost para este proyecto (versiones, convenciones, reglas) |
| `.claude/skills/` | Habilidades de Claude para Laravel, Inertia, Tailwind, Horizon, Fortify y Wayfinder |
| `boost.json` | Qué instaló Boost (guías, habilidades y MCP) |

Como PHP solo existe dentro de Docker, `.mcp.json` ejecuta el servidor **dentro del contenedor `backend`**:

```json
{
    "mcpServers": {
        "laravel-boost": {
            "command": "docker",
            "args": ["compose", "exec", "-T", "backend", "php", "artisan", "boost:mcp"]
        }
    }
}
```

### Usarlo

1. Con los contenedores arriba (`docker compose --profile local up -d`), instala las dependencias si no lo hiciste: `docker compose exec backend composer install`.
2. Abre Claude Code **en la raíz del proyecto**:

   ```bash
   cd capacitacion
   claude
   ```

3. La primera vez Claude pide aprobar el servidor MCP del proyecto: acéptalo. Después comprueba que está conectado con `/mcp`; debe aparecer `laravel-boost` con sus herramientas (`search-docs`, `database-schema`, `database-query`, `last-error`, `read-log-entries`, `browser-logs`, `get-absolute-url`, `database-connections`, `record-rule`).

> El servidor solo responde si el contenedor `backend` está en marcha. Si Claude dice que `laravel-boost` falló, ejecuta `docker compose ps` y levanta los servicios.

### Instalarlo desde cero en otro proyecto (o rehacerlo)

```bash
docker compose exec backend composer require laravel/boost --dev
docker compose exec backend php artisan boost:install --mcp --guidelines --skills
```

`boost:install` escribe `.mcp.json` con `"command": "php"`, que aquí no existe en tu máquina: cámbialo por el `docker compose exec …` de arriba. Cuando actualices paquetes, refresca las guías y habilidades con:

```bash
docker compose exec backend php artisan boost:update
```

Boost no se instala en producción (`composer install --no-dev`).

## Comandos habituales

```bash
docker compose --profile local up -d      # levantar
docker compose --profile local stop       # detener sin borrar datos
docker compose --profile local down       # quitar contenedores (los datos de la base se conservan en el volumen)
docker compose logs -f backend            # ver logs de PHP
docker compose exec backend bash          # entrar al contenedor

docker compose exec backend php artisan migrate
docker compose exec backend php artisan tinker
docker compose exec backend pnpm build    # compilar el frontend (ver nota abajo)
```

Para borrar **también** la base de datos (volumen `capa_db_data_pgsql`): `docker compose --profile local down -v`.

## Estructura

```
app/                      Aplicación (usuarios, estudiantes, profesores, administrativos, visitantes…)
database/seeders/         Seeders: DatabaseSeeder → DemoSeeder → MobilitySeeder…
packages/
  academic-mobility/      Módulo reutilizable de movilidad académica (API, páginas React, tests)
  api-response/           Formato común de respuestas de la API (Response::api/success/error)
resources/js/             Frontend React + Inertia
lang/                     Traducciones (es) de la aplicación
.docker/                  Dockerfile de PHP, Nginx
```

El módulo de movilidad tiene su propio README en [`packages/academic-mobility/README.md`](packages/academic-mobility/README.md).

## Problemas frecuentes

- **`Connection refused` con Redis o Horizon se reinicia:** `REDIS_HOST` debe ser `cache`, no `127.0.0.1`, y `REDIS_PASSWORD` debe estar en `.env`.
- **No hay base de datos:** levantaste sin `--profile local`. Usa `docker compose --profile local up -d`.
- **La base `app` no existe o está vacía tras recrear el volumen:** `docker compose --profile local down -v` y vuelve a levantar; Postgres crea la base en el primer arranque.
- **Error de permisos en `storage/` o `bootstrap/cache`:** `docker compose exec backend chmod -R ug+rwX storage bootstrap/cache`.
- **El navegador responde 409 al navegar (Inertia):** quedó una carpeta `public/build/` de una compilación anterior y choca con Vite en desarrollo. Bórrala: `rm -rf public/build`. Solo la necesitas si compilas con `pnpm build`.
- **Cambios en `.env` o config sin efecto:** `docker compose exec backend php artisan config:clear`.
- **Faltan las rutas tipadas (`@/routes`) o los permisos en TypeScript:** ejecuta `php artisan wayfinder:generate` y `php artisan permissions:export-ts`.
- **El selector de idioma no cambia el texto de una página:** esa página aún no está traducida (las de solicitudes, acceso y ajustes siguen en inglés).
