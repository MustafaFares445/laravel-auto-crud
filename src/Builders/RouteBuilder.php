<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Builders;

use Illuminate\Support\Str;
use Mrmarchone\LaravelAutoCrud\Services\HelperService;

class RouteBuilder
{
    public function create(string $modelName, string $controller, array $types, bool $hasSoftDeletes = false): void
    {
        $modelName = HelperService::toSnakeCase(Str::plural($modelName));

        if (in_array('api', $types)) {
            $routesPath = base_path('routes/api.php');
            $routeCode = $this->generateApiRouteCode($modelName, $controller, $hasSoftDeletes);
            $this->createRoutes($routesPath, $routeCode);
        }

        if (in_array('web', $types)) {
            $routesPath = base_path('routes/web.php');
            $routeCode = "Route::resource('/{$modelName}', {$controller}::class);";
            $this->createRoutes($routesPath, $routeCode);
        }
    }

    /**
     * Generate API route code with optional soft delete routes.
     *
     * @param string $modelName Plural model name (snake_case)
     * @param string $controller Full controller class name
     * @param bool $hasSoftDeletes Whether model uses soft deletes
     * @return string Generated route code
     */
    private function generateApiRouteCode(string $modelName, string $controller, bool $hasSoftDeletes = false): string
    {
        $apiResource = "Route::apiResource('/{$modelName}', {$controller}::class);";
        $softDeleteRoutes = '';

        if ($hasSoftDeletes) {
            $softDeleteRoutes = "\nRoute::post('/{$modelName}/{id}/restore', [{$controller}::class, 'restore']);";
            $softDeleteRoutes .= "\nRoute::delete('/{$modelName}/{id}/force-delete', [{$controller}::class, 'forceDelete']);";
        }

        return $apiResource . $softDeleteRoutes;
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
