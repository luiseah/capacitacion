-- Runs once, when the database volume is created (POSTGRES_DB = app already exists).
-- Everything the application and its tests need at database level, so a fresh clone works
-- with `docker compose up` and `php artisan migrate --seed`.

-- The application's schema: the default one (DB_SCHEMA=public).
CREATE SCHEMA IF NOT EXISTS public;

-- The tests' schema (DB_SCHEMA=testing in .env.testing): RefreshDatabase only drops its tables,
-- never those of `public`.
CREATE SCHEMA IF NOT EXISTS testing;

-- The search() function of the application ignores accents with unaccent. It is installed once, in
-- `public`, and both schemas use it.
CREATE EXTENSION IF NOT EXISTS unaccent SCHEMA public;
