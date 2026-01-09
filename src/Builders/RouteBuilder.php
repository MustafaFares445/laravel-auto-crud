<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Builders;

use Illuminate\Support\Str;
use Mrmarchone\LaravelAutoCrud\Services\HelperService;

class RouteBuilder
{
    public function create(string $modelName, string $controller, array $types, bool $hasSoftDeletes = false, array $bulkEndpoints = []): void
    {
        $modelName = HelperService::toSnakeCase(Str::plural($modelName));

        if (in_array('api', $types)) {
            $routesPath = base_path('routes/api.php');
            $routeCode = $this->generateApiRouteCode($modelName, $controller, $hasSoftDeletes, $bulkEndpoints);
            $this->createRoutes($routesPath, $routeCode);
        }

        if (in_array('web', $types)) {
            $routesPath = base_path('routes/web.php');
            $routeCode = $this->generateWebRouteCode($modelName, $controller, $bulkEndpoints);
            $this->createRoutes($routesPath, $routeCode);
        }
    }

    /**
     * Generate API route code with optional soft delete routes and bulk routes.
     *
     * @param string $modelName Plural model name (snake_case)
     * @param string $controller Full controller class name
     * @param bool $hasSoftDeletes Whether model uses soft deletes
     * @param array $bulkEndpoints Selected bulk endpoints (create, update, delete)
     * @return string Generated route code
     */
    private function generateApiRouteCode(string $modelName, string $controller, bool $hasSoftDeletes = false, array $bulkEndpoints = []): string
    {
        $apiResource = "Route::apiResource('/{$modelName}', {$controller}::class);";
        $softDeleteRoutes = '';
        $bulkRoutes = '';

        if ($hasSoftDeletes) {
            $softDeleteRoutes = "\nRoute::post('/{$modelName}/{id}/restore', [{$controller}::class, 'restore']);";
            $softDeleteRoutes .= "\nRoute::delete('/{$modelName}/{id}/force-delete', [{$controller}::class, 'forceDelete']);";
        }

        if (in_array('create', $bulkEndpoints, true)) {
            $bulkRoutes .= "\nRoute::post('/{$modelName}/bulk', [{$controller}::class, 'bulkStore']);";
        }

        if (in_array('update', $bulkEndpoints, true)) {
            $bulkRoutes .= "\nRoute::put('/{$modelName}/bulk', [{$controller}::class, 'bulkUpdate']);";
        }

        if (in_array('delete', $bulkEndpoints, true)) {
            $bulkRoutes .= "\nRoute::delete('/{$modelName}/bulk', [{$controller}::class, 'bulkDestroy']);";
        }

        return $apiResource . $softDeleteRoutes . $bulkRoutes;
    }

    /**
     * Generate Web route code with bulk routes.
     *
     * @param string $modelName Plural model name (snake_case)
     * @param string $controller Full controller class name
     * @param array $bulkEndpoints Selected bulk endpoints (create, update, delete)
     * @return string Generated route code
     */
    private function generateWebRouteCode(string $modelName, string $controller, array $bulkEndpoints = []): string
    {
        $resourceRoute = "Route::resource('/{$modelName}', {$controller}::class);";
        $bulkRoutes = '';

        if (in_array('create', $bulkEndpoints, true)) {
            $bulkRoutes .= "\nRoute::post('/{$modelName}/bulk', [{$controller}::class, 'bulkStore']);";
        }

        if (in_array('update', $bulkEndpoints, true)) {
            $bulkRoutes .= "\nRoute::put('/{$modelName}/bulk', [{$controller}::class, 'bulkUpdate']);";
        }

        if (in_array('delete', $bulkEndpoints, true)) {
            $bulkRoutes .= "\nRoute::delete('/{$modelName}/bulk', [{$controller}::class, 'bulkDestroy']);";
        }

        return $resourceRoute . $bulkRoutes;
    }

    /**
     * Create bulk routes for bulk controller.
     *
     * @param string $modelName Model name (singular)
     * @param string $controller Full controller class name
     * @param array $types Controller types (api, web)
     * @param array $bulkEndpoints Selected bulk endpoints (create, update, delete)
     * @return void
     */
    public function createBulkRoutes(string $modelName, string $controller, array $types, array $bulkEndpoints = []): void
    {
        $modelName = HelperService::toSnakeCase(Str::plural($modelName));

        if (in_array('api', $types)) {
            $routesPath = base_path('routes/api.php');
            $routeCode = $this->generateBulkApiRouteCode($modelName, $controller, $bulkEndpoints);
            $this->createRoutes($routesPath, $routeCode);
        }

        if (in_array('web', $types)) {
            $routesPath = base_path('routes/web.php');
            $routeCode = $this->generateBulkWebRouteCode($modelName, $controller, $bulkEndpoints);
            $this->createRoutes($routesPath, $routeCode);
        }
    }

    /**
     * Generate API bulk route code.
     *
     * @param string $modelName Plural model name (snake_case)
     * @param string $controller Full controller class name
     * @param array $bulkEndpoints Selected bulk endpoints (create, update, delete)
     * @return string Generated route code
     */
    private function generateBulkApiRouteCode(string $modelName, string $controller, array $bulkEndpoints = []): string
    {
        $bulkRoutes = '';

        if (in_array('create', $bulkEndpoints, true)) {
            $bulkRoutes .= "Route::post('/{$modelName}/bulk', [{$controller}::class, 'bulkStore']);";
        }

        if (in_array('update', $bulkEndpoints, true)) {
            $bulkRoutes .= "\nRoute::put('/{$modelName}/bulk', [{$controller}::class, 'bulkUpdate']);";
        }

        if (in_array('delete', $bulkEndpoints, true)) {
            $bulkRoutes .= "\nRoute::delete('/{$modelName}/bulk', [{$controller}::class, 'bulkDestroy']);";
        }

        return $bulkRoutes;
    }

    /**
     * Generate Web bulk route code.
     *
     * @param string $modelName Plural model name (snake_case)
     * @param string $controller Full controller class name
     * @param array $bulkEndpoints Selected bulk endpoints (create, update, delete)
     * @return string Generated route code
     */
    private function generateBulkWebRouteCode(string $modelName, string $controller, array $bulkEndpoints = []): string
    {
        $bulkRoutes = '';

        if (in_array('create', $bulkEndpoints, true)) {
            $bulkRoutes .= "Route::post('/{$modelName}/bulk', [{$controller}::class, 'bulkStore']);";
        }

        if (in_array('update', $bulkEndpoints, true)) {
            $bulkRoutes .= "\nRoute::put('/{$modelName}/bulk', [{$controller}::class, 'bulkUpdate']);";
        }

        if (in_array('delete', $bulkEndpoints, true)) {
            $bulkRoutes .= "\nRoute::delete('/{$modelName}/bulk', [{$controller}::class, 'bulkDestroy']);";
        }

        return $bulkRoutes;
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
