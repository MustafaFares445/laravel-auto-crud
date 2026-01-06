<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Helpers;

use Illuminate\Support\Str;

class TestDataHelper
{
    /**
     * Generate a valid payload for a model based on column definitions.
     *
     * @param array $columns Array of column definitions
     * @param bool $isUpdate Whether this is for an update operation
     * @return array Valid payload array
     */
    public static function validPayload(array $columns, bool $isUpdate = false): array
    {
        $payload = [];
        
        foreach ($columns as $column) {
            $value = self::generateValidValue($column, $isUpdate);
            $camelCaseName = Str::camel($column['name']);
            $payload[$camelCaseName] = $value;
        }
        
        return $payload;
    }

    /**
     * Generate an invalid payload for testing validation errors.
     *
     * @param array $columns Array of column definitions
     * @param string $fieldToInvalidate Field name to make invalid (optional)
     * @return array Invalid payload array
     */
    public static function invalidPayload(array $columns, ?string $fieldToInvalidate = null): array
    {
        $payload = [];
        
        foreach ($columns as $column) {
            $camelCaseName = Str::camel($column['name']);
            
            if ($fieldToInvalidate && $camelCaseName === $fieldToInvalidate) {
                $payload[$camelCaseName] = self::generateInvalidValue($column);
            } else {
                $payload[$camelCaseName] = self::generateValidValue($column, false);
            }
        }
        
        return $payload;
    }

    /**
     * Generate edge case payload for testing boundary conditions.
     *
     * @param array $columns Array of column definitions
     * @return array Edge case payload array
     */
    public static function edgeCasePayload(array $columns): array
    {
        $payload = [];
        
        foreach ($columns as $column) {
            $camelCaseName = Str::camel($column['name']);
            $payload[$camelCaseName] = self::generateEdgeCaseValue($column);
        }
        
        return $payload;
    }

    /**
     * Generate a valid value for a column.
     *
     * @param array $column Column definition
     * @param bool $isUpdate Whether this is for an update operation
     * @return mixed Valid value
     */
    private static function generateValidValue(array $column, bool $isUpdate = false)
    {
        $type = $column['type'];
        $name = strtolower($column['name']);
        $suffix = $isUpdate ? ' updated' : '';

        if ($column['isTranslatable'] ?? false) {
            return ['en' => 'Sample ' . $column['name'] . $suffix];
        }

        if (!empty($column['allowedValues'])) {
            $enumClass = $column['enum_class'] ?? null;
            if ($enumClass && class_exists($enumClass)) {
                $cases = $enumClass::cases();
                return !empty($cases) ? $cases[0]->value : $column['allowedValues'][0];
            }
            return $column['allowedValues'][0];
        }

        // Handle special column names
        if (str_contains($name, 'email')) {
            return 'test' . ($isUpdate ? '_updated' : '') . '@example.com';
        }
        if (str_contains($name, 'phone')) {
            return '+1234567890';
        }
        if (str_contains($name, 'url') || str_contains($name, 'website')) {
            return 'https://example.com';
        }
        if (str_contains($name, 'slug')) {
            return 'sample-' . strtolower($column['name']) . ($isUpdate ? '-updated' : '');
        }

        return match ($type) {
            'integer', 'bigint', 'smallint' => 1,
            'decimal', 'float', 'double' => 10.5,
            'boolean' => true,
            'date' => '2025-01-01',
            'datetime', 'timestamp' => '2025-01-01 10:00:00',
            'json' => [],
            'uuid', 'char' => str_ends_with($name, '_id') ? null : fake()->uuid(),
            default => 'Sample ' . $column['name'] . $suffix,
        };
    }

    /**
     * Generate an invalid value for a column to test validation.
     *
     * @param array $column Column definition
     * @return mixed Invalid value
     */
    private static function generateInvalidValue(array $column)
    {
        $type = $column['type'];
        $name = strtolower($column['name']);

        // Generate invalid value based on expected type
        if (str_contains($name, 'email')) {
            return 'invalid-email';
        }
        if (str_contains($name, 'url') || str_contains($name, 'website')) {
            return 'not-a-url';
        }

        return match ($type) {
            'integer', 'bigint', 'smallint' => 'not-a-number',
            'decimal', 'float', 'double' => 'not-a-decimal',
            'boolean' => 'not-a-boolean',
            'date' => 'invalid-date',
            'datetime', 'timestamp' => 'invalid-datetime',
            'json' => 'not-json',
            'enum' => 'invalid-enum-value',
            default => null, // Missing required field
        };
    }

    /**
     * Generate edge case value for testing boundaries.
     *
     * @param array $column Column definition
     * @return mixed Edge case value
     */
    private static function generateEdgeCaseValue(array $column)
    {
        $type = $column['type'];
        $name = strtolower($column['name']);
        $maxLength = $column['maxLength'] ?? null;

        // Generate edge case based on type
        if ($type === 'string' || $type === 'varchar' || $type === 'char') {
            if ($maxLength) {
                // Generate string at max length boundary
                return str_repeat('a', (int) $maxLength);
            }
            // Very long string
            return str_repeat('a', 1000);
        }

        if (in_array($type, ['integer', 'bigint', 'smallint'])) {
            // Test with zero and negative numbers
            return -1;
        }

        if (in_array($type, ['decimal', 'float', 'double'])) {
            // Test with very large or very small numbers
            return 999999999.99;
        }

        if ($type === 'json') {
            // Test with complex nested structure
            return ['nested' => ['deep' => ['structure' => true]]];
        }

        // Default: return empty string or null
        return '';
    }

    /**
     * Generate SQL injection attempt payload.
     *
     * @return string SQL injection attempt string
     */
    public static function sqlInjectionAttempt(): string
    {
        return "'; DROP TABLE users; --";
    }

    /**
     * Generate XSS attempt payload.
     *
     * @return string XSS attempt string
     */
    public static function xssAttempt(): string
    {
        return '<script>alert("XSS")</script>';
    }

    /**
     * Generate empty payload (missing all required fields).
     *
     * @return array Empty array
     */
    public static function emptyPayload(): array
    {
        return [];
    }
}


