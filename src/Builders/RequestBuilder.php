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
    protected EnumBuilder $enumBuilder;
    
    public function __construct()
    {
        parent::__construct();
        $this->tableColumnsService = new TableColumnsService;
        $this->modelService = new ModelService;
        $this->enumBuilder = new EnumBuilder;
    }

    public function create(array $modelData, bool $overwrite = false): array
    {
        $storeRequestPath = $this->createStoreRequest($modelData, $overwrite);
        $updateRequestPath = $this->createUpdateRequest($modelData, $overwrite);
        
        return [
            'store' => $storeRequestPath,
            'update' => $updateRequestPath,
        ];
    }

    public function createForSpatieData(array $modelData, bool $overwrite = false): array
    {
        $storeRequestPath = $this->createSpatieDataStoreRequest($modelData, $overwrite);
        $updateRequestPath = $this->createSpatieDataUpdateRequest($modelData, $overwrite);
        
        return [
            'store' => $storeRequestPath,
            'update' => $updateRequestPath,
        ];
    }

    protected function createStoreRequest(array $modelData, bool $overwrite = false): string
    {
        return $this->fileService->createFromStub($modelData, 'store_request', 'Http/Requests', 'StoreRequest', $overwrite, function ($modelData) {
            $data = $this->getRequestData($modelData, 'store');
            return [
                '{{ data }}' => HelperService::formatArrayToPhpSyntax($data['rules']),
                '{{ useStatements }}' => $this->generateUseStatements($data['useStatements']),
            ];
        });
    }

    protected function createUpdateRequest(array $modelData, bool $overwrite = false): string
    {
        return $this->fileService->createFromStub($modelData, 'update_request', 'Http/Requests', 'UpdateRequest', $overwrite, function ($modelData) {
            $data = $this->getRequestData($modelData, 'update');
            return [
                '{{ data }}' => HelperService::formatArrayToPhpSyntax($data['rules']),
                '{{ useStatements }}' => $this->generateUseStatements($data['useStatements']),
            ];
        });
    }

    protected function createSpatieDataStoreRequest(array $modelData, bool $overwrite = false): string
    {
        return $this->fileService->createFromStub($modelData, 'spatie_data_store_request', 'Http/Requests', 'StoreRequest', $overwrite, function ($modelData) {
            $data = $this->getRequestData($modelData, 'store');
            
            // Add media field validation rules
            $model = $this->getFullModelNamespace($modelData);
            $mediaFields = \Mrmarchone\LaravelAutoCrud\Services\MediaDetector::detectMediaFields($model);

            foreach ($mediaFields as $field) {
                $data['rules'][$field['name']] = $this->convertValidationToArray($field['validation']);
            }

            return [
                '{{ data }}' => HelperService::formatArrayToPhpSyntax($data['rules']),
                '{{ useStatements }}' => $this->generateUseStatements($data['useStatements']),
            ];
        });
    }

    protected function createSpatieDataUpdateRequest(array $modelData, bool $overwrite = false): string
    {
        return $this->fileService->createFromStub($modelData, 'spatie_data_update_request', 'Http/Requests', 'UpdateRequest', $overwrite, function ($modelData) {
            $data = $this->getRequestData($modelData, 'update');
            
            // Add media field validation rules
            $model = $this->getFullModelNamespace($modelData);
            $mediaFields = \Mrmarchone\LaravelAutoCrud\Services\MediaDetector::detectMediaFields($model);

            foreach ($mediaFields as $field) {
                $data['rules'][$field['name']] = $this->convertValidationToArray($field['validation']);
            }

            return [
                '{{ data }}' => HelperService::formatArrayToPhpSyntax($data['rules']),
                '{{ useStatements }}' => $this->generateUseStatements($data['useStatements']),
            ];
        });
    }

    private function getRequestData(array $modelData, string $requestType): array
    {
        $columns = $this->getAvailableColumns($modelData);
        $model = $this->getFullModelNamespace($modelData);
        $modelVariable = lcfirst($modelData['model']);

        $validationRules = [];
        $useStatements = [];
        $needsRule = false;

        // Add relationship validation rules
        $relationships = RelationshipDetector::detectRelationships($model);
        $relationshipRules = RelationshipDetector::getRelationshipValidationRules($relationships);

        foreach ($relationshipRules as $field => $rules) {
            // Skip model_id and model_type fields for media relationships
            if (in_array($field, ['modelId', 'modelType'], true)) {
                continue;
            }
            
            // Optimize relationship rules
            $optimizedRules = $this->optimizeRules($rules, $requestType);
            $validationRules[$field] = $optimizedRules;
        }

        foreach ($columns as $column) {
            $columnName = $column['name'];
            
            // Skip model_id and model_type columns
            if (in_array($columnName, ['model_id', 'model_type'], true)) {
                continue;
            }
            
            $camelCaseName = Str::camel($columnName);
            $isNullable = $column['is_nullable'] ?? false;
            
            if ($column['is_translatable'] ?? false) {
                $rules = $this->buildTranslatableRules($isNullable, $requestType);
            } else {
                $rules = $this->buildColumnRules($column, $modelData, $isNullable, $requestType, $modelVariable, $useStatements, $needsRule);
            }

            // For update requests, add 'sometimes' at the beginning (only validate if present)
            if ($requestType === 'update' && !empty($rules)) {
                // Ensure 'sometimes' comes before 'bail' for proper validation flow
                $rulesWithoutBail = array_filter($rules, fn($rule) => $rule !== 'bail');
                $validationRules[$camelCaseName] = ['sometimes', 'bail', ...$rulesWithoutBail];
            } else {
                $validationRules[$camelCaseName] = $rules;
            }
        }

        if ($needsRule) {
            $useStatements[] = 'Illuminate\Validation\Rule';
        }

        return [
            'rules' => $validationRules,
            'useStatements' => array_unique($useStatements),
        ];
    }

    private function buildColumnRules(array $column, array $modelData, bool $isNullable, string $requestType, string $modelVariable, array &$useStatements, bool &$needsRule): array
    {
        $rules = [];
        $columnType = $column['type'];
        $columnName = $column['name'];
        $maxLength = $column['max_length'];
        $isUnique = $column['is_unique'];
        $allowedValues = $column['allowed_values'];
        
        // Add 'bail' as first rule for better performance (stops on first failure)
        $rules[] = 'bail';
        
        // Handle nullable columns - allow null for both store and update
        if ($isNullable) {
            $rules[] = 'nullable';
        }
        
        // Detect special field types for better validation
        $fieldType = $this->detectFieldType($columnName);
        
        // Handle column types with enhanced security
        switch ($columnType) {
            case 'string':
            case 'char':
            case 'varchar':
                if ($fieldType === 'email') {
                    $rules[] = 'email:rfc,dns';
                    if ($maxLength) {
                        $rules[] = 'max:'.$maxLength;
                    }
                } elseif ($fieldType === 'url') {
                    $rules[] = 'url';
                    if ($maxLength) {
                        $rules[] = 'max:'.$maxLength;
                    }
                } elseif ($fieldType === 'password') {
                    $rules[] = 'string';
                    $rules[] = 'min:8';
                    if ($maxLength) {
                        $rules[] = 'max:'.$maxLength;
                    }
                } else {
                    $rules[] = 'string';
                    if ($maxLength) {
                        $rules[] = 'max:'.$maxLength;
                    }
                }
                break;

            case 'integer':
            case 'int':
            case 'bigint':
            case 'smallint':
            case 'tinyint':
                $rules[] = 'integer';
                
                // Set min/max based on integer type
                if (str_contains($columnType, 'unsigned')) {
                    $rules[] = 'min:0';
                } else {
                    $rules[] = 'min:-2147483648';
                }
                
                // Prevent integer overflow
                if ($columnType === 'bigint') {
                    $rules[] = 'max:9223372036854775807';
                } elseif ($columnType === 'int' || $columnType === 'integer') {
                    $rules[] = 'max:2147483647';
                } elseif ($columnType === 'smallint') {
                    $rules[] = 'max:32767';
                } elseif ($columnType === 'tinyint') {
                    $rules[] = 'max:255';
                }
                break;

            case 'boolean':
                $rules[] = 'boolean';
                break;

            case 'date':
                $rules[] = 'date';
                $rules[] = 'date_format:Y-m-d';
                break;

            case 'datetime':
            case 'timestamp':
                $rules[] = 'date';
                $rules[] = 'date_format:Y-m-d H:i:s';
                break;

            case 'time':
                $rules[] = 'date_format:H:i:s';
                break;

            case 'text':
            case 'longtext':
            case 'mediumtext':
                $rules[] = 'string';
                // Add max length for text fields to prevent DoS
                if ($columnType === 'text') {
                    $rules[] = 'max:65535';
                } elseif ($columnType === 'mediumtext') {
                    $rules[] = 'max:16777215';
                }
                break;

            case 'decimal':
            case 'float':
            case 'double':
                $rules[] = 'numeric';
                // Add reasonable bounds for decimal types to prevent overflow
                if ($columnType === 'decimal') {
                    $rules[] = 'between:-999999999.99,999999999.99';
                }
                break;

            case 'enum':
                if (! empty($allowedValues)) {
                    // Check if an Enum class exists for this field
                    $enumClass = $this->getEnumClassForField($modelData, $columnName, $allowedValues);
                    if ($enumClass) {
                        $rules[] = "Rule::enum({$enumClass}::class)";
                        $useStatements[] = "App\\Enums\\{$enumClass}";
                        $needsRule = true;
                    } else {
                        // Fall back to in: validation with strict checking
                        $rules[] = 'in:'.implode(',', array_map(fn($v) => addslashes($v), $allowedValues));
                    }
                }
                break;

            case 'json':
                // Validate as JSON string first, then ensure it's a valid structure
                $rules[] = 'json';
                // JSON can be object or array, so we validate it's valid JSON format
                break;

            case 'binary':
            case 'blob':
                $rules[] = 'string';
                // Limit binary data size for security
                $rules[] = 'max:10485760'; // 10MB max
                break;

            default:
                $rules[] = 'string';
                if ($maxLength) {
                    $rules[] = 'max:'.$maxLength;
                }
                break;
        }

        // Handle unique columns with proper update handling
        if ($isUnique) {
            if ($requestType === 'update') {
                $rules[] = "Rule::unique('{$column['table']}', '{$columnName}')->ignore(\$this->route('{$modelVariable}'))";
                $needsRule = true;
            } else {
                $rules[] = "Rule::unique('{$column['table']}', '{$columnName}')";
                $needsRule = true;
            }
        }

        return $rules;
    }

    private function buildTranslatableRules(bool $isNullable, string $requestType): array
    {
        $rules = ['bail'];
        
        if ($isNullable && $requestType === 'store') {
            $rules[] = 'nullable';
        }
        
        $rules[] = 'array';
        
        return $rules;
    }

    private function detectFieldType(string $columnName): ?string
    {
        $columnNameLower = strtolower($columnName);
        
        // Email detection
        if (in_array($columnNameLower, ['email', 'email_address', 'e_mail', 'user_email'], true)) {
            return 'email';
        }
        
        // URL detection
        if (in_array($columnNameLower, ['url', 'website', 'link', 'uri', 'homepage', 'web_url'], true)) {
            return 'url';
        }
        
        // Password detection
        if (in_array($columnNameLower, ['password', 'password_hash', 'pass', 'pwd'], true)) {
            return 'password';
        }
        
        return null;
    }

    private function optimizeRules(array $rules, string $requestType): array
    {
        $optimized = ['bail'];
        
        // Add 'sometimes' for update requests
        if ($requestType === 'update') {
            $optimized[] = 'sometimes';
        }
        
        // Merge existing rules
        return array_merge($optimized, $rules);
    }

    private function getEnumClassForField(array $modelData, string $columnName, array $allowedValues): ?string
    {
        // Generate the expected enum class name based on the column name
        $enumClass = Str::studly($columnName);
        
        // Check if the enum class file exists
        $enumPath = app_path("Enums/{$enumClass}.php");
        if (file_exists($enumPath)) {
            return $enumClass;
        }
        
        // Also check with model prefix (e.g., UserStatus for users.status)
        $modelEnumClass = $modelData['model'] . $enumClass;
        $modelEnumPath = app_path("Enums/{$modelEnumClass}.php");
        if (file_exists($modelEnumPath)) {
            return $modelEnumClass;
        }
        
        return null;
    }

    private function generateUseStatements(array $useStatements): string
    {
        if (empty($useStatements)) {
            return '';
        }
        
        $statements = array_map(fn($statement) => "use {$statement};", $useStatements);
        return implode("\n", $statements);
    }

    private function convertValidationToArray(string $validation): array
    {
        // Convert pipe-separated validation string to array
        if (empty($validation)) {
            return [];
        }
        
        return explode('|', $validation);
    }
}
