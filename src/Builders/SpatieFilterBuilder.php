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

    /**
     * Initialize the SpatieFilterBuilder with required services.
     *
     * @param FileService $fileService File service for creating files
     * @param TableColumnsService $tableColumnsService Service for table column operations
     * @param ModelService $modelService Service for model operations
     */
    public function __construct(FileService $fileService, TableColumnsService $tableColumnsService, ModelService $modelService)
    {
        parent::__construct($fileService);
        $this->tableColumnsService = $tableColumnsService;
        $this->modelService = $modelService;
    }

    /**
     * Create a filter request class for Spatie Query Builder.
     *
     * @param array<string, mixed> $modelData Model information
     * @param bool $overwrite Whether to overwrite existing files
     * @return string Full namespace path of the created file
     */
    public function createFilterRequest(array $modelData, bool $overwrite = false): string
    {
        $requestPath = 'Http/Requests/' . $modelData['modelName'] . 'Requests';
        return $this->fileService->createFromStub($modelData, 'spatie_filter_request', $requestPath, 'FilterRequest', $overwrite, function ($modelData) {
            $columns = $this->getAvailableColumns($modelData);
            
            // Get hidden properties and filter them out
            $model = $this->getFullModelNamespace($modelData);
            $hiddenProperties = $this->getHiddenProperties($model);
            $columns = array_filter($columns, function($column) use ($hiddenProperties) {
                return !in_array($column['name'], $hiddenProperties, true);
            });
            
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

    /**
     * Create a filter query trait for Spatie Query Builder.
     *
     * @param array<string, mixed> $modelData Model information
     * @param bool $overwrite Whether to overwrite existing files
     * @return string Full namespace path of the created file
     */
    public function createFilterQueryTrait(array $modelData, bool $overwrite = false): string
    {
        $modelClass = $this->getFullModelNamespace($modelData);
        $hasScoutSearch = $this->modelHasScoutSearch($modelClass);
        $isTypesense = $this->isTypesenseDriver($modelClass);

        return $this->fileService->createFromStub($modelData, 'spatie_filter_query_trait', 'Traits/FilterQueries', 'FilterQuery', $overwrite, function ($modelData) use ($hasScoutSearch, $isTypesense) {
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
            $scopes[] = $this->generateScopeSearch($modelData, $searchableColumns, $hasScoutSearch, $isTypesense);

            return [
                '{{ modelNamespace }}' => $this->getFullModelNamespace($modelData),
                '{{ model }}' => $modelData['modelName'],
                '{{ allowedFilters }}' => implode("\n", $allowedFilters),
                '{{ allowedSorts }}' => implode("\n", $allowedSorts),
                '{{ scopes }}' => implode("\n", $scopes),
            ];
        });
    }

    /**
     * Check if model has Scout search implementation.
     *
     * @param string $modelClass Full model class name
     * @return bool True if model has toSearchableArray method
     */
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

    /**
     * Check if Typesense driver is being used for Scout.
     *
     * @param string $modelClass Full model class name
     * @return bool True if Typesense driver is configured
     */
    private function isTypesenseDriver(string $modelClass): bool
    {
        // Check if Typesense driver is configured in Scout config
        $scoutDriver = config('scout.driver', '');
        
        if ($scoutDriver === 'typesense') {
            return true;
        }

        // Check if model uses TypesenseEngine explicitly
        try {
            if (! class_exists($modelClass)) {
                return false;
            }

            $reflection = new ReflectionClass($modelClass);
            
            // Check if model has searchableUsing method that returns TypesenseEngine
            if ($reflection->hasMethod('searchableUsing')) {
                $method = $reflection->getMethod('searchableUsing');
                $returnType = $method->getReturnType();
                
                if ($returnType instanceof \ReflectionNamedType) {
                    $returnTypeName = $returnType->getName();
                    if (str_contains($returnTypeName, 'Typesense') || str_contains($returnTypeName, 'TypesenseEngine')) {
                        return true;
                    }
                }
            }

            // Check if TypesenseEngine class exists (indicates package is installed)
            if (class_exists('Typesense\LaravelScout\TypesenseEngine') || 
                class_exists('Typesense\ScoutEngines\TypesenseEngine')) {
                // If Typesense package is installed and Scout driver is not explicitly set to something else
                return $scoutDriver !== 'algolia' && $scoutDriver !== 'meilisearch';
            }
        } catch (\Throwable) {
            // Ignore errors
        }

        return false;
    }

    /**
     * Generate scopeCreatedAfter method code.
     *
     * @return string Generated method code
     */
    private function generateScopeCreatedAfter(): string
    {
        return <<<EOT
            public function scopeCreatedAfter(\$query, \$date)
            {
                return \$query->where('created_at', '>=', \$date);
            }
        EOT;
    }

    /**
     * Generate scopeCreatedBefore method code.
     *
     * @return string Generated method code
     */
    private function generateScopeCreatedBefore(): string
    {
        return <<<EOT
            public function scopeCreatedBefore(\$query, \$date)
            {
                return \$query->where('created_at', '<=', \$date);
            }
        EOT;
    }

    /**
     * Generate scopeSearch method code.
     *
     * @param array<string, mixed> $modelData Model information
     * @param array<int, string> $searchableColumns List of searchable column names
     * @param bool $hasScoutSearch Whether model uses Scout search
     * @param bool $isTypesense Whether Typesense driver is being used
     * @return string Generated method code
     */
    private function generateScopeSearch(array $modelData, array $searchableColumns, bool $hasScoutSearch, bool $isTypesense = false): string
    {
        if ($hasScoutSearch) {
            if ($isTypesense) {
                return $this->generateTypesenseScopeSearch($modelData, $searchableColumns);
            }
            return $this->generateScoutScopeSearch($modelData);
        }

        return $this->generateDatabaseScopeSearch($searchableColumns);
    }

    /**
     * Generate scopeSearch method using Scout search.
     *
     * @param array<string, mixed> $modelData Model information
     * @return string Generated method code
     */
    private function generateScoutScopeSearch(array $modelData): string
    {
        $modelName = $modelData['modelName'];
        $modelVariable = lcfirst($modelName);

        return <<<EOT

    public function scopeSearch(\$query, \$search): Builder
    {
        if (empty(\$search)) {
            return \$query;
        }

        \${$modelVariable}Ids = {$modelName}::search(\$search)->keys();

        return \$query->when(
            \${$modelVariable}Ids->isNotEmpty(),
            fn (Builder \$q) => \$q->whereIn('id', \${$modelVariable}Ids)
        );
    }
EOT;
    }

    /**
     * Generate scopeSearch method using Typesense-specific features.
     *
     * @param array<string, mixed> $modelData Model information
     * @param array<int, string> $searchableColumns List of searchable column names
     * @return string Generated method code
     */
    private function generateTypesenseScopeSearch(array $modelData, array $searchableColumns): string
    {
        $modelName = $modelData['modelName'];
        $modelVariable = lcfirst($modelName);

        // Build query_by parameter for Typesense (comma-separated list of fields)
        $queryByFields = !empty($searchableColumns) 
            ? "'" . implode(',', $searchableColumns) . "'"
            : "'*'"; // Search all fields if no specific columns

        return <<<EOT

    public function scopeSearch(\$query, \$search, ?int \$typoTolerance = null, ?string \$queryBy = null): Builder
    {
        if (empty(\$search)) {
            return \$query;
        }

        // Typesense-specific search with typo tolerance and query_by parameters
        \$searchBuilder = {$modelName}::search(\$search);
        
        // Set query_by fields (which fields to search in)
        if (\$queryBy !== null) {
            \$searchBuilder->queryBy(\$queryBy);
        } else {
            // Default to all searchable columns
            \$searchBuilder->queryBy({$queryByFields});
        }
        
        // Set typo tolerance (0-2, where 2 is most lenient)
        if (\$typoTolerance !== null) {
            \$searchBuilder->typoTolerance(\$typoTolerance);
        } else {
            // Default typo tolerance for better search results
            \$searchBuilder->typoTolerance(1);
        }
        
        // Additional Typesense-specific parameters
        \$searchBuilder->numTypos(1) // Allow 1 typo
            ->dropTokensThreshold(0) // Don't drop tokens
            ->exhaustiveSearch(false); // Balance between speed and accuracy

        \${$modelVariable}Ids = \$searchBuilder->keys();

        return \$query->when(
            \${$modelVariable}Ids->isNotEmpty(),
            fn (Builder \$q) => \$q->whereIn('id', \${$modelVariable}Ids)
        );
    }
EOT;
    }

    /**
     * Generate scopeSearch method using database LIKE queries.
     *
     * @param array<int, string> $searchableColumns List of searchable column names
     * @return string Generated method code
     */
    private function generateDatabaseScopeSearch(array $searchableColumns): string
    {
        if (empty($searchableColumns)) {
            return <<<EOT

    public function scopeSearch(\$query, \$search): Builder
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

    public function scopeSearch(\$query, \$search): Builder
    {
        if (empty(\$search)) {
            return \$query;
        }

        \$likeTerm = SearchTermEscaper::escape(\$search);

        return \$query->where(function (Builder \$q) use (\$likeTerm) {
            {$conditionsString};
        });
    }
EOT;
    }

    /**
     * Get fallback column metadata from model fillable and casts.
     *
     * @param array<string, mixed> $modelData Model information
     * @return array<int, array<string, string>> Array of column metadata
     */
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

    /**
     * Collect searchable columns from column metadata.
     *
     * @param array<int, array<string, string>> $columns Array of column metadata
     * @return array<int, string> Array of searchable column names
     */
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

    /**
     * Check if a column type is searchable.
     *
     * @param string $type Column type
     * @return bool True if column type is searchable
     */
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

