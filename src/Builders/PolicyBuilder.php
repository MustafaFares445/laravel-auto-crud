<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Builders;

class PolicyBuilder extends BaseBuilder
{
    public function create(array $modelData, bool $overwrite = false): string
    {
        return $this->fileService->createFromStub($modelData, 'policy', 'Policies', 'Policy', $overwrite, function ($modelData) {
            $model = $this->getFullModelNamespace($modelData);
            $modelName = $modelData['modelName'];
            $modelVariable = lcfirst($modelName);

            return [
                '{{ modelNamespace }}' => $model,
                '{{ model }}' => $modelName,
                '{{ modelVariable }}' => $modelVariable,
            ];
        });
    }
}
