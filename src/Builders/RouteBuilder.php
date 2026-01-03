<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Builders;

use Illuminate\Support\Str;
use Mrmarchone\LaravelAutoCrud\Services\HelperService;

class RouteBuilder
{
    public function create(string $modelName, string $controller, array $types, bool $withAuth = false, bool $hasSoftDeletes = false, ?string $apiVersion = null): void
    {
        $modelName = HelperService::toSnakeCase(Str::plural($modelName));

        if (in_array('api', $types)) {
            $routesPath = base_path('routes/api.php');
            $routeCode = $this->generateApiRouteCode($modelName, $controller, $withAuth, $hasSoftDeletes, $apiVersion);
            $this->createRoutes($routesPath, $routeCode);
        }

        if (in_array('web', $types)) {
            $routesPath = base_path('routes/web.php');
            $routeCode = "Route::resource('/{$modelName}', {$controller}::class);";
            $this->createRoutes($routesPath, $routeCode);
        }
    }

    /**
     * Generate API route code with optional authentication middleware.
     *
     * @param string $modelName Plural model name (snake_case)
     * @param string $controller Full controller class name
     * @param bool $withAuth Whether to wrap routes with auth middleware
     * @param bool $hasSoftDeletes Whether model uses soft deletes
     * @param string|null $apiVersion API version prefix (e.g., v1, v2)
     * @return string Generated route code
     */
    private function generateApiRouteCode(string $modelName, string $controller, bool $withAuth, bool $hasSoftDeletes = false, ?string $apiVersion = null): string
    {
        $middleware = config('laravel_auto_crud.authentication.middleware', 'auth:sanctum');
        
        // Build route path with optional version prefix
        $routePath = $modelName;
        if ($apiVersion) {
            $routePath = $apiVersion . '/' . $routePath;
        }
        
        $resourceRoute = "Route::apiResource('/{$routePath}', {$controller}::class);";
        
        if ($hasSoftDeletes) {
            $softDeleteRoutes = "\n    Route::post('/{$routePath}/{id}/restore', [{$controller}::class, 'restore']);\n    Route::delete('/{$routePath}/{id}/force', [{$controller}::class, 'forceDelete']);";
            $resourceRoute .= $softDeleteRoutes;
        }
        
        if ($withAuth && $this->isSanctumInstalled()) {
            return "Route::middleware('{$middleware}')->group(function () {\n    {$resourceRoute}\n});";
        }
        
        return $resourceRoute;
    }

    /**
     * Check if Laravel Sanctum is installed.
     *
     * @return bool
     */
    private function isSanctumInstalled(): bool
    {
        return class_exists(\Laravel\Sanctum\SanctumServiceProvider::class) 
            || class_exists(\Laravel\Sanctum\HasApiTokens::class);
    }

    /**
     * Create or append routes to the routes file.
     *
     * @param string $routesPath Path to routes file
     * @param string $routeCode Route code to add
     * @return void
     * @throws \RuntimeException If routes file cannot be read or written
     */
    private function createRoutes(string $routesPath, string $routeCode): void
    {
        try {
            if (! file_exists($routesPath)) {
                $initialContent = "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n";
                if (@file_put_contents($routesPath, $initialContent) === false) {
                    throw new \RuntimeException("Cannot create routes file: {$routesPath}");
                }
            }

            if (!is_readable($routesPath)) {
                throw new \RuntimeException("Routes file is not readable: {$routesPath}");
            }

            $content = @file_get_contents($routesPath);
            if ($content === false) {
                throw new \RuntimeException("Cannot read routes file: {$routesPath}");
            }

            if (strpos($content, $routeCode) === false) {
                if (!is_writable($routesPath)) {
                    throw new \RuntimeException("Routes file is not writable: {$routesPath}");
                }
                if (@file_put_contents($routesPath, "\n".$routeCode."\n", FILE_APPEND) === false) {
                    throw new \RuntimeException("Cannot write to routes file: {$routesPath}");
                }
            }
        } catch (\Throwable $e) {
            if ($e instanceof \RuntimeException) {
                throw $e;
            }
            throw new \RuntimeException("Error processing routes file: {$e->getMessage()}", 0, $e);
        }
    }
}
