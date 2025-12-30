<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ReflectionClass;

class EnumDetector
{
    /**
     * Detect if an enum class already exists for a model column.
     * Checks in:
     * 1. Model casts
     * 2. Migration files
     * 3. Existing enum files
     *
     * @param string $modelClass Full model class name
     * @param string $columnName Column name
     * @param string $modelName Model name (without namespace)
     * @return string|null Full enum class name if found, null otherwise
     */
    public static function detectExistingEnum(string $modelClass, string $columnName, string $modelName): ?string
    {
        // Check model casts first
        $enumFromCasts = self::getEnumFromModelCasts($modelClass, $columnName);
        if ($enumFromCasts) {
            return $enumFromCasts;
        }

        // Check migration files
        $enumFromMigrations = self::getEnumFromMigrations($modelName, $columnName);
        if ($enumFromMigrations) {
            return $enumFromMigrations;
        }

        // Check if enum file already exists with expected naming patterns
        $columnNamePascal = Str::studly($columnName);
        
        // Pattern 1: ModelName + ColumnName + Enum (e.g., WorkerStatusEnum)
        $expectedEnumName = $modelName . $columnNamePascal . 'Enum';
        $enumPath = app_path("Enums/{$expectedEnumName}.php");
        if (file_exists($enumPath) && enum_exists("App\\Enums\\{$expectedEnumName}")) {
            return "App\\Enums\\{$expectedEnumName}";
        }
        
        // Pattern 2: ModelName + ColumnName (e.g., WorkerStatus) - without Enum suffix
        $expectedEnumNameNoSuffix = $modelName . $columnNamePascal;
        $enumPathNoSuffix = app_path("Enums/{$expectedEnumNameNoSuffix}.php");
        if (file_exists($enumPathNoSuffix) && enum_exists("App\\Enums\\{$expectedEnumNameNoSuffix}")) {
            return "App\\Enums\\{$expectedEnumNameNoSuffix}";
        }
        
        // Pattern 3: Just ColumnName (e.g., Status)
        $enumPathColumn = app_path("Enums/{$columnNamePascal}.php");
        if (file_exists($enumPathColumn) && enum_exists("App\\Enums\\{$columnNamePascal}")) {
            return "App\\Enums\\{$columnNamePascal}";
        }

        return null;
    }

    /**
     * Get enum class from model casts.
     *
     * @param string $modelClass Full model class name
     * @param string $columnName Column name
     * @return string|null Enum class name if found
     */
    private static function getEnumFromModelCasts(string $modelClass, string $columnName): ?string
    {
        if (!class_exists($modelClass)) {
            return null;
        }

        try {
            $reflection = new ReflectionClass($modelClass);
            $modelFile = $reflection->getFileName();
            
            if (!$modelFile || !file_exists($modelFile)) {
                return null;
            }
            
            // Read model file to extract cast class names
            $modelContent = File::get($modelFile);
            $enumClass = self::extractEnumFromModelContent($modelContent, $columnName);
            if ($enumClass) {
                return $enumClass;
            }
            
            // Fallback: Check $casts property via reflection
            if ($reflection->hasProperty('casts')) {
                $castsProperty = $reflection->getProperty('casts');
                $castsProperty->setAccessible(true);
                $casts = $castsProperty->getValue($reflection->newInstanceWithoutConstructor()) ?? [];
                
                if (isset($casts[$columnName])) {
                    $castValue = $casts[$columnName];
                    return self::resolveEnumClass($castValue);
                }
            }

            // Check getCasts() or casts() method if it exists
            foreach (['getCasts', 'casts'] as $methodName) {
                if ($reflection->hasMethod($methodName)) {
                    try {
                        $model = $reflection->newInstanceWithoutConstructor();
                        $casts = $model->{$methodName}();
                        if (isset($casts[$columnName])) {
                            $castValue = $casts[$columnName];
                            $resolved = self::resolveEnumClass($castValue);
                            if ($resolved) {
                                return $resolved;
                            }
                        }
                    } catch (\Throwable $e) {
                        // Ignore errors
                    }
                }
            }
        } catch (\Throwable $e) {
            // Ignore reflection errors
        }

        return null;
    }

