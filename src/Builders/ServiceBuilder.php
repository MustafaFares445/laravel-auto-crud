<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Builders;

use Illuminate\Support\Str;
use Mrmarchone\LaravelAutoCrud\Services\FileService;
use Mrmarchone\LaravelAutoCrud\Services\RelationshipDetector;
use Mrmarchone\LaravelAutoCrud\Traits\ModelHelperTrait;

class ServiceBuilder
{
    use ModelHelperTrait;

    protected FileService $fileService;

    public function __construct(FileService $fileService)
    {
        $this->fileService = $fileService;
    }
    public function createServiceOnly(array $modelData, bool $overwrite = false, ?string $module = null): string
    {
        return $this->fileService->createFromStub($modelData, 'service_only', 'Services', 'Service', $overwrite, function ($modelData) {
            $model = $this->getFullModelNamespace($modelData);
            $dataClass = 'App\\Data\\' . $modelData['modelName'] . 'Data';
            $modelVariable = lcfirst($modelData['modelName']);
            
            // Detect media fields
            $mediaFields = \Mrmarchone\LaravelAutoCrud\Services\MediaDetector::detectMediaFields($model);
            $hasMedia = !empty($mediaFields);
            
            $mediaHelperImport = $hasMedia ? "\nuse Mrmarchone\LaravelAutoCrud\Helpers\MediaHelper;" : '';
            $mediaStoreLogic = '';
            $mediaUpdateLogic = '';
            
            if ($hasMedia) {
                foreach ($mediaFields as $field) {
                    $fieldName = $field['name'];
                    $collection = $field['collectionName'];
                    $mediaStoreLogic .= "\n            MediaHelper::uploadMedia(\$data->{$fieldName}, \${$modelVariable}, '{$collection}');";
                    $mediaUpdateLogic .= "\n            MediaHelper::updateMedia(\$data->{$fieldName}, \${$modelVariable}, '{$collection}');";
                }
            }
            
            // Detect belongsToMany relationships to determine if syncRelationships method exists
            $relationships = RelationshipDetector::detectRelationships($model);
            $hasBelongsToMany = false;
            
            foreach ($relationships as $relationship) {
                if ($relationship['type'] === 'belongsToMany') {
                    $hasBelongsToMany = true;
                    break;
                }
            }
            
            // Only add syncRelationships call if there are belongsToMany relationships
            $syncRelationshipsCall = $hasBelongsToMany ? "\n            \$data->syncRelationships(\${$modelVariable});" : '';
            
            // Add empty line before syncRelationships if there's media logic
            if ($mediaStoreLogic && $syncRelationshipsCall) {
                $mediaStoreLogic .= "\n";
            }
            if ($mediaUpdateLogic && $syncRelationshipsCall) {
                $mediaUpdateLogic .= "\n";
            }

            return [
                '{{ modelNamespace }}' => $model,
                '{{ dataNamespace }}' => $dataClass,
                '{{ model }}' => $modelData['modelName'],
                '{{ data }}' => $modelData['modelName'] . 'Data',
                '{{ modelVariable }}' => $modelVariable,
                '{{ mediaHelperImport }}' => $mediaHelperImport,
                '{{ mediaStoreLogic }}' => $mediaStoreLogic,
                '{{ mediaUpdateLogic }}' => $mediaUpdateLogic,
                '{{ syncRelationshipsCall }}' => $syncRelationshipsCall,
            ];
        }, $module);
    }

    public function createBulkService(array $modelData, bool $overwrite = false, ?string $uniqueKey = null, string $pattern = 'normal', ?string $spatieData = null, ?string $module = null): string
    {
        $modifiedModelData = $modelData;
        $modifiedModelData['modelName'] = $modelData['modelName'] . 'Bulk';
        return $this->fileService->createFromStub($modifiedModelData, 'bulk_service', 'Services', 'Service', $overwrite, function ($modelData) use ($uniqueKey, $pattern, $spatieData) {
            $originalModelName = str_replace('Bulk', '', $modelData['modelName']);
            $originalModelData = [
                'modelName' => $originalModelName,
                'namespace' => $modelData['namespace'] ?? null,
            ];
            $model = $this->getFullModelNamespace($originalModelData);
            $dataClass = 'App\\Data\\' . $originalModelName . 'Data';

            $uniqueKeyProperty = $uniqueKey
                ? "\n    protected ?string \$uniqueKey = '{$uniqueKey}';\n"
                : '';

            $dataImport = '';
            $transformationLogic = '';
            
            if ($pattern === 'spatie-data' && $spatieData) {
                $dataImport = "use {$spatieData};\n";
                $dataClassShortName = $originalModelName . 'Data';
                $transformationLogic = "return collect(\$dataArray)->map(fn (array \$data) => {$dataClassShortName}::from(\$data));";
            } else {
                $transformationLogic = "return collect(\$dataArray);";
            }

            return [
                '{{ modelNamespace }}' => $model,
                '{{ dataNamespace }}' => $dataClass,
                '{{ model }}' => $originalModelName,
                '{{ data }}' => $originalModelName . 'Data',
                '{{ uniqueKeyProperty }}' => $uniqueKeyProperty,
                '{{ dataImport }}' => $dataImport,
                '{{ transformationLogic }}' => $transformationLogic,
            ];
        }, $module);
    }
}