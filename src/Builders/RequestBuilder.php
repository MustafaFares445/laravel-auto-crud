<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Builders;

use Illuminate\Support\Str;
use Mrmarchone\LaravelAutoCrud\Services\FileService;
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
    
    /**
     * Initialize the RequestBuilder with required services.
     */
    public function __construct(FileService $fileService, TableColumnsService $tableColumnsService, ModelService $modelService, EnumBuilder $enumBuilder)
    {
        parent::__construct($fileService);
        $this->tableColumnsService = $tableColumnsService;
        $this->modelService = $modelService;
        $this->enumBuilder = $enumBuilder;
    }

    /**
     * Create Store and Update request classes for a model.
     *
     * Generates two separate FormRequest classes:
     * - {Model}StoreRequest: For validating data when creating new records
     * - {Model}UpdateRequest: For validating data when updating existing records
     *
     * @param array $modelData Model information including name, namespace, etc.
     * @param bool $overwrite Whether to overwrite existing request files
     * @return array Returns array with 'store' and 'update' keys containing file paths
     */
    public function create(array $modelData, bool $overwrite = false): array
    {
        $storeRequestPath = $this->createStoreRequest($modelData, $overwrite);
        $updateRequestPath = $this->createUpdateRequest($modelData, $overwrite);
        
        return [
            'store' => $storeRequestPath,
            'update' => $updateRequestPath,
        ];
    }

    /**
     * Create Store and Update request classes for Spatie Data pattern.
     *
     * Similar to create() but includes media field validation rules
     * for models using Spatie Media Library.
     *
     * @param array $modelData Model information including name, namespace, etc.
     * @param bool $overwrite Whether to overwrite existing request files
     * @return array Returns array with 'store' and 'update' keys containing file paths
     */
    public function createForSpatieData(array $modelData, bool $overwrite = false): array
    {
        $storeRequestPath = $this->createSpatieDataStoreRequest($modelData, $overwrite);
        $updateRequestPath = $this->createSpatieDataUpdateRequest($modelData, $overwrite);
        
        return [
            'store' => $storeRequestPath,
            'update' => $updateRequestPath,
        ];
    }

    /**
     * Create a StoreRequest class for standard CRUD operations.
     *
     * @param array $modelData Model information including name, namespace, etc.
     * @param bool $overwrite Whether to overwrite existing request file
     * @return string Path to the created StoreRequest file
     */
    protected function createStoreRequest(array $modelData, bool $overwrite = false): string
    {
        $requestPath = $this->getRequestFolderPath($modelData);
        return $this->fileService->createFromStub($modelData, 'store_request', $requestPath, 'StoreRequest', $overwrite, function ($modelData) {
            $data = $this->getRequestData($modelData, 'store');
            return [
                '{{ data }}' => HelperService::formatArrayToPhpSyntax($data['rules']),
                '{{ useStatements }}' => $this->generateUseStatements($data['useStatements']),
            ];
        });
    }

    /**
     * Create an UpdateRequest class for standard CRUD operations.
     *
     * Update requests use 'sometimes' rule so fields are only validated if present.
     *
     * @param array $modelData Model information including name, namespace, etc.
     * @param bool $overwrite Whether to overwrite existing request file
     * @return string Path to the created UpdateRequest file
     */
    protected function createUpdateRequest(array $modelData, bool $overwrite = false): string
    {
        $requestPath = $this->getRequestFolderPath($modelData);
        return $this->fileService->createFromStub($modelData, 'update_request', $requestPath, 'UpdateRequest', $overwrite, function ($modelData) {
            $data = $this->getRequestData($modelData, 'update');
            return [
                '{{ data }}' => HelperService::formatArrayToPhpSyntax($data['rules']),
                '{{ useStatements }}' => $this->generateUseStatements($data['useStatements']),
            ];
        });
    }

    /**
     * Create a StoreRequest class for Spatie Data pattern with media support.
     *
     * Includes media field validation rules for models using Spatie Media Library.
     *
     * @param array $modelData Model information including name, namespace, etc.
     * @param bool $overwrite Whether to overwrite existing request file
     * @return string Path to the created StoreRequest file
     */
    protected function createSpatieDataStoreRequest(array $modelData, bool $overwrite = false): string
    {
        $requestPath = $this->getRequestFolderPath($modelData);
        return $this->fileService->createFromStub($modelData, 'spatie_data_store_request', $requestPath, 'StoreRequest', $overwrite, function ($modelData) {
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
        $requestPath = $this->getRequestFolderPath($modelData);
        return $this->fileService->createFromStub($modelData, 'spatie_data_update_request', $requestPath, 'UpdateRequest', $overwrite, function ($modelData) {
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
    
    /**
     * Get the folder path for model requests.
     * Groups requests by model name (e.g., Http/Requests/UserRequests).
     *
     * @param array $modelData Model information
     * @return string Request folder path
     */
    private function getRequestFolderPath(array $modelData): string
    {
        $modelName = $modelData['modelName'];
        return 'Http/Requests/' . $modelName . 'Requests';
    }

    private function getRequestData(array $modelData, string $requestType): array
    {
        $columns = $this->getAvailableColumns($modelData);
        $model = $this->getFullModelNamespace($modelData);
        $modelVariable = lcfirst($modelData['modelName']);

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
            $isNullable = $column['isNullable'] ?? false;
            
            if ($column['isTranslatable'] ?? false) {
                $rules = $this->buildTranslatableRules($isNullable, $requestType);
            } else {
                $rules = $this->buildColumnRules($column, $modelData, $isNullable, $requestType, $modelVariable, $useStatements, $needsRule);
            }

            // For update requests, add 'sometimes' at the beginning (only validate if present)
            if ($requestType === 'update' && !empty($rules)) {
                $validationRules[$camelCaseName] = ['sometimes', ...$rules];
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
        $maxLength = $column['maxLength'];
        $isUnique = $column['isUnique'];
        $allowedValues = $column['allowedValues'];
        
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
                    // Prefer enum_class supplied by TableColumnsService
                    $enumClass = $column['enum_class'] ?? $this->getEnumClassForField($modelData, $columnName, $allowedValues);
                    if ($enumClass) {
                        // Ensure we have full namespace (if not, prepend App\Enums\)
                        if (!str_contains($enumClass, '\\')) {
                            $enumClass = "App\\Enums\\{$enumClass}";
                        }
                        
                        // Extract short class name for Rule::enum() call
                        $shortClassName = $this->getShortClassName($enumClass);
                        $rules[] = "Rule::enum({$shortClassName}::class)";
                        // Add full class name to use statements
                        $useStatements[] = "{$enumClass}";
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
        $rules = [];
        
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
        $optimized = [];
        
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
            // Return full namespace
            return "App\\Enums\\{$enumClass}";
        }
        
        // Also check with model prefix (e.g., UserStatus for users.status)
        $modelEnumClass = $modelData['modelName'] . $enumClass;
        $modelEnumPath = app_path("Enums/{$modelEnumClass}.php");
        if (file_exists($modelEnumPath)) {
            // Return full namespace
            return "App\\Enums\\{$modelEnumClass}";
        }
        
        // Also check with Enum suffix
        $enumClassWithSuffix = $enumClass . 'Enum';
        $enumPathWithSuffix = app_path("Enums/{$enumClassWithSuffix}.php");
        if (file_exists($enumPathWithSuffix)) {
            return "App\\Enums\\{$enumClassWithSuffix}";
        }
        
        // Check model prefix with Enum suffix
        $modelEnumClassWithSuffix = $modelData['modelName'] . $enumClass . 'Enum';
        $modelEnumPathWithSuffix = app_path("Enums/{$modelEnumClassWithSuffix}.php");
        if (file_exists($modelEnumPathWithSuffix)) {
            return "App\\Enums\\{$modelEnumClassWithSuffix}";
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

    /**
     * Get short class name from full namespace.
     *
     * @param string $fullClassName
     * @return string
     */
    private function getShortClassName(string $fullClassName): string
    {
        $parts = explode('\\', $fullClassName);
        return end($parts);
    }
}
