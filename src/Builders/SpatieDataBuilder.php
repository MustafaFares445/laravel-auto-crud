<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Builders;

use Illuminate\Support\Str;
use Mrmarchone\LaravelAutoCrud\Services\FileService;
use Mrmarchone\LaravelAutoCrud\Services\ModelService;
use Mrmarchone\LaravelAutoCrud\Services\RelationshipDetector;
use Mrmarchone\LaravelAutoCrud\Services\TableColumnsService;
use Mrmarchone\LaravelAutoCrud\Traits\ModelHelperTrait;
use Mrmarchone\LaravelAutoCrud\Traits\TableColumnsTrait;
use Mrmarchone\LaravelAutoCrud\Transformers\SpatieDataTransformer;

class SpatieDataBuilder
{
    use ModelHelperTrait;
    use TableColumnsTrait;

    protected FileService $fileService;
    protected ModelService $modelService;
    protected TableColumnsService $tableColumnsService;
    private EnumBuilder $enumBuilder;

    private static array $processedModels = [];

    public function __construct(FileService $fileService, EnumBuilder $enumBuilder, ModelService $modelService, TableColumnsService $tableColumnsService)
    {
        $this->fileService = $fileService;
        $this->enumBuilder = $enumBuilder;
        $this->modelService = $modelService;
        $this->tableColumnsService = $tableColumnsService;
    }

    public function create(array $modelData, bool $overwrite = false): string
    {
        $model = $this->getFullModelNamespace($modelData);
        $modelKey = $model;

        // Track processed models to avoid infinite loops
        if (in_array($modelKey, self::$processedModels, true)) {
            // Return existing Data class namespace if already processed
            $namespace = 'App\\Data';
            if ($modelData['folders']) {
                $namespace .= '\\'.str_replace('/', '\\', $modelData['folders']);
            }
            return $namespace.'\\'.$modelData['modelName'].'Data';
        }

        self::$processedModels[] = $modelKey;

        return $this->fileService->createFromStub($modelData, 'spatie_data', 'Data', 'Data', $overwrite, function ($modelData) use ($overwrite, $model) {
            $supportedData = $this->getHelperData($modelData, $overwrite, $model);

            // Add model property - always add this
            $modelClassName = $modelData['modelName'];
            $modelNamespace = $this->getFullModelNamespace($modelData);

            // Add namespaces
            $supportedData['namespaces'][] = "use {$modelNamespace};";

            // Build the model property string
            $modelPropertyString = "/** @var class-string<{$modelClassName}> */\n    protected static string \$model = {$modelClassName}::class;";

            // Add media fields if they exist
            $mediaFields = \Mrmarchone\LaravelAutoCrud\Services\MediaDetector::detectMediaFields($model);

            if (!empty($mediaFields)) {
                $supportedData['namespaces'][] = 'use Illuminate\Http\UploadedFile;';
                $supportedData['namespaces'][] = 'use Spatie\LaravelData\Attributes\Validation\File;';

                // Add media properties (make them nullable for optional updates)
                foreach ($mediaFields as $field) {
                    $typeHint = \Mrmarchone\LaravelAutoCrud\Services\MediaDetector::getTypeHint($field['isSingle']);
                    $propertyName = Str::camel($field['name']);
                    $property = "public ?{$typeHint} \${$propertyName};";
                    $supportedData['properties'][$property] = '#[File]';
                }
            }

            // Add relationship properties
            $relationships = RelationshipDetector::detectRelationships($model);
            $relationshipProperties = RelationshipDetector::getRelationshipProperties($relationships);

            foreach ($relationshipProperties as $relProperty) {
                $property = $relProperty['property'];
                $validation = $relProperty['validation'];
                $relatedModel = $relProperty['related_model'] ?? null;
                $foreignKey = $relProperty['foreign_key'] ?? null;
                $propertyName = $this->getPropertyName($property);

                // Skip if a property with the same signature was already added from table columns
                if (isset($supportedData['properties'][$property])) {
                    continue;
                }

                // Skip if the property name already exists with a different signature (prevents duplicate params)
                if ($propertyName && $this->propertyExistsByName($supportedData['properties'], $propertyName)) {
                    continue;
                }

                // Add Exists validation for belongsTo relationships
                // Check if it's an integer property (belongsTo) and has a related model
                if ($relatedModel && str_contains($property, 'public ?int $')) {
                    $tableName = $this->getTableNameFromModel($relatedModel);
                    if ($tableName) {
                        // Exists validation checks if the value exists in the related table's id column
                        $validation = "#[Exists('{$tableName}', 'id')]";
                        $existsNamespace = 'use Spatie\LaravelData\Attributes\Validation\Exists;';
                        if (!in_array($existsNamespace, $supportedData['namespaces'], true)) {
                            $supportedData['namespaces'][] = $existsNamespace;
                        }
                    }
                }

                // Add Json validation namespace if needed
                if (str_contains($validation, 'Json')) {
                    $jsonNamespace = 'use Spatie\LaravelData\Attributes\Validation\Json;';
                    if (!in_array($jsonNamespace, $supportedData['namespaces'], true)) {
                        $supportedData['namespaces'][] = $jsonNamespace;
                    }
                }

                $supportedData['properties'][$property] = $validation;
            }

            // Ensure namespaces are unique
            $supportedData['namespaces'] = array_unique($supportedData['namespaces']);

            // Build constructor with properties
            $constructorProperties = [];
            foreach ($supportedData['properties'] ?? [] as $property => $validation) {
                // Remove semicolon from property if present (for constructor parameters)
                $propertyWithoutSemicolon = rtrim($property, ';');
                $constructorProperties[] = ($validation ? "        $validation\n        " : "        ") . $propertyWithoutSemicolon;
            }

            $constructor = "    public function __construct(\n";
            $constructor .= implode(",\n", $constructorProperties);
            $constructor .= "\n    ) {}";

            // Generate syncRelationships method for belongsToMany relationships
            $syncRelationshipsMethod = $this->generateSyncRelationshipsMethod($relationships, $modelData);

            // Combine model property, constructor, and syncRelationships method
            $dataSection = $modelPropertyString . "\n\n" . $constructor;
            if ($syncRelationshipsMethod) {
                $dataSection .= "\n\n" . $syncRelationshipsMethod;
            }

            return [
                '{{ namespaces }}' => SpatieDataTransformer::convertNamespacesToString($supportedData['namespaces']),
                '{{ data }}' => $dataSection,
            ];
        });
    }

