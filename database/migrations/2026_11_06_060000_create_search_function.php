<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `search(text)`: lower-cased text without accents, used on both sides of a
 * LIKE so "jose" matches "José". PostgreSQL only; SQLite (tests) gets the same
 * function registered from PHP, see App\Support\SearchFunction.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared('CREATE EXTENSION IF NOT EXISTS unaccent SCHEMA public');

        // The extension is installed once, in `public`, so every schema can use it;
        // `search()` is created in the current schema, so
        // name the extension's schema (it may already live elsewhere).
        $schema = DB::scalar("select extnamespace::regnamespace::text from pg_extension where extname = 'unaccent'");

        // `unaccent(text)` is only STABLE; naming the dictionary makes the
        // wrapper IMMUTABLE, so it can back an expression index.
        DB::unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION search(value text) RETURNS text
            LANGUAGE sql IMMUTABLE PARALLEL SAFE STRICT
            AS \$\$ SELECT lower({$schema}.unaccent('{$schema}.unaccent'::regdictionary, value)) \$\$
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared('DROP FUNCTION IF EXISTS search(text)');
    }
};
