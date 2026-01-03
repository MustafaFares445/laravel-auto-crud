<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Builders;

class PulseBuilder extends BaseBuilder
{
    /**
     * Check if Laravel Pulse is installed.
     *
     * @return bool
     */
    public function isInstalled(): bool
    {
        return class_exists(\Laravel\Pulse\Pulse::class)
            || class_exists(\Laravel\Pulse\PulseServiceProvider::class);
    }

    /**
     * Generate Pulse recording code for a controller method.
     *
     * @param string $operation Operation name
     * @param array<string, mixed> $modelData Model information
     * @return string Generated Pulse recording code
     */
    public function generatePulseRecording(string $operation, array $modelData): string
    {
        $modelName = $modelData['modelName'];
        $eventName = "{$modelName}.{$operation}";

        return <<<PHP
        \Laravel\Pulse\Facades\Pulse::record(
            type: 'crud_operation',
            key: '{$eventName}',
            value: 1,
            timestamp: now()->getTimestampMs()
        )->tag('model', '{$modelName}')
         ->tag('operation', '{$operation}');
PHP;
    }

    /**
     * Generate Pulse metrics endpoint method.
     *
     * @param array<string, mixed> $modelData Model information
     * @return string Generated metrics method code
     */
    public function generateMetricsMethod(array $modelData): string
    {
        $modelName = $modelData['modelName'];

        return <<<PHP
    /**
     * Get metrics for {$modelName} operations.
     *
     * @param \Illuminate\Http\Request \$request
     * @return \Illuminate\Http\JsonResponse
     */
    public function metrics(\Illuminate\Http\Request \$request): \Illuminate\Http\JsonResponse
    {
        \$period = \$request->get('period', '1h');
        \$operation = \$request->get('operation');

        \$query = \Laravel\Pulse\Facades\Pulse::values('crud_operation')
            ->where('tag', 'model', '{$modelName}');

        if (\$operation) {
            \$query->where('tag', 'operation', \$operation);
        }

        \$metrics = \$query->aggregate(
            period: \$period,
            aggregate: 'sum'
        );

        return response()->json([
            'model' => '{$modelName}',
            'period' => \$period,
            'operation' => \$operation,
            'metrics' => \$metrics,
        ]);
    }
PHP;
    }

    /**
     * Generate metrics route code.
     *
     * @param string $modelName Plural model name (snake_case)
     * @param string $controller Full controller class name
     * @param bool $withAuth Whether routes are authenticated
     * @return string Generated route code
     */
    public function generateMetricsRoute(string $modelName, string $controller, bool $withAuth = false): string
    {
        $route = "Route::get('/{$modelName}/metrics', [{$controller}::class, 'metrics']);";
        
        if ($withAuth) {
            $middleware = config('laravel_auto_crud.authentication.middleware', 'auth:sanctum');
            return "Route::middleware('{$middleware}')->group(function () {\n    {$route}\n});";
        }
        
        return $route;
    }
}