    /**
     * Check if a property with the same name already exists, regardless of signature.
     *
     * @param array<string, string> $properties
     */
    private function propertyExistsByName(array $properties, string $propertyName): bool
    {
        foreach (array_keys($properties) as $propertyDefinition) {
            if ($this->getPropertyName($propertyDefinition) === $propertyName) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract the property name from a property definition like "public ?int $foo;"
     */
    private function getPropertyName(string $propertyDefinition): ?string
    {
        if (preg_match('/\$(\w+)/', $propertyDefinition, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function getHelperData(array $modelData, $overwrite = false, ?string $modelClass = null): array
    {
        $columns = $this->getAvailableColumns($modelData);
        
        // Get hidden properties and filter them out
        if ($modelClass) {
            $hiddenProperties = $this->getHiddenProperties($modelClass);
            $columns = array_filter($columns, function($column) use ($hiddenProperties) {
                return !in_array($column['name'], $hiddenProperties, true);
            });
        }
        
        $properties = [];
        $validationNamespaces = [];
        $validationNamespace = 'use Spatie\LaravelData\Attributes\Validation\{{ validationNamespace }};';

        foreach ($columns as $column) {
            $columnName = $column['name'];
            $propertyName = Str::camel($columnName);
            // Skip model_id and model_type columns (polymorphic relationship columns)
            if (in_array($columnName, ['model_id', 'model_type'], true)) {
                continue;
            }

            $rules = [];
            $isNullable = $column['isNullable'];
            $columnType = $column['type'];
            $maxLength = $column['maxLength'];
            $isUnique = $column['isUnique'];
            $allowedValues = $column['allowedValues'];
            $validation = '#[{{ validation }}]';
            // Make all properties nullable to support optional values in update endpoints
            $property = 'public ?{{ type }} $'.$propertyName.';';

            // Handle column types
            switch ($columnType) {
                case 'string':
                case 'char':
                case 'varchar':
                case 'text':
                case 'longtext':
                case 'mediumtext':
                case 'binary':
                case 'blob':
                    $property = str_replace('{{ type }}', 'string', $property);
                    if ($maxLength) {
                        $rules[] = 'Max('.$maxLength.')';
                        $validationNamespaces[] = str_replace('{{ validationNamespace }}', 'Max', $validationNamespace);
                    }
                    break;

                case 'integer':
                case 'int':
                case 'bigint':
                case 'smallint':
                case 'tinyint':
                    $property = str_replace('{{ type }}', 'int', $property);
                    if (str_contains($columnType, 'unsigned')) {
                        $rules[] = 'Min(0)';
                        $validationNamespaces[] = str_replace('{{ validationNamespace }}', 'Min', $validationNamespace);
                    }
                    break;

                case 'boolean':
                    $property = str_replace('{{ type }}', 'bool', $property);
                    break;

                case 'date':
                case 'datetime':
                case 'timestamp':
                    $property = str_replace('{{ type }}', 'Carbon', $property);
                    $rules[] = 'Date';
                    $validationNamespaces[] = str_replace('{{ validationNamespace }}', 'Date', $validationNamespace);
                    $validationNamespaces[] = 'use Carbon\Carbon;';
                    break;

                case 'decimal':
                case 'float':
                case 'double':
                    $property = str_replace('{{ type }}', 'int', $property);
                    $rules[] = 'Numeric';
                    $validationNamespaces[] = str_replace('{{ validationNamespace }}', 'Numeric', $validationNamespace);
                    break;

                case 'enum':
                    if (! empty($allowedValues)) {
                        $enum = $this->enumBuilder->create($modelData, $allowedValues, $columnName, $overwrite);
                        $enumClass = explode('\\', $enum);
                        $enumClass = end($enumClass);
                        $rules[] = "Enum($enumClass::class)";
                        $property = str_replace('{{ type }}', $enumClass, $property);
                        $validationNamespaces[] = str_replace('{{ validationNamespace }}', 'Enum', $validationNamespace);
                        $validationNamespaces[] = 'use '.$enum.';';
                    }
                    break;

                case 'json':
                    $property = str_replace('{{ type }}', 'array', $property);
                    $rules[] = 'Json';
                    $validationNamespaces[] = str_replace('{{ validationNamespace }}', 'Json', $validationNamespace);
                    break;

                default:
                    $property = str_replace('{{ type }}', 'string', $property);
                    break;
            }

            // Handle unique columns
            if ($isUnique) {
                $table = $column['table'];
                $rules[] = "Unique('$table', '$columnName')";
                $validationNamespaces[] = str_replace('{{ validationNamespace }}', 'Unique', $validationNamespace);
            }

            $properties['properties'][$property] = count($rules) ? str_replace('{{ validation }}', implode(', ', $rules), $validation) : '';
        }
        $properties['namespaces'] = array_unique($validationNamespaces);

        return $properties;
    }

    /**
     * Generate syncRelationships method for belongsToMany relationships.
     *
     * @param array $relationships Array of relationship definitions
     * @param array $modelData Model information
     * @return string|null Generated method code or null if no belongsToMany relationships
     */
    private function generateSyncRelationshipsMethod(array $relationships, array $modelData): ?string
    {
        $belongsToManyRelations = array_filter($relationships, fn($rel) => $rel['type'] === 'belongsToMany');
        
        if (empty($belongsToManyRelations)) {
            return null;
        }

        $modelName = $modelData['modelName'];
        $syncCalls = [];

        foreach ($belongsToManyRelations as $relationship) {
            $relationshipName = $relationship['name'];
            $relatedModel = $relationship['related_model'] ?? null;
            
            if ($relatedModel) {
                $relatedModelName = RelationshipDetector::getModelNameFromClass($relatedModel);
                $propertyName = Str::camel(strtolower($relatedModelName) . '_ids');
                $modelVariable = lcfirst($modelName);
                $syncCalls[] = "        \$this->syncIfSet(\${$modelVariable}, '{$relationshipName}', \$this->{$propertyName});";
            }
        }

        if (empty($syncCalls)) {
            return null;
        }

        $modelVariable = lcfirst($modelName);
        $method = "    public function syncRelationships({$modelName} \${$modelVariable}): void\n    {\n";
        $method .= implode("\n", $syncCalls);
        $method .= "\n    }";

        return $method;
    }

    /**
     * Get table name from model class
     *
     * @param string $modelClass Full class name of the model
     * @return string Table name
     */
    private function getTableNameFromModel(string $modelClass): string
    {
        if (!class_exists($modelClass)) {
            return '';
        }

        try {
            $modelInstance = new $modelClass();
            if ($modelInstance instanceof \Illuminate\Database\Eloquent\Model) {
                return $modelInstance->getTable();
            }
        } catch (\Exception $e) {
            // If we can't instantiate, try to infer from class name
            $parts = explode('\\', $modelClass);
            $modelName = end($parts);
            return Str::snake(Str::plural($modelName));
        }

        return '';
    }
}
