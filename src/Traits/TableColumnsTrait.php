<?php

namespace Mrmarchone\LaravelAutoCrud\Traits;

use Illuminate\Support\Str;
use Mrmarchone\LaravelAutoCrud\Services\ModelService;
use Mrmarchone\LaravelAutoCrud\Services\TableColumnsService;

trait TableColumnsTrait
{
    public function getAvailableColumns(array $modelData): array
    {
        $model = ModelService::getFullModelNamespace($modelData);
        $modelInstance = new $model;
        $table = $modelInstance->getTable();

        return $this->tableColumnsService->getAvailableColumns($table, ['created_at', 'updated_at'], $model);
    }

    protected function isSearchableColumnType(string $type): bool
    {
        $type = strtolower($type);

        if ($type === 'email') {
            return true;
        }

        return Str::contains($type, [
            'char',
            'text',
            'string',
            'json',
            'uuid',
            'guid',
            'citext',
            'varchar',
            'nvarchar',
            'nchar',
        ]);
    }
}
