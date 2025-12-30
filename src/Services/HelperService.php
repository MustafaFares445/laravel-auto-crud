<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Services;

use function Laravel\Prompts\multiselect;

class HelperService
{
    /**
     * Format an array to PHP syntax string.
     *
     * @param array<string|int, mixed> $data Array to format
     * @param bool $removeValuesQuotes Whether to remove quotes from values (for PHP code)
     * @param int $indentation Number of spaces for indentation
     * @return string Formatted PHP array syntax
     */
    public static function formatArrayToPhpSyntax(array $data, bool $removeValuesQuotes = false, int $indentation = 12): string
    {
        $indent = str_repeat(' ', $indentation);
        $formattedArray = "[\n";

        // Check if array is sequential (numeric keys starting from 0)
        $isSequential = self::isSequentialArray($data);

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                // Recursively format nested arrays
                // The nested array will be formatted correctly based on its own structure
                $nestedArray = self::formatArrayToPhpSyntax($value, $removeValuesQuotes, $indentation + 4);
                
                if ($isSequential) {
                    // For sequential arrays, don't include key
                    $formattedArray .= $indent."$nestedArray,\n";
                } else {
                    // For associative arrays, include key
                    $escapedKey = addslashes((string) $key);
                    $formattedArray .= $indent."'$escapedKey' => $nestedArray,\n";
                }
            } else {
                // Format the value
                $formattedValue = self::formatValue($value, $removeValuesQuotes);
                
                if ($isSequential) {
                    // For sequential arrays, don't include key
                    $formattedArray .= $indent."$formattedValue,\n";
                } else {
                    // For associative arrays, include key
                    $escapedKey = addslashes((string) $key);
                    $formattedArray .= $indent."'$escapedKey' => $formattedValue,\n";
                }
            }
        }

        $formattedArray .= str_repeat(' ', $indentation - 4).']';

        return $formattedArray;
    }

    /**
     * Check if an array is sequential (numeric keys starting from 0).
     *
     * @param array $array Array to check
     * @return bool True if sequential, false otherwise
     */
    private static function isSequentialArray(array $array): bool
    {
        if (empty($array)) {
            return true;
        }

        $keys = array_keys($array);
        return $keys === range(0, count($array) - 1);
    }

    /**
     * Format a single value for PHP syntax.
     *
     * @param mixed $value Value to format
     * @param bool $removeValuesQuotes Whether to remove quotes from values
     * @return string Formatted value
     */
    private static function formatValue($value, bool $removeValuesQuotes): string
    {
        if ($removeValuesQuotes) {
            // For non-array values when removing quotes, output as-is (for PHP code)
            return (string) $value;
        }

        // Convert value to string safely
        if (is_null($value)) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        $stringValue = (string) $value;
        
        // Check if the value looks like PHP code (Rule::, ->, ::class, etc.)
        if (str_starts_with($stringValue, 'Rule::') || str_contains($stringValue, '::class') || str_contains($stringValue, '->')) {
            // Output PHP code without quotes
            return $stringValue;
        }

        // Escape single quotes and backslashes in string values
        $escapedValue = addslashes($stringValue);
        return "'$escapedValue'";
    }

    /**
     * Convert a string to snake_case.
     *
     * @param string $text Text to convert
     * @return string Converted snake_case string
     */
    public static function toSnakeCase(string $text): string
    {
        // Convert camelCase or PascalCase (ModelName -> model_name)
        $text = preg_replace('/([a-z])([A-Z])/', '$1_$2', $text);

        // Replace any non-alphanumeric characters (spaces, dashes, etc.) with underscores
        $text = preg_replace('/[^a-zA-Z0-9]+/', '_', $text);

        // Convert to lowercase
        return strtolower(trim($text, '_'));
    }

    public static function displaySignature(): void
    {
        $asciiArt = <<<ASCII
                _           _____ _____  _    _ _____
     /\        | |         / ____|  __ \| |  | |  __ \
    /  \  _   _| |_ ___   | |    | |__) | |  | | |  | |
   / /\ \| | | | __/ _ \  | |    |  _  /| |  | | |  | |
  / ____ \ |_| | || (_) | | |____| | \ \| |__| | |__| |
 /_/    \_\__,_|\__\___/   \_____|_|  \_\\____/|_____/
                                         Free Palestine
ASCII;

        echo "\n$asciiArt\n\n";
        echo "[+] Name: Abdelrahman Muhammed\n";
        echo "[+] Email: mrmarchone@gmail.com\n";

        echo "[+] Name: Mustafa Fares\n";
        echo "[+] Email: mustafa.fares.dev@gmail.com\n";
    }

    /**
     * Ask user to select controller type(s).
     *
     * @param \Symfony\Component\Console\Input\InputInterface $inputType Input interface
     * @param array<string>|null $types Pre-selected types
     * @return void
     */
    public static function askForType($inputType, ?array $types = []): void
    {
        $newTypes = count($types) > 0 ? $types : multiselect(
            label: 'Do you want to create an "api", "web" or "both" controller?',
            options: ['api', 'web'],
            default: ['api'],
            required: true,
            validate: function ($value) {
                if (empty(array_intersect($value, ['api', 'web']))) {
                    return 'Please enter a valid type api or web';
                }

                return null;
            },
            hint: 'Select api, web or both',
        );

        $types = array_map('strtolower', $newTypes);

        $inputType->setOption('type', $types);
    }
}
