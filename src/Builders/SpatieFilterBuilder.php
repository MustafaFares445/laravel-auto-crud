<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Builders;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Mrmarchone\LaravelAutoCrud\Services\FileService;
use Mrmarchone\LaravelAutoCrud\Services\HelperService;
use Mrmarchone\LaravelAutoCrud\Services\ModelService;
use Mrmarchone\LaravelAutoCrud\Services\TableColumnsService;
use Mrmarchone\LaravelAutoCrud\Traits\TableColumnsTrait;
use ReflectionClass;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;

class SpatieFilterBuilder extends BaseBuilder
{
    use TableColumnsTrait;

    protected TableColumnsService $tableColumnsService;

    protected ModelService $modelService;

    public function __construct(FileService $fileService, TableColumnsService $tableColumnsService, ModelService $modelService)
    {
        parent::__construct($fileService);
        $this->tableColumnsService = $tableColumnsService;
        $this->modelService = $modelService;
    }

    public function createFilterRequest(array $modelData, bool $overwrite = false): string
    {
        return $this->fileService->createFromStub($modelData, 'spatie_filter_request', 'Http/Requests', 'FilterRequest', $overwrite, function ($modelData) {
            $columns = $this->getAvailableColumns($modelData);
            $rules = [];
            $sortableColumns = [];
            $searchableColumns = [];

            foreach ($columns as $column) {
                $columnName = $column['name'];
                $camelCaseName = Str::camel($columnName);
                $isDate = in_array($column['type'], ['date', 'datetime', 'timestamp']);

                // 1. Filter Rules
                $filterRule = '';
                if ($columnName === 'id') {
                    $filterRule = 'uuid';
                } elseif ($columnName === 'email') {
                    $filterRule = 'email|max:255';
                } elseif ($isDate) {
                    $filterRule = 'date';
                } else {
                    $filterRule = 'string|max:255';
                }

                if ($columnName !== 'id' && $columnName !== 'created_at' && $columnName !== 'updated_at') {
                    $rules[] = "            'filter.{$camelCaseName}' => 'sometimes|{$filterRule}',";
                }

                // 2. Sortable Columns
                $sortableColumns[] = $columnName;

                // 3. Searchable Columns (for scopeSearch)
                if (in_array($column['type'], ['string', 'varchar', 'text', 'longtext', 'mediumtext', 'char', 'email'])) {
                    $searchableColumns[] = $columnName;
                }
            }

            // Add custom filter rules
            $rules[] = "            'filter.createdAfter' => 'sometimes|date',";
            $rules[] = "            'filter.createdBefore' => 'sometimes|date|after_or_equal:filter.createdAfter',";
            $rules[] = "            'filter.search' => 'sometimes|string|max:255',";

            // Add sortable columns with ascending and descending options
            $sortOptions = [];
            foreach (array_unique($sortableColumns) as $col) {
                $camelCol = Str::camel($col);
                $sortOptions[] = $camelCol;
                $sortOptions[] = "-{$camelCol}";
            }

            return [
                '{{ rules }}' => implode("\n", $rules),
                '{{ sortableColumns }}' => implode(',', $sortOptions),
            ];
        });
    }

    public function createFilterQueryTrait(array $modelData, bool $overwrite = false): string
    {
        $modelClass = $this->getFullModelNamespace($modelData);
        $hasScoutSearch = $this->modelHasScoutSearch($modelClass);

        return $this->fileService->createFromStub($modelData, 'spatie_filter_query_trait', 'Traits/FilterQueries', 'FilterQuery', $overwrite, function ($modelData) use ($hasScoutSearch) {
            try {
                $columns = $this->getAvailableColumns($modelData);
            } catch (\Throwable) {
                $columns = [];
            }

            if (empty($columns)) {
                $columns = $this->fallbackColumnMetadata($modelData);
            }

            $allowedFilters = [];
            $allowedSorts = [];
            $searchableColumns = [];
            $scopes = [];

            foreach ($columns as $column) {
                $columnName = $column['name'];
                $camelCaseName = Str::camel($columnName);
                $isDate = in_array($column['type'], ['date', 'datetime', 'timestamp']);

                // 1. Allowed Filters
                if ($columnName === 'id') {
                    $allowedFilters[] = "                AllowedFilter::exact('{$columnName}'),";
                } elseif ($columnName === 'email_verified_at') {
                    $allowedFilters[] = "                AllowedFilter::exact('{$columnName}'),";
                } elseif ($columnName !== 'created_at' && $columnName !== 'updated_at') {
                    if ($camelCaseName !== $columnName) {
                        $allowedFilters[] = "                AllowedFilter::partial('{$camelCaseName}', '{$columnName}'),";
                    } else {
                        $allowedFilters[] = "                AllowedFilter::partial('{$camelCaseName}'),";
                    }
                }

                // 2. Allowed Sorts
                if ($camelCaseName !== $columnName) {
                    $allowedSorts[] = "                AllowedSort::field('{$camelCaseName}', '{$columnName}'),";
                } else {
                    $allowedSorts[] = "                AllowedSort::field('{$camelCaseName}'),";
                }

                // 3. Searchable Columns (for scopeSearch)
                if ($this->isSearchableColumnType($column['type'])) {
                    $searchableColumns[] = $columnName;
                }
            }

            if (empty($searchableColumns)) {
                $searchableColumns = $this->collectSearchableColumns($this->fallbackColumnMetadata($modelData));
            }

            // Add custom scope filters
            $allowedFilters[] = "                AllowedFilter::scope('createdAfter'),";
            $allowedFilters[] = "                AllowedFilter::scope('createdBefore'),";
            $allowedFilters[] = "                AllowedFilter::scope('search'),";

            // Generate scopes
            $scopes[] = $this->generateScopeCreatedAfter();
            $scopes[] = $this->generateScopeCreatedBefore();
            $scopes[] = $this->generateScopeSearch($modelData, $searchableColumns, $hasScoutSearch);

            return [
                '{{ modelNamespace }}' => $this->getFullModelNamespace($modelData),
                '{{ model }}' => $modelData['modelName'],
                '{{ allowedFilters }}' => implode("\n", $allowedFilters),
                '{{ allowedSorts }}' => implode("\n", $allowedSorts),
                '{{ scopes }}' => implode("\n", $scopes),
            ];
        });
    }

