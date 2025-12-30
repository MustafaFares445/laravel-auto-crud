<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Builders;

use Illuminate\Support\Str;
use Mrmarchone\LaravelAutoCrud\Services\RelationshipDetector;

class ServiceBuilder extends BaseBuilder
{
    public function create(array $modelData, string $repository, bool $overwrite = false): string
    {
        return $this->fileService->createFromStub($modelData, 'service', 'Services', 'Service', $overwrite, function ($modelData) use ($repository) {
            $model = $this->getFullModelNamespace($modelData);
            $repositorySplitting = explode('\\', $repository);
            $repositoryNamespace = $repository;
            $repository = end($repositorySplitting);
            $repositoryVariable = lcfirst($repository);

            return [
                '{{ modelNamespace }}' => $model,
                '{{ model }}' => $modelData['modelName'],
                '{{ modelVariable }}' => lcfirst($modelData['modelName']),
                '{{ repository }}' => $repository,
                '{{ repositoryNamespace }}' => $repositoryNamespace,
                '{{ repositoryVariable }}' => $repositoryVariable,
            ];
        });
    }

    public function createServiceOnly(array $modelData, bool $overwrite = false): string
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
            
            // Detect relationships and generate syncing logic
            $relationships = RelationshipDetector::detectRelationships($model);
            $relationshipStoreLogic = '';
            $relationshipUpdateLogic = '';
            
            foreach ($relationships as $relationship) {
                if ($relationship['type'] === 'belongsToMany') {
                    $relatedModel = $relationship['related_model'] ?? null;
                    if ($relatedModel) {
                        $relatedModelName = RelationshipDetector::getModelNameFromClass($relatedModel);
                        $propertyName = Str::camel(strtolower($relatedModelName) . '_ids');
                        $relationshipName = $relationship['name'];
                        
                        $relationshipStoreLogic .= "\n            // Sync {$relationshipName} relationship";
                        $relationshipStoreLogic .= "\n            if (isset(\$data->{$propertyName})) {";
                        $relationshipStoreLogic .= "\n                \${$modelVariable}->{$relationshipName}()->sync(\$data->{$propertyName} ?? []);";
                        $relationshipStoreLogic .= "\n            }";
                        
                        $relationshipUpdateLogic .= "\n            // Sync {$relationshipName} relationship";
                        $relationshipUpdateLogic .= "\n            if (isset(\$data->{$propertyName})) {";
                        $relationshipUpdateLogic .= "\n                \${$modelVariable}->{$relationshipName}()->sync(\$data->{$propertyName} ?? []);";
                        $relationshipUpdateLogic .= "\n            }";
                    }
                }
            }
            
            // Add empty line before return if there's media or relationship logic
            if ($mediaStoreLogic || $relationshipStoreLogic) {
                $mediaStoreLogic .= "\n";
            }
            if ($mediaUpdateLogic || $relationshipUpdateLogic) {
                $mediaUpdateLogic .= "\n";
            }
            
            // Combine media logic (relationships are now handled via syncRelationships in Data class)
            $storeLogic = $mediaStoreLogic;
            $updateLogic = $mediaUpdateLogic;

            return [
                '{{ modelNamespace }}' => $model,
                '{{ dataNamespace }}' => $dataClass,
                '{{ model }}' => $modelData['modelName'],
                '{{ data }}' => $modelData['modelName'] . 'Data',
                '{{ modelVariable }}' => $modelVariable,
                '{{ mediaHelperImport }}' => $mediaHelperImport,
                '{{ mediaStoreLogic }}' => $storeLogic,
                '{{ mediaUpdateLogic }}' => $updateLogic,
            ];
        });
    }
}

