<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Builders;

use Mrmarchone\LaravelAutoCrud\Services\FileService;
use Mrmarchone\LaravelAutoCrud\Services\TableColumnsService;
use Mrmarchone\LaravelAutoCrud\Traits\ModelHelperTrait;
use Mrmarchone\LaravelAutoCrud\Traits\TableColumnsTrait;
use ReflectionClass;
use Throwable;

class FilterBuilderBuilder
{
    use ModelHelperTrait;
    use TableColumnsTrait;

    protected FileService $fileService;
    protected TableColumnsService $tableColumnsService;

    public function __construct(FileService $fileService, TableColumnsService $tableColumnsService)
    {
        $this->fileService = $fileService;
        $this->tableColumnsService = $tableColumnsService;
    }

    public function create(array $modelData, bool $overwrite = false): string
    {
        return $this->fileService->createFromStub($modelData, 'filter_builder', 'FilterBuilders', 'FilterBuilder', $overwrite, function ($modelData) {
            $model = $this->getFullModelNamespace($modelData);
            $requestClass = 'App\\Http\\Requests\\' . $modelData['modelName'] . 'Requests\\' . $modelData['modelName'] . 'FilterRequest';

            $hasScoutSearch = $this->modelHasScoutSearch($model);
            $textSearchMethod = $this->generateTextSearchMethod($modelData, $model, $hasScoutSearch);

            return [
                '{{ modelNamespace }}' => $model,
                '{{ model }}' => $modelData['modelName'],
                '{{ modelVariable }}' => lcfirst($modelData['modelName']),
                '{{ requestNamespace }}' => $requestClass,
                '{{ request }}' => $modelData['modelName'] . 'FilterRequest',
                '{{ textSearchMethod }}' => $textSearchMethod,
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
        } catch (Throwable) {
            return false;
        }
    }

    private function generateTextSearchMethod(array $modelData, string $modelClass, bool $hasScoutSearch): string
    {
        if ($hasScoutSearch) {
            return $this->generateScoutTextSearchMethod($modelData, $modelClass);
        }

        return $this->generateDatabaseTextSearchMethod($modelData);
    }

    private function generateScoutTextSearchMethod(array $modelData, string $modelClass): string
    {
        try {
            $reflection = new ReflectionClass($modelClass);
            $searchableProperties = $this->extractSearchableProperties($reflection);

            if (empty($searchableProperties)) {
                return $this->generateDatabaseTextSearchMethod($modelData);
            }

            $modelName = $modelData['modelName'];
            $modelVariable = lcfirst($modelName);
            $indent = '    ';

            $code = "\n{$indent}public function textSearch(?string \$text): self\n";
            $code .= "{$indent}{\n";
            $code .= "{$indent}    if (\$text) {\n";
            $code .= "{$indent}        \${$modelVariable}Ids = {$modelName}::search(\$text)->keys();\n\n";
            $code .= "{$indent}        \$this->query->when(\n";
            $code .= "{$indent}            \${$modelVariable}Ids->isNotEmpty(),\n";
            $code .= "{$indent}            fn(\$q) => \$q->whereIn('id', \${$modelVariable}Ids)\n";
            $code .= "{$indent}        );\n";
            $code .= "{$indent}    }\n\n";
            $code .= "{$indent}    return \$this;\n";
            $code .= "{$indent}}\n";

            return $code;
        } catch (Throwable) {
            return $this->generateDatabaseTextSearchMethod($modelData);
        }
    }

    private function generateDatabaseTextSearchMethod(array $modelData): string
    {
        try {
            $columns = $this->getAvailableColumns($modelData);
        } catch (Throwable) {
            return $this->emptyTextSearchMethod();
        }

        $searchableColumns = $this->collectSearchableColumns($columns);

        if (empty($searchableColumns)) {
            $searchableColumns = $this->fallbackSearchableColumns($modelData);

            if (empty($searchableColumns)) {
                return $this->emptyTextSearchMethod();
            }
        }

        $indent = '    ';
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

        $conditionsString = implode("\n{$indent}            ", $conditions);

        $code = "\n{$indent}public function textSearch(?string \$text): self\n";
        $code .= "{$indent}{\n";
        $code .= "{$indent}    if (empty(\$text)) {\n";
        $code .= "{$indent}        return \$this;\n";
        $code .= "{$indent}    }\n\n";
        $code .= "{$indent}    \$likeTerm = \\Mrmarchone\\LaravelAutoCrud\\Helpers\\SearchTermEscaper::escape(\$text);\n\n";
        $code .= "{$indent}    \$this->query->where(function (\$q) use (\$likeTerm) {\n";
        $code .= "{$indent}        {$conditionsString};\n";
        $code .= "{$indent}    });\n\n";
        $code .= "{$indent}    return \$this;\n";
        $code .= "{$indent}}\n";

        return $code;
    }

    private function emptyTextSearchMethod(): string
    {
        $indent = '    ';

        return "\n{$indent}public function textSearch(?string \$text): self\n"
            . "{$indent}{\n"
            . "{$indent}    return \$this;\n"
            . "{$indent}}\n";
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

    private function fallbackSearchableColumns(array $modelData): array
    {
        $modelClass = $this->getFullModelNamespace($modelData);

        if (! class_exists($modelClass)) {
            return [];
        }

        try {
            $model = new $modelClass;
        } catch (Throwable) {
            return [];
        }

        $fromFillable = method_exists($model, 'getFillable') ? $model->getFillable() : [];
        $fromCasts = method_exists($model, 'getCasts') ? array_keys($model->getCasts()) : [];

        $candidates = array_unique(array_merge($fromFillable, $fromCasts));

        return array_values(array_filter(
            $candidates,
            fn ($column) => ! in_array($column, ['id', 'created_at', 'updated_at', 'deleted_at'], true)
        ));
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

    private function extractSearchableProperties(ReflectionClass $reflection): array
    {
        $method = $reflection->getMethod('toSearchableArray');
        $fileName = $method->getFileName();
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();

        if ($fileName === false || $startLine === false || $endLine === false) {
            return [];
        }

        $lines = file($fileName);
        if ($lines === false) {
            return [];
        }

        $methodCode = implode('', array_slice($lines, $startLine - 1, $endLine - $startLine + 1));

        $properties = [];

        if (preg_match('/return\s+([^;]+);/s', $methodCode, $matches)) {
            $returnExpression = $matches[1];

            if (preg_match_all("/['\"]([a-z_][a-z0-9_]*)['\"]\s*=>/i", $returnExpression, $keyMatches)) {
                $properties = array_unique($keyMatches[1]);
                $properties = array_filter($properties, function ($prop) {
                    return ! in_array($prop, ['created_at', 'updated_at', 'deleted_at', 'timestamp'], true);
                });
            }
        }

        try {
            $modelInstance = app($reflection->getName());
            if (method_exists($modelInstance, 'toSearchableArray')) {
                $searchableArray = $modelInstance->toSearchableArray();
                $arrayKeys = array_keys($searchableArray);
                $properties = array_unique(array_merge($properties, $arrayKeys));
                $properties = array_filter($properties, function ($prop) {
                    return ! in_array($prop, ['created_at', 'updated_at', 'deleted_at'], true);
                });
            }
        } catch (Throwable) {
        }

        return array_values($properties);
    }
}


