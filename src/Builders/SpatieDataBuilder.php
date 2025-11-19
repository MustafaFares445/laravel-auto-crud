<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Builders;

use Exception;
use Mrmarchone\LaravelAutoCrud\Services\ModelService;
use Mrmarchone\LaravelAutoCrud\Services\RelationshipDetector;
use Mrmarchone\LaravelAutoCrud\Services\TableColumnsService;
use Mrmarchone\LaravelAutoCrud\Traits\TableColumnsTrait;
use Mrmarchone\LaravelAutoCrud\Transformers\SpatieDataTransformer;

final class SpatieDataBuilder extends BaseBuilder
{
    use TableColumnsTrait;

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

        // Detect relationships and generate Data classes for related models
        $relationships = RelationshipDetector::detectRelationships($model);
        $this->generateRelatedDataClasses($relationships, $overwrite);

        return $this->fileService->createFromStub($modelData, 'spatie_data', 'Data', 'Data', $overwrite, function ($modelData) use ($overwrite, $model) {
            $supportedData = $this->getHelperData($modelData, $overwrite, $model);

            // Add model property - always add this
            $modelClassName = $modelData['modelName'];
            $modelNamespace = $this->getFullModelNamespace($modelData);

            // Build the model property string
            $modelPropertyString = "/** @var class-string<{$modelClassName}> */\n    protected static string \$model = {$modelClassName}::class;";

            // Add namespaces
            $supportedData['namespaces'][] = "use {$modelNamespace};";
            $supportedData['namespaces'][] = 'use App\Traits\HasModelAttributes;';

            // Add media fields if they exist
            $mediaFields = \Mrmarchone\LaravelAutoCrud\Services\MediaDetector::detectMediaFields($model);

            if (! empty($mediaFields)) {
                $supportedData['namespaces'][] = 'use Illuminate\Http\UploadedFile;';
                $supportedData['namespaces'][] = 'use Spatie\LaravelData\Attributes\Validation\File;';

                // Add media properties
                foreach ($mediaFields as $field) {
                    $typeHint = \Mrmarchone\LaravelAutoCrud\Services\MediaDetector::getTypeHint($field['isSingle']);
                    $property = "public {$typeHint} \${$field['name']};";
                    $supportedData['properties'][$property] = '#[File]';
                }
            }

            // Build the complete data section including model property, trait usage, and properties
            $traitUsage = '    use HasModelAttributes;';
            $propertiesString = SpatieDataTransformer::convertDataToString(
                $supportedData['properties'] ?? [],
                ''
            );

            // Combine model property, trait usage, and properties
            // Ensure model property is always included at the beginning
            $dataSection = $modelPropertyString."\n\n".$traitUsage;
            if (! empty($propertiesString)) {
                $dataSection .= "\n\n".$propertiesString;
            }

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

        // Detect and add relationship properties
        if ($modelClass) {
            $relationships = RelationshipDetector::detectRelationships($modelClass);
            $relationshipProperties = RelationshipDetector::getRelationshipProperties($relationships);

            foreach ($relationshipProperties as $relProperty) {
                $property = $relProperty['property'];
                $validation = $relProperty['validation'] ?? '';
                $properties['properties'][$property] = $validation;

                // Add Json validation namespace if needed
                if ($validation === '#[Json]') {
                    $validationNamespaces[] = str_replace('{{ validationNamespace }}', 'Json', $validationNamespace);
                }
            }
        }

        foreach ($columns as $column) {
            $rules = [];
            $isNullable = $column['is_nullable'];
            $columnType = $column['type'];
            $maxLength = $column['max_length'];
            $isUnique = $column['is_unique'];
            $allowedValues = $column['allowed_values'];
            $validation = '#[{{ validation }}]';
            $property = 'public '.($isNullable ? '?' : '').'{{ type }} $'.$column['name'].';';

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
                $columnName = $column['name'];
                $rules[] = "Unique('$table', '$columnName')";
                $validationNamespaces[] = str_replace('{{ validationNamespace }}', 'Unique', $validationNamespace);
            }

            $properties['properties'][$property] = count($rules) ? str_replace('{{ validation }}', implode(', ', $rules), $validation) : '';
        }
        $properties['namespaces'] = array_unique($validationNamespaces);

        return $properties;
    }

    /**
     * Generate Data classes for related models
     *
     * @param  array  $relationships  Array of relationship definitions
     * @param  bool  $overwrite  Whether to overwrite existing files
     */
    private function generateRelatedDataClasses(array $relationships, bool $overwrite): void
    {
        foreach ($relationships as $relationship) {
            $relatedModel = $relationship['related_model'] ?? null;

            if (! $relatedModel || ! class_exists($relatedModel)) {
                continue;
            }

            // Check if already processed
            if (in_array($relatedModel, self::$processedModels, true)) {
                continue;
            }

            // Resolve model data for related model
            $relatedModelData = $this->resolveModelDataFromClass($relatedModel);

            if (! $relatedModelData) {
                continue;
            }

            // Check if Data class already exists
            $dataNamespace = 'App\\Data';
            if ($relatedModelData['folders']) {
                $dataNamespace .= '\\'.str_replace('/', '\\', $relatedModelData['folders']);
            }
            $dataClass = $dataNamespace.'\\'.$relatedModelData['modelName'].'Data';

            if (class_exists($dataClass) && ! $overwrite) {
                continue;
            }

            // Recursively generate Data class for related model
            try {
                $this->create($relatedModelData, $overwrite);
            } catch (Exception $e) {
                // Skip if generation fails
                continue;
            }
        }
    }

    /**
     * Resolve model data array from model class name
     *
     * @param  string  $modelClass  Full class name of the model
     * @return array|null Model data array or null if cannot be resolved
     */
    private function resolveModelDataFromClass(string $modelClass): ?array
    {
        $parts = explode('\\', $modelClass);
        $modelName = end($parts);

        // Remove 'App\Models\' prefix if present
        $namespaceParts = array_slice($parts, 0, -1);
        $namespace = implode('\\', $namespaceParts);

        // Extract folders if model is in a subdirectory
        $folders = null;
        if (str_contains($namespace, 'Models\\')) {
            $modelsIndex = array_search('Models', $namespaceParts);
            if ($modelsIndex !== false && $modelsIndex < count($namespaceParts) - 1) {
                $folderParts = array_slice($namespaceParts, $modelsIndex + 1);
                $folders = implode('/', $folderParts);
            }
        }

        return [
            'modelName' => $modelName,
            'namespace' => $namespace,
            'folders' => $folders,
        ];
    }
}
