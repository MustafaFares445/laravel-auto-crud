<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Builders;

class ScoutBuilder extends BaseBuilder
{
    /**
     * Check if Laravel Scout is installed.
     *
     * @return bool
     */
    public function isInstalled(): bool
    {
        return class_exists(\Laravel\Scout\Searchable::class);
    }

    /**
     * Check if Typesense is configured.
     *
     * @return bool
     */
    public function isTypesenseInstalled(): bool
    {
        return class_exists(\Typesense\Client::class);
    }

    /**
     * Generate search method for controller.
     *
     * @param array<string, mixed> $modelData Model information
     * @return string Generated search method code
     */
    public function generateSearchMethod(array $modelData): string
    {
        $model = $this->getFullModelNamespace($modelData);
        $modelName = $modelData['modelName'];
        $modelVariable = lcfirst($modelName);
        $resourceClass = "App\\Http\\Resources\\{$modelName}Resource";

        if ($this->isTypesenseInstalled()) {
            return $this->generateTypesenseSearchMethod($model, $modelName, $modelVariable, $resourceClass);
        }

        return $this->generateStandardSearchMethod($model, $modelName, $modelVariable, $resourceClass);
    }

    /**
     * Generate standard Scout search method.
     *
     * @param string $model
     * @param string $modelName
     * @param string $modelVariable
     * @param string $resourceClass
     * @return string
     */
    private function generateStandardSearchMethod(string $model, string $modelName, string $modelVariable, string $resourceClass): string
    {
        return <<<PHP
    /**
     * Search {$modelName} records.
     *
     * @param \Illuminate\Http\Request \$request
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function search(\Illuminate\Http\Request \$request): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        \$query = \$request->get('q', '');
        
        if (empty(\$query)) {
            return {$resourceClass}::collection(collect([]));
        }

        \$results = {$model}::search(\$query)->paginate(20);

        return {$resourceClass}::collection(\$results);
    }
PHP;
    }

    /**
     * Generate Typesense-specific search method.
     *
     * @param string $model
     * @param string $modelName
     * @param string $modelVariable
     * @param string $resourceClass
     * @return string
     */
    private function generateTypesenseSearchMethod(string $model, string $modelName, string $modelVariable, string $resourceClass): string
    {
        return <<<PHP
    /**
     * Search {$modelName} records using Typesense.
     *
     * @param \Illuminate\Http\Request \$request
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection|\Illuminate\Http\JsonResponse
     */
    public function search(\Illuminate\Http\Request \$request): \Illuminate\Http\Resources\Json\AnonymousResourceCollection|\Illuminate\Http\JsonResponse
    {
        \$query = \$request->get('q', '');
        \$filters = \$request->get('filters', '');
        \$sortBy = \$request->get('sort_by', '');
        
        if (empty(\$query)) {
            return {$resourceClass}::collection(collect([]));
        }

        try {
            \$searchParams = [
                'q' => \$query,
                'query_by' => '*',
                'per_page' => \$request->get('per_page', 20),
                'page' => \$request->get('page', 1),
            ];

            if (!empty(\$filters)) {
                \$searchParams['filter_by'] = \$filters;
            }

            if (!empty(\$sortBy)) {
                \$searchParams['sort_by'] = \$sortBy;
            }

            \$results = {$model}::search(\$query, function (\$typesense, \$query, \$options) use (\$searchParams) {
                return \$typesense->collections[\$options['index']]->documents->search(\$searchParams);
            })->paginate(\$request->get('per_page', 20));

            return {$resourceClass}::collection(\$results);
        } catch (\Exception \$e) {
            return response()->json([
                'message' => 'Search failed',
                'error' => \$e->getMessage(),
            ], 500);
        }
    }
PHP;
    }

    /**
     * Generate search route code.
     *
     * @param string $modelName Plural model name (snake_case)
     * @param string $controller Full controller class name
     * @param bool $withAuth Whether routes are authenticated
     * @return string Generated route code
     */
    public function generateSearchRoute(string $modelName, string $controller, bool $withAuth = false): string
    {
        $route = "Route::get('/{$modelName}/search', [{$controller}::class, 'search']);";
        
        if ($withAuth) {
            $middleware = config('laravel_auto_crud.authentication.middleware', 'auth:sanctum');
            return "Route::middleware('{$middleware}')->group(function () {\n    {$route}\n});";
        }
        
        return $route;
    }
}

