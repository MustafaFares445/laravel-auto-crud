<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Builders;

use Illuminate\Support\Str;
use Mrmarchone\LaravelAutoCrud\Services\ModelService;
use Mrmarchone\LaravelAutoCrud\Services\RelationshipDetector;
use Mrmarchone\LaravelAutoCrud\Services\TableColumnsService;
use Mrmarchone\LaravelAutoCrud\Traits\TableColumnsTrait;
use Mrmarchone\LaravelAutoCrud\Transformers\SpatieDataTransformer;

class SpatieDataBuilder extends BaseBuilder
{
    use TableColumnsTrait;

    protected ModelService $modelService;
    protected TableColumnsService $tableColumnsService;
    private EnumBuilder $enumBuilder;

    /**
     * Track processed models to avoid infinite loops in recursive generation
     *
     * @var array<string>
     */
    private static array $processedModels = [];

    public function __construct()
    {
        parent::__construct();
        $this->enumBuilder = new EnumBuilder;
        $this->modelService = new ModelService;
        $this->tableColumnsService = new TableColumnsService;
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

                // Add media properties
                foreach ($mediaFields as $field) {
                    $typeHint = \Mrmarchone\LaravelAutoCrud\Services\MediaDetector::getTypeHint($field['isSingle']);
                    $propertyName = Str::camel($field['name']);
                    $property = "public {$typeHint} \${$propertyName};";
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

                // Add Exists validation for belongsTo relationships
                if ($relatedModel && str_contains($property, 'public ?int $') && str_contains($property, '_id')) {
                    $tableName = $this->getTableNameFromModel($relatedModel);
                    if ($tableName) {
                        $validation = "#[Exists('{$tableName}', 'id')]";
                        $supportedData['namespaces'][] = 'use Spatie\LaravelData\Attributes\Validation\Exists;';
                    }
                }

                // Add Json validation namespace if needed
                if (str_contains($validation, 'Json')) {
                    $supportedData['namespaces'][] = 'use Spatie\LaravelData\Attributes\Validation\Json;';
                }

                $supportedData['properties'][$property] = $validation;
            }

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

            // Combine model property and constructor
            $dataSection = $modelPropertyString . "\n\n" . $constructor;

            return [
                '{{ namespaces }}' => SpatieDataTransformer::convertNamespacesToString($supportedData['namespaces']),
                '{{ data }}' => $dataSection,
            ];
        });
    }

    private function getHelperData(array $modelData, $overwrite = false, ?string $modelClass = null): array
    {
        $columns = $this->getAvailableColumns($modelData);
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
            $isNullable = $column['is_nullable'];
            $columnType = $column['type'];
            $maxLength = $column['max_length'];
            $isUnique = $column['is_unique'];
            $allowedValues = $column['allowed_values'];
            $validation = '#[{{ validation }}]';
            $property = 'public '.($isNullable ? '?' : '').'{{ type }} $'.$propertyName.';';

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
                        $enum = $this->enumBuilder->create($modelData, $allowedValues, $overwrite);
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