    /**
     * Extract enum class from model file content.
     *
     * @param string $content Model file content
     * @param string $columnName Column name
     * @return string|null Enum class name if found
     */
    private static function extractEnumFromModelContent(string $content, string $columnName): ?string
    {
        // Pattern 1: 'column' => EnumClass::class,
        // Pattern 2: 'column' => 'App\Enums\EnumClass',
        // Pattern 3: 'column' => EnumClass::class in casts() method
        
        // Look for the column in casts array
        $patterns = [
            // In $casts property or casts() method return - with ::class
            "/['\"]{$columnName}['\"]\s*=>\s*([A-Za-z0-9_\\\\]+)::class/",
            // With quoted string
            "/['\"]{$columnName}['\"]\s*=>\s*['\"]([A-Za-z0-9_\\\\]+)['\"]/",
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                $enumClass = trim($matches[1]);
                
                // If it's a short name, try to resolve from use statements
                if (!str_contains($enumClass, '\\')) {
                    $fullClass = self::resolveFromUseStatements($content, $enumClass);
                    if ($fullClass && enum_exists($fullClass)) {
                        return $fullClass;
                    }
                }
                
                // Try direct resolution
                $resolved = self::resolveEnumClass($enumClass);
                if ($resolved) {
                    return $resolved;
                }
            }
        }
        
