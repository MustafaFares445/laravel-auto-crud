<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Builders;

use Mrmarchone\LaravelAutoCrud\Services\FileService;
use Mrmarchone\LaravelAutoCrud\Traits\ModelHelperTrait;

class PolicyBuilder
{
    use ModelHelperTrait;

    protected FileService $fileService;

    public function __construct(FileService $fileService)
    {
        $this->fileService = $fileService;
    }
    public function create(array $modelData, bool $overwrite = false, ?string $module = null): string
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
        }, $module);
    }
}
