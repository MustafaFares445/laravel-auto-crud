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

    public function __construct()
    {
        parent::__construct();
        $this->modelService = new ModelService;
        $this->tableColumnsService = new TableColumnsService;
    }

    public function create(array $modelData, bool $overwrite = false): string
    {
        return $this->fileService->createFromStub($modelData, 'resource', 'Http/Resources', 'Resource', $overwrite, function ($modelData) {
            $model = $this->getFullModelNamespace($modelData);
            $resourcesData = $this->getResourcesData($modelData, $model);
            
            return [
                '{{ modelNamespace }}' => $model,
                '{{ model }}' => $modelData['modelName'],
                '{{ data }}' => HelperService::formatArrayToPhpSyntax($resourcesData['data'], true),
                '{{ mediaResourceImport }}' => $resourcesData['mediaResourceImport'],
            ];
        });
    }

    private function getResourcesData(array $modelData, string $model): array
    {
        $columns = $this->getAvailableColumns($modelData);
        $data = [];
        $mediaResourceImport = '';

        // Always include id
        $data['id'] = '$this->id';

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

        // Detect and add media fields
        $mediaFields = \Mrmarchone\LaravelAutoCrud\Services\MediaDetector::detectMediaFields($model);
        
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
}