        return null;
    }

    /**
     * Resolve class name from use statements in file content.
     *
     * @param string $content File content
     * @param string $shortClassName Short class name
     * @return string|null Full class name if found
     */
    private static function resolveFromUseStatements(string $content, string $shortClassName): ?string
    {
        // Look for use statement: use App\Enums\WorkerStatus;
        if (preg_match("/use\s+([^;]+\\\\{$shortClassName});/", $content, $matches)) {
            $fullClass = trim($matches[1]);
            if (enum_exists($fullClass)) {
                return $fullClass;
            }
        }
        
        return null;
    }

    /**
     * Resolve enum class name from various formats.
     *
     * @param string|object $castValue Cast value (can be string class name or class constant)
     * @return string|null Full enum class name if found
     */
    private static function resolveEnumClass($castValue): ?string
    {
        // Handle class constant (::class)
        if (is_object($castValue) && method_exists($castValue, '__toString')) {
            $castValue = (string) $castValue;
        }
        
        if (!is_string($castValue)) {
            return null;
        }
        
        // If it's already a full class name, check if it's an enum
        if (enum_exists($castValue)) {
            return $castValue;
        }
        
        // If it starts with backslash, try as absolute namespace
        if (str_starts_with($castValue, '\\')) {
            $fullClass = ltrim($castValue, '\\');
            if (enum_exists($fullClass)) {
                return $fullClass;
            }
        }
        
        // Try App\Enums namespace (with and without Enum suffix)
        $fullClass = "App\\Enums\\{$castValue}";
        if (enum_exists($fullClass)) {
            return $fullClass;
        }
        
        // Try with Enum suffix if not already present
        if (!str_ends_with($castValue, 'Enum')) {
            $fullClassWithEnum = "App\\Enums\\{$castValue}Enum";
            if (enum_exists($fullClassWithEnum)) {
                return $fullClassWithEnum;
            }
        }
        
        return null;
    }

    /**
     * Get enum class from migration files.
     *
     * @param string $modelName Model name
     * @param string $columnName Column name
     * @return string|null Enum class name if found
     */
    private static function getEnumFromMigrations(string $modelName, string $columnName): ?string
    {
        $migrationsPath = database_path('migrations');
        
        if (!is_dir($migrationsPath)) {
            return null;
        }

        $tableName = Str::snake(Str::plural($modelName));
        $columnNameSnake = Str::snake($columnName);
        
        try {
            $migrationFiles = File::allFiles($migrationsPath);
            
            foreach ($migrationFiles as $file) {
                $content = File::get($file->getRealPath());
                
                // Look for enum usage in migrations
                // Pattern 1: $table->enum('column', array_values(EnumClass::cases()))
                // Pattern 2: $table->enum('column', [EnumClass::Active->value, ...])
                // Pattern 3: enum('column', array_values(EnumClass::cases()))
                
                // Check for array_values(EnumClass::cases()) pattern (with or without Enum suffix)
                if (preg_match_all('/array_values\(([A-Za-z0-9_\\\\]+(?:Enum)?)::cases\(\)\)/', $content, $matches)) {
                    foreach ($matches[1] as $enumClass) {
                        // Check if this migration is for our table and column
                        if (self::isMigrationForColumn($content, $tableName, $columnNameSnake)) {
                            // Resolve enum class name
                            $fullEnumClass = self::resolveEnumClassName($enumClass, $content);
                            if ($fullEnumClass && enum_exists($fullEnumClass)) {
                                return $fullEnumClass;
                            }
                        }
                    }
                }
                
                // Check for EnumClass::case->value pattern (with or without Enum suffix)
                if (preg_match_all('/([A-Za-z0-9_\\\\]+(?:Enum)?)::[A-Za-z0-9_]+->value/', $content, $matches)) {
                    foreach ($matches[1] as $enumClass) {
                        if (self::isMigrationForColumn($content, $tableName, $columnNameSnake)) {
                            $fullEnumClass = self::resolveEnumClassName($enumClass, $content);
                            if ($fullEnumClass && enum_exists($fullEnumClass)) {
                                return $fullEnumClass;
                            }
                        }
                    }
                }
                
                // Also check for App\Enums\EnumClass pattern
                if (preg_match_all('/(App\\\\Enums\\\\[A-Za-z0-9_]+(?:Enum)?)::cases\(\)/', $content, $matches)) {
                    foreach ($matches[1] as $enumClass) {
                        if (self::isMigrationForColumn($content, $tableName, $columnNameSnake)) {
                            $fullEnumClass = str_replace('\\\\', '\\', $enumClass);
                            if (enum_exists($fullEnumClass)) {
                                return $fullEnumClass;
                            }
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            // Ignore errors
        }

        return null;
    }

    /**
     * Check if migration content is for the specified table and column.
     *
     * @param string $content Migration file content
     * @param string $tableName Table name
     * @param string $columnName Column name
     * @return bool
     */
    private static function isMigrationForColumn(string $content, string $tableName, string $columnName): bool
    {
        // Check if migration mentions the table
        $hasTable = str_contains($content, "'{$tableName}'") || 
                   str_contains($content, "\"{$tableName}\"") ||
                   str_contains($content, "->table('{$tableName}'") ||
                   str_contains($content, "->table(\"{$tableName}\"");
        
        // Check if migration mentions the column
        $hasColumn = str_contains($content, "'{$columnName}'") || 
                    str_contains($content, "\"{$columnName}\"") ||
                    str_contains($content, "->enum('{$columnName}'") ||
                    str_contains($content, "->enum(\"{$columnName}\"");
        
        return $hasTable && $hasColumn;
    }

    /**
     * Resolve enum class name from migration content.
     * Checks for use statements to get full namespace.
     *
     * @param string $enumClass Short enum class name
     * @param string $content Migration file content
     * @return string|null Full enum class name
     */
    private static function resolveEnumClassName(string $enumClass, string $content): ?string
    {
        // If it already contains namespace, return as is
        if (str_contains($enumClass, '\\')) {
            $normalized = str_replace('\\\\', '\\', $enumClass);
            if (enum_exists($normalized)) {
                return $normalized;
            }
        }

        // Check for use statements (handle both with and without Enum suffix)
        $patterns = [
            "/use\s+([^;]+\\\\{$enumClass});/",
            "/use\s+([^;]+)\s+as\s+{$enumClass};/",
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                $fullClass = trim($matches[1]);
                if (enum_exists($fullClass)) {
                    return $fullClass;
                }
            }
        }

        // Try App\Enums namespace (with and without Enum suffix)
        $fullClass = "App\\Enums\\{$enumClass}";
        if (enum_exists($fullClass)) {
            return $fullClass;
        }
        
        // Try with Enum suffix if not already present
        if (!str_ends_with($enumClass, 'Enum')) {
            $fullClassWithEnum = "App\\Enums\\{$enumClass}Enum";
            if (enum_exists($fullClassWithEnum)) {
                return $fullClassWithEnum;
            }
        }

        return null;
    }
}

