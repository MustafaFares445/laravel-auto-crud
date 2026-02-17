<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Services;

use Illuminate\Support\Facades\File;

class ModuleService
{
    public const MODULES_PATH = 'Modules';
    public const MODULES_NAMESPACE = 'Modules';

    /**
     * Check if nwidart/laravel-modules is installed and configured.
     *
     * @return bool
     */
    public static function isModulesInstalled(): bool
    {
        return class_exists(\Nwidart\Modules\Facades\Module::class) 
            && is_dir(base_path(self::MODULES_PATH));
    }

    /**
     * Get all available modules.
     *
     * @return array<string>
     */
    public static function getAllModules(): array
    {
        if (!self::isModulesInstalled()) {
            return [];
        }

        try {
            $moduleFacade = \Nwidart\Modules\Facades\Module::class;
            if (method_exists($moduleFacade, 'all')) {
                $modules = \Nwidart\Modules\Facades\Module::all();
                return array_keys($modules);
            }

            // Fallback: scan Modules directory manually
            $modulesPath = base_path(self::MODULES_PATH);
            if (!is_dir($modulesPath)) {
                return [];
            }

            $modules = [];
            $directories = File::directories($modulesPath);
            foreach ($directories as $dir) {
                $moduleName = basename($dir);
                if ($moduleName !== '.' && $moduleName !== '..' && !str_starts_with($moduleName, '.')) {
                    $modules[] = $moduleName;
                }
            }

            return $modules;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Get the base path for a module.
     *
     * @param string $moduleName
     * @return string
     */
    public static function getModulePath(string $moduleName): string
    {
        $baseModulesPath = config('laravel_auto_crud.modules.path', self::MODULES_PATH);
        return "{$baseModulesPath}/{$moduleName}";
    }

    /**
     * Get the root namespace for a module.
     *
     * @param string $moduleName
     * @return string
     */
    public static function getModuleNamespace(string $moduleName): string
    {
        $baseNamespace = config('laravel_auto_crud.modules.namespace', self::MODULES_NAMESPACE);
        return "{$baseNamespace}\\{$moduleName}";
    }

    /**
     * Resolve the models path for a module.
     *
     * @param string $moduleName
     * @return string
     */
    public static function resolveModelPath(string $moduleName): string
    {
        $customPaths = config('laravel_auto_crud.modules.custom_paths', []);
        $modelsSubPath = $customPaths['models'] ?? 'Models';
        return self::getModulePath($moduleName) . '/' . $modelsSubPath;
    }

    /**
     * Resolve file path for different file types within a module.
     *
     * @param string $moduleName
     * @param string $type File type: 'controllers', 'requests', 'resources', 'services', 'policies', 'factories', 'tests', 'routes', 'data', 'filters'
     * @return string
     */
    public static function resolveFilePath(string $moduleName, string $type): string
    {
        $customPaths = config('laravel_auto_crud.modules.custom_paths', []);

        $pathMap = [
            'controllers' => $customPaths['controllers'] ?? 'Http/Controllers',
            'requests' => $customPaths['requests'] ?? 'Http/Requests',
            'resources' => $customPaths['resources'] ?? 'Http/Resources',
            'services' => $customPaths['services'] ?? 'Services',
            'policies' => $customPaths['policies'] ?? 'Policies',
            'models' => $customPaths['models'] ?? 'Models',
            'factories' => $customPaths['factories'] ?? 'Database/Factories',
            'seeders' => $customPaths['seeders'] ?? 'Database/Seeders',
            'tests' => $customPaths['tests'] ?? 'Tests',
            'routes' => $customPaths['routes'] ?? 'Routes',
            'data' => $customPaths['data'] ?? 'Data',
            'filters' => $customPaths['filters'] ?? 'FilterBuilders',
            'traits' => $customPaths['traits'] ?? 'Traits',
        ];

        $subPath = $pathMap[$type] ?? 'Http/Controllers';

        return self::getModulePath($moduleName) . '/' . $subPath;
    }

    /**
     * Resolve the route file path for a module.
     *
     * @param string $moduleName
     * @param string $type 'api' or 'web'
     * @return string
     */
    public static function resolveRoutePath(string $moduleName, string $type = 'api'): string
    {
        return self::getModulePath($moduleName) . '/Routes/' . ($type === 'web' ? 'web' : 'api') . '.php';
    }

    /**
     * Check if a module exists.
     *
     * @param string $moduleName
     * @return bool
     */
    public static function moduleExists(string $moduleName): bool
    {
        return is_dir(base_path(self::getModulePath($moduleName)));
    }
}
