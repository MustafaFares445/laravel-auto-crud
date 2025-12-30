<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Builders;

use Mrmarchone\LaravelAutoCrud\Services\FileService;

abstract class BaseBuilder
{
    protected FileService $fileService;

    public function __construct(FileService $fileService)
    {
        $this->fileService = $fileService;
    }

    protected function getFullModelNamespace(array $modelData): string
    {
        return $modelData['namespace'] ? $modelData['namespace'].'\\'.$modelData['modelName'] : $modelData['modelName'];
    }
}