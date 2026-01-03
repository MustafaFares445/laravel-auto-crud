<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Builders;

use Mrmarchone\LaravelAutoCrud\Services\TableColumnsService;

class ImportBuilder extends BaseBuilder
{
    protected TableColumnsService $tableColumnsService;

    public function __construct($fileService, TableColumnsService $tableColumnsService)
    {
        parent::__construct($fileService);
        $this->tableColumnsService = $tableColumnsService;
    }

    /**
     * Create an import class for a model.
     *
     * @param array<string, mixed> $modelData Model information
     * @param bool $overwrite Whether to overwrite existing files
     * @return string Full namespace path of created import class
     */
    public function createImport(array $modelData, bool $overwrite = false): string
    {
        $importNamespace = 'App\\Imports';
        if ($modelData['folders']) {
            $importNamespace .= '\\' . str_replace('/', '\\', $modelData['folders']);
        }

        $className = $modelData['modelName'] . 'Import';
        
        return $this->fileService->createFromStub(
            $modelData,
            'import',
            'Imports',
            'Import',
            $overwrite,
            function ($modelData) use ($importNamespace, $className) {
                $model = $this->getFullModelNamespace($modelData);
                $modelInstance = new $model;
                $tableName = $modelInstance->getTable();
                $columns = $this->tableColumnsService->getAvailableColumns($tableName, ['id', 'created_at', 'updated_at'], $model);
                
                $rules = $this->generateRules($columns);
                $modelCreation = $this->generateModelCreation($columns, $modelData['modelName']);

                return [
                    '{{ namespace }}' => $importNamespace,
                    '{{ class }}' => $className,
                    '{{ modelNamespace }}' => $model,
                    '{{ model }}' => $modelData['modelName'],
                    '{{ rules }}' => $rules,
                    '{{ modelCreation }}' => $modelCreation,
                ];
            }
        );
    }

    /**
     * Generate validation rules for import.
     *
     * @param array<int, array<string, mixed>> $columns
     * @return string Generated rules code
     */
    private function generateRules(array $columns): string
    {
        $rules = [];
        foreach ($columns as $column) {
            $rule = $this->getRuleForColumn($column);
            if ($rule) {
                $rules[] = "            '" . str_replace('_', ' ', $column['name']) . "' => '{$rule}'";
            }
        }
        return implode(",\n", $rules);
    }

    /**
     * Get validation rule for a column.
     *
     * @param array<string, mixed> $column
     * @return string
     */
    private function getRuleForColumn(array $column): string
    {
        $rules = ['required'];
        $type = strtolower($column['type'] ?? '');

        if (str_contains($type, 'int')) {
            $rules[] = 'integer';
        } elseif (str_contains($type, 'decimal') || str_contains($type, 'float')) {
            $rules[] = 'numeric';
        } elseif (str_contains($type, 'bool')) {
            $rules[] = 'boolean';
        } elseif (str_contains($column['name'], 'email')) {
            $rules[] = 'email';
        } else {
            $rules[] = 'string';
            if (isset($column['length'])) {
                $rules[] = 'max:' . $column['length'];
            }
        }

        return implode('|', $rules);
    }

    /**
     * Generate model creation code.
     *
     * @param array<int, array<string, mixed>> $columns
     * @param string $modelName
     * @return string Generated model creation code
     */
    private function generateModelCreation(array $columns, string $modelName): string
    {
        $fields = [];
        foreach ($columns as $column) {
            $fieldName = $column['name'];
            $fields[] = "            '{$fieldName}' => \$row['" . str_replace('_', ' ', $fieldName) . "']";
        }

        return "        {$modelName}::create([\n" . implode(",\n", $fields) . "\n        ]);";
    }
}

