<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Builders;

use Illuminate\Support\Str;
use Mrmarchone\LaravelAutoCrud\Services\FileService;
use Mrmarchone\LaravelAutoCrud\Services\HelperService;
use Mrmarchone\LaravelAutoCrud\Services\ModelService;
use Mrmarchone\LaravelAutoCrud\Services\RelationshipDetector;
use Mrmarchone\LaravelAutoCrud\Services\TableColumnsService;
use Mrmarchone\LaravelAutoCrud\Traits\ModelHelperTrait;
use Mrmarchone\LaravelAutoCrud\Traits\TableColumnsTrait;

class ResourceBuilder
{
    use ModelHelperTrait;
    use TableColumnsTrait;

    protected FileService $fileService;
    protected ModelService $modelService;
    protected TableColumnsService $tableColumnsService;

    public function __construct(FileService $fileService, ModelService $modelService, TableColumnsService $tableColumnsService)
    {
        $this->fileService = $fileService;
        $this->modelService = $modelService;
        $this->tableColumnsService = $tableColumnsService;
    }

    public function create(array $modelData, bool $overwrite = false, string $pattern = 'normal', ?string $spatieData = null): string
    {
        return $this->fileService->createFromStub($modelData, 'resource', 'Http/Resources', 'Resource', $overwrite, function ($modelData) use ($pattern, $spatieData) {
            $model = $this->getFullModelNamespace($modelData);
            $resourcesData = $this->getResourcesData($modelData, $model, $pattern, $spatieData);
            
            // Check if selective-response package is installed
            $useSelectiveResponse = $this->isSelectiveResponseInstalled();
            $baseResourceClass = $useSelectiveResponse ? 'BaseApiResource' : 'JsonResource';
            $baseResourceImport = $useSelectiveResponse 
                ? "use MustafaFares\SelectiveResponse\Http\Resources\BaseApiResource;"
                : "use Illuminate\Http\Resources\Json\JsonResource;";
            
            // Ensure baseResourceImport has a newline if mediaResourceImport is empty
            if (empty($resourcesData['mediaResourceImport'])) {
                $baseResourceImport .= "\n";
            }

            return [
                '{{ modelNamespace }}' => $model,
                '{{ model }}' => $modelData['modelName'],
                '{{ data }}' => HelperService::formatArrayToPhpSyntax($resourcesData['data'], true),
                '{{ mediaResourceImport }}' => $resourcesData['mediaResourceImport'],
                '{{ baseResourceImport }}' => $baseResourceImport,
                '{{ baseResourceClass }}' => $baseResourceClass,
            ];
        });
    }
    
    /**
     * Check if the selective-response package is installed.
     *
     * @return bool
     */
    private function isSelectiveResponseInstalled(): bool
    {
        return class_exists(\MustafaFares\SelectiveResponse\Http\Resources\BaseApiResource::class);
    }

