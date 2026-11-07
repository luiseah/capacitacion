<?php

namespace App\Support;

use Illuminate\Database\Connection;

/**
 * PHP twin of the PostgreSQL `search(text)` function, registered on SQLite
 * connections so the same queries run in tests.
 */
class SearchFunction
{
    private const ACCENTS = [
        'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a', 'ã' => 'a',
        'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
        'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o', 'õ' => 'o',
        'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
        'ñ' => 'n', 'ç' => 'c', 'ý' => 'y', 'ÿ' => 'y',
    ];

    public static function normalize(?string $value): ?string
    {
        return $value === null ? null : strtr(mb_strtolower($value), self::ACCENTS);
    }

    public static function register(Connection $connection): void
    {
        if ($connection->getDriverName() !== 'sqlite') {
            return;
        }

        $connection->getPdo()->sqliteCreateFunction('search', self::normalize(...), 1);
    }
}
