<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Builders;

use Illuminate\Support\Str;
use Mrmarchone\LaravelAutoCrud\Services\HelperService;
use Mrmarchone\LaravelAutoCrud\Services\ModelService;
use Mrmarchone\LaravelAutoCrud\Services\RelationshipDetector;
use Mrmarchone\LaravelAutoCrud\Services\TableColumnsService;
use Mrmarchone\LaravelAutoCrud\Traits\TableColumnsTrait;

class RequestBuilder extends BaseBuilder
{
    use TableColumnsTrait;

    protected TableColumnsService $tableColumnsService;
    protected ModelService $modelService;
    public function __construct()
    {
        parent::__construct();
        $this->tableColumnsService = new TableColumnsService;
        $this->modelService = new ModelService;
    }

    public function create(array $modelData, bool $overwrite = false): string
    {
        return $this->fileService->createFromStub($modelData, 'request', 'Http/Requests', 'Request', $overwrite, function ($modelData) {
            return ['{{ data }}' => HelperService::formatArrayToPhpSyntax($this->getRequestData($modelData))];
        });
    }

    public function createForSpatieData(array $modelData, bool $overwrite = false): string
    {
        return $this->fileService->createFromStub($modelData, 'spatie_data_request', 'Http/Requests', 'Request', $overwrite, function ($modelData) {
            $rules = $this->getRequestData($modelData);

            // Add media field validation rules
            $model = $this->getFullModelNamespace($modelData);
            $mediaFields = \Mrmarchone\LaravelAutoCrud\Services\MediaDetector::detectMediaFields($model);

            foreach ($mediaFields as $field) {
                $rules[$field['name']] = $field['validation'];
            }

            return ['{{ data }}' => HelperService::formatArrayToPhpSyntax($rules)];
        });
    }

    private function getRequestData(array $modelData): array
    {
        $columns = $this->getAvailableColumns($modelData);
        $model = $this->getFullModelNamespace($modelData);

        $validationRules = [];

        // Add relationship validation rules
        $relationships = RelationshipDetector::detectRelationships($model);
        $relationshipRules = RelationshipDetector::getRelationshipValidationRules($relationships);

        foreach ($relationshipRules as $field => $rules) {
            // Field names are already in camelCase from getRelationshipValidationRules
            $validationRules[$field] = implode('|', $rules);
        }

        foreach ($columns as $column) {
            if ($column['is_translatable'] ?? false) {
                $rules = ['array'];
            } else {
                $rules = [];
                $columnType = $column['type'];
                $maxLength = $column['max_length'];
                $isUnique = $column['is_unique'];
                $allowedValues = $column['allowed_values'];
                // Handle column types
                switch ($columnType) {
                    case 'string':
                    case 'char':
                    case 'varchar':
                        $rules[] = 'string';
                        if ($maxLength) {
                            $rules[] = 'max:'.$maxLength;
                        }
                        break;

                    case 'integer':
                    case 'int':
                    case 'bigint':
                    case 'smallint':
                    case 'tinyint':
                        $rules[] = 'integer';
                        if (str_contains($columnType, 'unsigned')) {
                            $rules[] = 'min:0';
                        }
                        break;

                    case 'boolean':
                        $rules[] = 'boolean';
                        break;

                    case 'date':
                    case 'datetime':
                    case 'timestamp':
                        $rules[] = 'date';
                        break;

                    case 'text':
                    case 'longtext':
                    case 'mediumtext':
                        $rules[] = 'string';
                        break;

                    case 'decimal':
                    case 'float':
                    case 'double':
                        $rules[] = 'numeric';
                        break;

                    case 'enum':
                        if (! empty($allowedValues)) {
                            $rules[] = 'in:'.implode(',', $allowedValues);
                        }
                        break;

                    case 'json':
                        $rules[] = 'json';
                        break;

                    case 'binary':
                    case 'blob':
                        $rules[] = 'string'; // Handle binary data as string for simplicity
                        break;

                    default:
                        $rules[] = 'string'; // Default fallback
                        break;
                }

                $columnName = $column['name'];

                // Handle unique columns
                if ($isUnique) {
                    $rules[] = 'unique:'.$column['table'].','.$columnName;
                }
            }

            $camelCaseName = Str::camel($column['name']);
            // Add rules to the validation array with camelCase key
            $validationRules[$camelCaseName] = implode('|', $rules);
        }

        return $validationRules;
    }
}
