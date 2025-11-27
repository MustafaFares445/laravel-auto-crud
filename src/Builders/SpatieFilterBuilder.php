<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Builders;

use Illuminate\Support\Str;
use Mrmarchone\LaravelAutoCrud\Services\HelperService;
use Mrmarchone\LaravelAutoCrud\Traits\TableColumnsTrait;

class SpatieFilterBuilder extends BaseBuilder
{
    use TableColumnsTrait;

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
        return $this->fileService->createFromStub($modelData, 'spatie_filter_query_trait', 'Traits/FilterQueries', 'FilterQuery', $overwrite, function ($modelData) {
            $columns = $this->getAvailableColumns($modelData);
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
                    $allowedFilters[] = "                AllowedFilter::partial('{$columnName}'),";
                }

                // 2. Allowed Sorts
                $allowedSorts[] = "                '{$columnName}',";

                // 3. Searchable Columns (for scopeSearch)
                if (in_array($column['type'], ['string', 'varchar', 'text', 'longtext', 'mediumtext', 'char', 'email'])) {
                    $searchableColumns[] = $columnName;
                }
            }

            // Add custom scope filters
            $allowedFilters[] = "                AllowedFilter::scope('created_after'),";
            $allowedFilters[] = "                AllowedFilter::scope('created_before'),";
            $allowedFilters[] = "                AllowedFilter::scope('search'),";

            // Generate scopes
            $scopes[] = $this->generateScopeCreatedAfter();
            $scopes[] = $this->generateScopeCreatedBefore();
            $scopes[] = $this->generateScopeSearch($searchableColumns);

            return [
                '{{ modelNamespace }}' => $this->getFullModelNamespace($modelData),
                '{{ model }}' => $modelData['modelName'],
                '{{ allowedFilters }}' => implode("\n", $allowedFilters),
                '{{ allowedSorts }}' => implode("\n", $allowedSorts),
                '{{ scopes }}' => implode("\n", $scopes),
            ];
        });
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

    private function generateScopeSearch(array $searchableColumns): string
    {
        if (empty($searchableColumns)) {
            return '';
        }

        $conditions = [];
        foreach ($searchableColumns as $column) {
            $conditions[] = "                ->orWhere('{$column}', 'LIKE', \"%{\$term}%\")";
        }
        // Remove the first 'orWhere' and replace it with 'where'
        $firstCondition = array_shift($conditions);
        $firstCondition = str_replace('->orWhere', '->where', $firstCondition);
        array_unshift($conditions, $firstCondition);

        $conditionsString = implode("\n", $conditions);

        return <<<EOT

    public function scopeSearch(\$query, \$term)
    {
        if (empty(\$term)) {
            return \$query;
        }

        return \$query->where(function (\$q) use (\$term) {
{$conditionsString}
        });
    }
EOT;
    }
}