    private function getResourcesData(array $modelData, string $model, string $pattern = 'normal', ?string $spatieData = null): array
    {
        $data = [];
        $mediaResourceImport = '';

        // Always include id
        $data['id'] = '$this->id';

        // Get hidden properties from model
        $hiddenProperties = $this->getHiddenProperties($model);

        // Detect media fields once (used later)
        $mediaFields = \Mrmarchone\LaravelAutoCrud\Services\MediaDetector::detectMediaFields($model);

        // Get media field names for filtering
        $mediaFieldNames = [];
        foreach ($mediaFields as $field) {
            $mediaFieldNames[] = Str::camel($field['name']);
        }

        // Get database columns (needed for translatable info even if using spatie-data)
        $columns = $this->getAvailableColumns($modelData);
        $columnsByName = [];
        foreach ($columns as $column) {
            $columnsByName[$column['name']] = $column;
        }

        // If using spatie-data pattern, read properties from Data class
        if ($pattern === 'spatie-data' && !empty($spatieData)) {
            $properties = $this->getPropertiesFromDataClass($spatieData);

            // Add properties from Data class
            foreach ($properties as $propertyName) {
                // Skip id, created_at, updated_at as they're handled separately
                if (in_array($propertyName, ['id', 'created_at', 'updated_at'])) {
                    continue;
                }

                // Skip media fields as they're handled separately
                if (in_array($propertyName, $mediaFieldNames, true)) {
                    continue;
                }

                // Skip hidden properties
                $snakeCaseName = Str::snake($propertyName);
                if (in_array($snakeCaseName, $hiddenProperties, true)) {
                    continue;
                }

                $camelCaseName = Str::camel($propertyName);
                
                $data[$camelCaseName] = '$this->'.$snakeCaseName;
            }
        } else {
            // Use database columns (original behavior)
            // Add regular columns (excluding id, created_at, updated_at)
            foreach ($columns as $column) {
                $columnName = $column['name'];

                // Skip id, created_at, updated_at as they're handled separately
                if (in_array($columnName, ['id', 'created_at', 'updated_at'])) {
                    continue;
                }

                // Skip hidden properties
                if (in_array($columnName, $hiddenProperties, true)) {
                    continue;
                }

                $camelCaseName = Str::camel($columnName);
                $data[$camelCaseName] = '$this->'.$columnName;
            }
        }

        // Add media fields
        if (!empty($mediaFields)) {
            $mediaResourceImport = "\nuse App\Http\Resources\MediaResource;";

            foreach ($mediaFields as $field) {
                $fieldName = $field['name'];
                $collection = $field['collectionName'];
                $camelCaseName = Str::camel($fieldName);

                if ($field['isSingle']) {
                    // Single file: MediaResource::make($this->whenLoaded('media', fn() => $this->getFirstMedia('collection')))
                    $data[$camelCaseName] = "MediaResource::make(\$this->whenLoaded('media', fn() => \$this->getFirstMedia('{$collection}')))";
                } else {
                    // Multiple files: MediaResource::collection($this->whenLoaded('media', fn() => $this->getMedia('collection')))
                    $data[$camelCaseName] = "MediaResource::collection(\$this->whenLoaded('media', fn() => \$this->getMedia('{$collection}')))";
                }
            }
        }

        // Add relationship data
        $relationships = RelationshipDetector::detectRelationships($model);
        foreach ($relationships as $relationship) {
            $relationshipName = $relationship['name'];
            $relationshipType = $relationship['type'];
            $camelCaseName = Str::camel($relationshipName);

            switch ($relationshipType) {
                case 'belongsTo':
                    // For belongsTo, include the related model when loaded
                    $relatedModel = $relationship['related_model'] ?? null;
                    if ($relatedModel) {
                        $relatedModelName = RelationshipDetector::getModelNameFromClass($relatedModel);
                        $relatedResourceName = $relatedModelName . 'Resource';
                        $data[$camelCaseName] = "new {$relatedResourceName}(\$this->whenLoaded('{$relationshipName}'))";
                    }
                    break;

                case 'hasOne':
                    // For hasOne, include single resource when loaded
                    $relatedModel = $relationship['related_model'] ?? null;
                    if ($relatedModel) {
                        $relatedModelName = RelationshipDetector::getModelNameFromClass($relatedModel);
                        $relatedResourceName = $relatedModelName . 'Resource';
                        $data[$camelCaseName] = "new {$relatedResourceName}(\$this->whenLoaded('{$relationshipName}'))";
                    }
                    break;

                case 'hasMany':
                case 'belongsToMany':
                case 'morphMany':
                case 'morphToMany':
                    // For collections, use Resource::collection()
                    $relatedModel = $relationship['related_model'] ?? null;
                    if ($relatedModel) {
                        $relatedModelName = RelationshipDetector::getModelNameFromClass($relatedModel);
                        $relatedResourceName = $relatedModelName . 'Resource';
                        $data[$camelCaseName] = "{$relatedResourceName}::collection(\$this->whenLoaded('{$relationshipName}'))";
                    }
                    break;

                case 'morphTo':
                    // For morphTo, include polymorphic relationship when loaded
                    $data[$camelCaseName] = "\$this->whenLoaded('{$relationshipName}')";
                    break;
            }
        }

        // Add timestamps at the end (only if not hidden)
        if (!in_array('created_at', $hiddenProperties, true)) {
            $data['createdAt'] = '$this->created_at->toDateTimeString()';
        }
        if (!in_array('updated_at', $hiddenProperties, true)) {
            $data['updatedAt'] = '$this->updated_at->toDateTimeString()';
        }

        return [
            'data' => $data,
            'mediaResourceImport' => $mediaResourceImport,
        ];
    }

    /**
     * Get property names from Spatie Data class using reflection
     *
     * @return array<string>
     */
    private function getPropertiesFromDataClass(string $dataClass): array
    {
        $properties = [];

        try {
            $reflection = new \ReflectionClass($dataClass);
            $constructor = $reflection->getConstructor();

            if ($constructor) {
                $parameters = $constructor->getParameters();

                foreach ($parameters as $parameter) {
                    $propertyName = $parameter->getName();
                    $properties[] = $propertyName;
                }
            }
        } catch (\ReflectionException $e) {
            // If reflection fails, return empty array
            return [];
        }

        return $properties;
    }

}
