<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Helpers;

class SearchTermEscaper
{
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
