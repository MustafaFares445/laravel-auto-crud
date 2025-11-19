<?php

namespace Mrmarchone\LaravelAutoCrud\Transformers;

class SpatieDataTransformer
{
    public static function convertDataToString(array $data, string $traitUsage = ''): string
    {
        $string = '';
        $indent = str_repeat(' ', 4);

        // Add trait usage at the beginning if provided
        if ($traitUsage) {
            $string .= $traitUsage . "\n\n";
        }

        foreach ($data as $key => $value) {
            $string .= $indent.$value."\n".$indent.$key."\n";
        }

        return $string;
    }

    public static function convertNamespacesToString(array $data): string
    {
        $string = '';
        foreach ($data as $value) {
            $string .= $value."\n";
        }

        return $string;
    }
}