    private function modelHasScoutSearch(string $modelClass): bool
    {
        try {
            if (! class_exists($modelClass)) {
                return false;
            }

            $reflection = new ReflectionClass($modelClass);

            return $reflection->hasMethod('toSearchableArray');
        } catch (\Throwable) {
            return false;
        }
    }

    private function generateScopeCreatedAfter(): string
    {
        return <<<EOT
            public function scopeCreatedAfter(\$query, \$date)
            {
                return \$query->where('created_at', '>=', \$date);
            }
        EOT;
    }

    private function generateScopeCreatedBefore(): string
    {
        return <<<EOT
            public function scopeCreatedBefore(\$query, \$date)
            {
                return \$query->where('created_at', '<=', \$date);
            }
        EOT;
    }

    private function generateScopeSearch(array $modelData, array $searchableColumns, bool $hasScoutSearch): string
    {
        if ($hasScoutSearch) {
            return $this->generateScoutScopeSearch($modelData);
        }

        return $this->generateDatabaseScopeSearch($searchableColumns);
    }

    private function generateScoutScopeSearch(array $modelData): string
    {
        $modelName = $modelData['modelName'];
        $modelVariable = lcfirst($modelName);

        return <<<EOT

    public function scopeSearch(\$query, \$term): Builder
    {
        if (empty(\$term)) {
            return \$query;
        }

        \${$modelVariable}Ids = {$modelName}::search(\$term)->keys();

        return \$query->when(
            \${$modelVariable}Ids->isNotEmpty(),
            fn (Builder \$q) => \$q->whereIn('id', \${$modelVariable}Ids)
        );
    }
EOT;
    }

    private function generateDatabaseScopeSearch(array $searchableColumns): string
    {
        if (empty($searchableColumns)) {
            return <<<EOT

    public function scopeSearch(\$query, \$term): Builder
    {
        return \$query;
    }
EOT;
        }

        $conditions = [];
        $isFirst = true;

        foreach ($searchableColumns as $column) {
            if ($isFirst) {
                $conditions[] = "\$q->whereRaw(\"{$column} LIKE ? ESCAPE '!'\", [\$likeTerm])";
                $isFirst = false;
            } else {
                $conditions[] = "->orWhereRaw(\"{$column} LIKE ? ESCAPE '!'\", [\$likeTerm])";
            }
        }

        $conditionsString = implode("\n                ", $conditions);

        return <<<EOT

    public function scopeSearch(\$query, \$term): Builder
    {
        if (empty(\$term)) {
            return \$query;
        }

        \$likeTerm = SearchTermEscaper::escape(\$term);

        return \$query->where(function (Builder \$q) use (\$likeTerm) {
            {$conditionsString};
        });
    }
EOT;
    }

    private function fallbackColumnMetadata(array $modelData): array
    {
        $modelClass = $this->getFullModelNamespace($modelData);

        if (! class_exists($modelClass)) {
            return [];
        }

        try {
            $model = new $modelClass;
        } catch (\Throwable) {
            return [];
        }

        $fromFillable = method_exists($model, 'getFillable') ? $model->getFillable() : [];
        $fromCasts = method_exists($model, 'getCasts') ? array_keys($model->getCasts()) : [];

        $columns = array_unique(array_merge($fromFillable, $fromCasts));

        return array_values(array_map(fn ($name) => [
            'name' => $name,
            'type' => 'string',
        ], array_filter($columns, fn ($name) => ! in_array($name, ['created_at', 'updated_at', 'deleted_at'], true))));
    }

    private function collectSearchableColumns(array $columns): array
    {
        $searchableColumns = [];

        foreach ($columns as $column) {
            if ($this->isSearchableColumnType($column['type'])) {
                $searchableColumns[] = $column['name'];
            }
        }

        return array_values(array_unique($searchableColumns));
    }

    private function isSearchableColumnType(string $type): bool
    {
        return in_array($type, [
            'string',
            'varchar',
            'text',
            'longtext',
            'mediumtext',
            'char',
            'email',
            'enum',
            'json',
            'jsonb',
            'array',
        ], true);
    }
}

