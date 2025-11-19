<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Builders;

use Illuminate\Support\Str;
use ReflectionClass;
use Throwable;

class FilterBuilderBuilder extends BaseBuilder
{
    public function create(array $modelData, bool $overwrite = false): string
    {
        return $this->fileService->createFromStub($modelData, 'filter_builder', 'FilterBuilders', 'FilterBuilder', $overwrite, function ($modelData) {
            $model = $this->getFullModelNamespace($modelData);
            $requestClass = 'App\\Http\\Requests\\' . $modelData['modelName'] . 'FilterRequest';
            
            $textSearchMethod = $this->generateTextSearchMethod($modelData, $model);

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

    private function generateTextSearchMethod(array $modelData, string $modelClass): string
    {
        try {
            if (!class_exists($modelClass)) {
                return '';
            }

            $reflection = new ReflectionClass($modelClass);
            $searchableProperties = [];

            if ($reflection->hasMethod('toSearchableArray')) {
                $searchableProperties = $this->extractSearchableProperties($reflection);
            }

            if (empty($searchableProperties)) {
                return '';
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
        } catch (Throwable $e) {
            return '';
        }
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
        } catch (Throwable $e) {
        }

        return array_values($properties);
    }
}

