<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Builders;

use Illuminate\Support\Str;
use Mrmarchone\LaravelAutoCrud\Services\HelperService;
use Mrmarchone\LaravelAutoCrud\Services\ModelService;
use Mrmarchone\LaravelAutoCrud\Services\TableColumnsService;
use Mrmarchone\LaravelAutoCrud\Traits\TableColumnsTrait;

class ResourceBuilder extends BaseBuilder
{
    use TableColumnsTrait;

    protected ModelService $modelService;
    protected TableColumnsService $tableColumnsService;

    public function __construct()
    {
        parent::__construct();
        $this->modelService = new ModelService;
        $this->tableColumnsService = new TableColumnsService;
    }

    public function create(array $modelData, bool $overwrite = false, string $pattern = 'normal', ?string $spatieData = null): string
    {
        return $this->fileService->createFromStub($modelData, 'resource', 'Http/Resources', 'Resource', $overwrite, function ($modelData) use ($pattern, $spatieData) {
            $model = $this->getFullModelNamespace($modelData);
            $resourcesData = $this->getResourcesData($modelData, $model, $pattern, $spatieData);

            return [
                '{{ modelNamespace }}' => $model,
                '{{ model }}' => $modelData['modelName'],
                '{{ data }}' => HelperService::formatArrayToPhpSyntax($resourcesData['data'], true),
                '{{ mediaResourceImport }}' => $resourcesData['mediaResourceImport'],
            ];
        });
    }

    private function getResourcesData(array $modelData, string $model, string $pattern = 'normal', ?string $spatieData = null): array
    {
        $data = [];
        $mediaResourceImport = '';

        // Always include id
        $data['id'] = '$this->id';

        // Detect media fields once (used later)
        $mediaFields = \Mrmarchone\LaravelAutoCrud\Services\MediaDetector::detectMediaFields($model);

        // Get media field names for filtering
        $mediaFieldNames = [];
        foreach ($mediaFields as $field) {
            $mediaFieldNames[] = Str::camel($field['name']);
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

                $camelCaseName = Str::camel($propertyName);
                $snakeCaseName = Str::snake($propertyName);
                $data[$camelCaseName] = '$this->'.$snakeCaseName;
            }
        } else {
            // Use database columns (original behavior)
            $columns = $this->getAvailableColumns($modelData);

            // Add regular columns (excluding id, created_at, updated_at)
            foreach ($columns as $column) {
                $columnName = $column['name'];

                // Skip id, created_at, updated_at as they're handled separately
                if (in_array($columnName, ['id', 'created_at', 'updated_at'])) {
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

        // Always add timestamps at the end
        $data['createdAt'] = '$this->created_at->toDateTimeString()';
        $data['updatedAt'] = '$this->updated_at->toDateTimeString()';

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
