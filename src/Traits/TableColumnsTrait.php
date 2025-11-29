<?php

namespace Mrmarchone\LaravelAutoCrud\Traits;

use Mrmarchone\LaravelAutoCrud\Services\ModelService;
use Mrmarchone\LaravelAutoCrud\Services\TableColumnsService;

trait TableColumnsTrait
{
    protected TableColumnsService $tableColumnsService;

    protected ModelService $modelService;

    public function getAvailableColumns(array $modelData): array
    {
        $table = ModelService::getFullModelNamespace($modelData);

        return $this->tableColumnsService->getAvailableColumns($table);
    }
}
