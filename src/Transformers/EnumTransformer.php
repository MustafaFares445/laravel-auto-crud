<?php

namespace Mrmarchone\LaravelAutoCrud\Transformers;

use Illuminate\Support\Str;

class EnumTransformer
{
    public static function convertDataToString(array $data): string
    {
        $string = '';
        $indent = str_repeat(' ', 4);
        foreach ($data as $value) {
            // Convert value to PascalCase for case name (e.g., 'active' -> 'Active', 'out_of_service' -> 'OutOfService')
            $caseName = Str::studly($value);
            $string .= $indent.'case '.$caseName.' = '."'$value';"."\n";
        }

        return $string;
    }
}
