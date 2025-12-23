<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Helpers;

/**
 * Helper class for safely escaping search terms for SQL LIKE queries.
 *
 * Prevents SQL injection by escaping special characters used in LIKE patterns
 * (% and _) and wrapping the term with wildcards for partial matching.
 */
class SearchTermEscaper
{
    /**
     * Escape special characters in a search term for safe use in SQL LIKE queries.
     *
     * Escapes the following special characters:
     * - `%` (percent sign - matches any sequence of characters)
     * - `_` (underscore - matches any single character)
     * - The escape character itself (default: `!`)
     *
     * The escaped term is then wrapped with `%` wildcards to enable partial matching.
     * This prevents SQL injection while allowing flexible search functionality.
     *
     * @param string $term The search term to escape
     * @param string $escapeCharacter The character to use for escaping (default: '!')
     * @return string The escaped search term wrapped with % wildcards (e.g., "%escaped%term%")
     *
     * @example
     * // Basic usage
     * SearchTermEscaper::escape('test');
     * // Returns: "%test%"
     *
     * // Escaping special characters
     * SearchTermEscaper::escape('test_%value');
     * // Returns: "%test!_!%value%" (special chars are escaped)
     *
     * // Custom escape character
     * SearchTermEscaper::escape('test%value', '\\');
     * // Returns: "%test\\%value%"
     */
    public static function escape(string $term, string $escapeCharacter = '!'): string
    {
        $escaped = str_replace(
            [$escapeCharacter, '%', '_'],
            [$escapeCharacter.$escapeCharacter, $escapeCharacter.'%', $escapeCharacter.'_'],
            $term
        );

        return "%{$escaped}%";
    }
}
