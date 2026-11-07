<?php

namespace App\Support;

/**
 * Helpers to turn what the user typed into LIKE patterns.
 */
class SearchInput
{
    /**
     * Split the text into words; every word must match.
     *
     * @return array<int, string>
     */
    public static function tokens(string $text): array
    {
        return preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /**
     * A contains-pattern with the LIKE wildcards of the input escaped.
     */
    public static function like(string $token): string
    {
        return '%'.addcslashes($token, '\\%_').'%';
    }
}
