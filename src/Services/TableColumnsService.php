<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class TableColumnsService
{
    public function getAvailableColumns(string $table, array $excludeColumns = ['created_at', 'updated_at'], ?string $modelClass = null): array
    {
        $columns = Schema::getColumnListing($table);

        $driver = DB::connection()->getDriverName();

        $output = [];

        // Check if model uses Spatie Media Library
        $usesMediaLibrary = $this->usesMediaLibrary($modelClass);
        $mediaExcludeColumns = $usesMediaLibrary ? ['model_id', 'model_type'] : [];

        foreach ($columns as $column) {
            $columnDetails = $this->getColumnDetails($driver, $table, $column, $modelClass);

            if (count($columnDetails)) {
                // Exclude primary keys, timestamps, and media library columns
                if ($columnDetails['isPrimaryKey'] || in_array($column, $excludeColumns) || in_array($column, $mediaExcludeColumns)) {
                    continue;
                }

                $columnDetails['isTranslatable'] = $this->isTranslatable($column, $modelClass);
                $output[] = $columnDetails;
            }
        }

        return $output;
    }

    /**
     * Check if a model uses Spatie Media Library traits.
     *
     * @param string|null $modelClass
     * @return bool
     */
    private function usesMediaLibrary(?string $modelClass): bool
    {
        if (!$modelClass || !class_exists($modelClass)) {
            return false;
        }

        try {
            $reflection = new \ReflectionClass($modelClass);
            $traits = $reflection->getTraitNames();

            foreach ($traits as $trait) {
                if (str_contains($trait, 'InteractsWithMedia') ||
                    str_contains($trait, 'HasMediaConversions') ||
                    str_contains($trait, 'HasMedia')) {
                    return true;
                }
            }

            return false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function isTranslatable(string $column, ?string $modelClass): bool
    {
        if (!$modelClass || !class_exists($modelClass)) {
            return false;
        }

        try {
            $model = new $modelClass;
            if (property_exists($model, 'translatable')) {
                return in_array($column, $model->translatable);
            }
            if (method_exists($model, 'getTranslatableAttributes')) {
                return in_array($column, $model->getTranslatableAttributes());
            }
        } catch (\Throwable $e) {
            // Ignore errors
        }

        return false;
    }

    private function getColumnDetails(string $driver, string $table, string $column, ?string $modelClass = null): array
    {
        return match ($driver) {
            'mysql' => $this->getMySQLColumnDetails($table, $column, $modelClass),
            'pgsql' => $this->getPostgresColumnDetails($table, $column, $modelClass),
            'sqlite' => $this->getSQLiteColumnDetails($table, $column),
            'sqlsrv' => $this->getSQLServerColumnDetails($table, $column),
            default => [],
        };
    }

    private function getMysqlColumnDetails(string $table, string $column, ?string $modelClass = null): array
    {
        $columnDetails = DB::select("SHOW COLUMNS FROM `$table` WHERE Field = ?", [$column]);

        if (empty($columnDetails)) {
            return [];
        }

        $columnInfo = $columnDetails[0];
        $isPrimaryKey = ($columnInfo->Key === 'PRI');
        $isUnique = ($columnInfo->Key === 'UNI');
        $isNullable = ($columnInfo->Null === 'YES');
        $maxLength = isset($columnInfo->Type) && preg_match('/\((\d+)\)/', $columnInfo->Type, $matches) ? $matches[1] : null;

        $allowedValues = [];
        $enumClass = null;
        if (str_starts_with($columnInfo->Type, 'enum')) {
            preg_match("/^enum\((.+)\)$/", $columnInfo->Type, $matches);
            $allowedValues = isset($matches[1]) ? str_getcsv(str_replace("'", '', $matches[1])) : [];

            // Use EnumDetector to find existing enum from model casts, migrations, or files
            if ($modelClass) {
                $parts = explode('\\', $modelClass);
                $modelName = end($parts);
                $enumClass = \Mrmarchone\LaravelAutoCrud\Services\EnumDetector::detectExistingEnum($modelClass, $column, $modelName);
            }
            
            // Fallback to simple file check if EnumDetector didn't find anything
            if (!$enumClass) {
                $studly = Str::studly($column);
                $candidate = app_path("Enums/{$studly}.php");
                if (file_exists($candidate)) {
                    $enumClass = 'App\\Enums\\' . $studly;
                } elseif ($modelClass) {
                    $parts = explode('\\', $modelClass);
                    $modelName = end($parts);
                    $modelEnum = $modelName . $studly . 'Enum';
                    $candidate2 = app_path("Enums/{$modelEnum}.php");
                    if (file_exists($candidate2)) {
                        $enumClass = 'App\\Enums\\' . $modelEnum;
                    }
                }
            }
        }

        return [
            'isPrimaryKey' => $isPrimaryKey,
            'isUnique' => $isUnique,
            'isNullable' => $isNullable,
            'type' => Schema::getColumnType($table, $column),
            'maxLength' => $maxLength,
            'allowedValues' => $allowedValues,
            'enum_class' => $enumClass,
            'name' => $column,
            'table' => $table,
        ];
    }

    private function getPostgresColumnDetails(string $table, string $column, ?string $modelClass = null): array
    {
        $columnDetails = DB::select("
                                    SELECT
                                        column_name,
                                        column_default,
                                        is_nullable,
                                        data_type,
                                        character_maximum_length,
                                        udt_name,
                                        (SELECT COUNT(*) > 0 FROM information_schema.table_constraints tc
                                            JOIN information_schema.constraint_column_usage ccu
                                            ON tc.constraint_name = ccu.constraint_name
                                            WHERE tc.table_name = ? AND ccu.column_name = ? AND tc.constraint_type = 'PRIMARY KEY') AS is_primary,
                                        (SELECT COUNT(*) > 0 FROM information_schema.table_constraints tc
                                            JOIN information_schema.constraint_column_usage ccu
                                            ON tc.constraint_name = ccu.constraint_name
                                            WHERE tc.table_name = ? AND ccu.column_name = ? AND tc.constraint_type = 'UNIQUE') AS is_unique
                                    FROM information_schema.columns
                                    WHERE table_name = ? AND column_name = ?",
            [$table, $column, $table, $column, $table, $column]
        );

        if (empty($columnDetails)) {
            return [];
        }

        $columnInfo = $columnDetails[0];
        $isPrimaryKey = $columnInfo->is_primary;
        $isUnique = $columnInfo->is_unique;
        $isNullable = ($columnInfo->is_nullable === 'YES');
        $maxLength = $columnInfo->character_maximum_length ?? null;

        $allowedValues = [];
        $enumClass = null;
        if (str_starts_with($columnInfo->udt_name, '_')) {
            preg_match('/^_(.+)$/', $columnInfo->udt_name, $matches);
            $allowedValues = isset($matches[1]) ? str_getcsv(str_replace("'", '', $matches[1])) : [];

            // Use EnumDetector to find existing enum from model casts, migrations, or files
            if ($modelClass) {
                $parts = explode('\\', $modelClass);
                $modelName = end($parts);
                $enumClass = \Mrmarchone\LaravelAutoCrud\Services\EnumDetector::detectExistingEnum($modelClass, $column, $modelName);
            }
            
            // Fallback to simple file check if EnumDetector didn't find anything
            if (!$enumClass) {
                $studly = Str::studly($column);
                $candidate = app_path("Enums/{$studly}.php");
                if (file_exists($candidate)) {
                    $enumClass = 'App\\Enums\\' . $studly;
                } elseif ($modelClass) {
                    $parts = explode('\\', $modelClass);
                    $modelName = end($parts);
                    $modelEnum = $modelName . $studly . 'Enum';
                    $candidate2 = app_path("Enums/{$modelEnum}.php");
                    if (file_exists($candidate2)) {
                        $enumClass = 'App\\Enums\\' . $modelEnum;
                    }
                }
            }
        }

        return [
            'isPrimaryKey' => $isPrimaryKey,
            'isUnique' => $isUnique,
            'isNullable' => $isNullable,
            'type' => Schema::getColumnType($table, $column),
            'maxLength' => $maxLength,
            'allowedValues' => $allowedValues,
            'enum_class' => $enumClass,
            'name' => $column,
            'table' => $table,
        ];
    }

    private function getSqliteColumnDetails(string $table, string $column): array
    {
        $columnDetails = DB::select("PRAGMA table_info($table)");
        foreach ($columnDetails as $col) {
            if ($col->name === $column) {
                return [
                    'isPrimaryKey' => $col->pk == 1,
                    'isUnique' => false,
                    'isNullable' => $col->notnull == 0,
                    'type' => Schema::getColumnType($table, $column),
                    'maxLength' => null,
                    'allowedValues' => [],
                    'name' => $column,
                    'table' => $table,
                ];
            }
        }

        return [];
    }

    private function getSQLServerColumnDetails(string $table, string $column): array
    {
        $columnDetails = DB::select(
            "SELECT COLUMN_NAME, COLUMNPROPERTY(object_id(?), COLUMN_NAME, 'IsIdentity') AS is_identity, IS_NULLABLE, DATA_TYPE
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_NAME = ? AND COLUMN_NAME = ?",
            [$table, $table, $column]
        );

        if (empty($columnDetails)) {
            return [];
        }

        $columnInfo = $columnDetails[0];

        return [
            'isPrimaryKey' => $columnInfo->is_identity,
            'isUnique' => false,
            'isNullable' => $columnInfo->IS_NULLABLE === 'YES',
            'type' => Schema::getColumnType($table, $column),
            'maxLength' => null,
            'allowedValues' => [],
            'name' => $column,
            'table' => $table,
        ];
    }
}
