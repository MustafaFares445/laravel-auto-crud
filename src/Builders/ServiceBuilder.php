<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Builders;

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
            
            $mediaHelperImport = $hasMedia ? "\nuse App\Helpers\MediaHelper;" : '';
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
            
            // Add empty line before return if there's media logic
            if ($mediaStoreLogic) {
                $mediaStoreLogic .= "\n";
            }
            if ($mediaUpdateLogic) {
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
            ];
        });
    }
}
