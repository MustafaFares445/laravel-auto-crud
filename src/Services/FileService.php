<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Services;

use Illuminate\Support\Facades\File;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;

class FileService
{
    /**
     * Create a file from a stub template.
     *
     * @param array<string, mixed> $modelData Model information including 'modelName', 'namespace', 'folders'
     * @param string $stubType The stub file name without extension
     * @param string $basePath Base path for the generated file (e.g., 'Http/Controllers/API')
     * @param string $suffix Suffix to append to model name (e.g., 'Controller', 'Service')
     * @param bool $overwrite Whether to overwrite existing files
     * @param callable|null $dataCallback Optional callback to generate replacement data
     * @param string|null $module Optional module name for generating within a module
     * @return string Full namespace path of the created file
     * @throws \RuntimeException If stub file is not found
     */
    public function createFromStub(array $modelData, string $stubType, string $basePath, string $suffix, bool $overwrite = false, ?callable $dataCallback = null, ?string $module = null): string
    {
        // Check custom stub path, project stubs directory, then fall back to package stubs
        $customStubPath = config('laravel_auto_crud.custom_stub_path');
        $projectStubPath = $customStubPath 
            ? rtrim($customStubPath, '/') . "/{$stubType}.stub"
            : base_path("stubs/{$stubType}.stub");
        $packageStubPath = __DIR__ . "/../Stubs/{$stubType}.stub";
        
        $stubPath = file_exists($projectStubPath) ? $projectStubPath : $packageStubPath;
        
        if (!file_exists($stubPath)) {
            throw new \RuntimeException("Stub file not found: {$stubType}.stub. Checked: {$projectStubPath} and {$packageStubPath}");
        }
        
        $isTest = str_starts_with($basePath, 'tests') || str_starts_with($basePath, 'Tests');
        
        if ($module) {
            $baseNamespace = \Mrmarchone\LaravelAutoCrud\Services\ModuleService::getModuleNamespace($module);
            $namespace = $baseNamespace . '\\' . str_replace('/', '\\', $basePath);
        } else {
            $namespace = $isTest ? 'Tests\\' : 'App\\';
            $namespace .= str_replace('/', '\\', $isTest ? $basePath : $basePath);
        }

        if ($modelData['folders']) {
            $namespace .= '\\'.str_replace('/', '\\', $modelData['folders']);
        }

        $filePath = $this->generateFilePath($modelData, $basePath, $suffix, $module);

        if (! $overwrite) {
            if (file_exists($filePath)) {
                $overwrite = confirm(
                    label: ucfirst($suffix).' file already exists, do you want to overwrite it? '.$filePath
                );
                if (! $overwrite) {
                    return $namespace.'\\'.$modelData['modelName'].$suffix;
                }
            }
        }

        File::ensureDirectoryExists(dirname($filePath), 0777, true);

        $data = $dataCallback ? $dataCallback($modelData) : [];
        $content = $this->generateContent($stubPath, $modelData, $namespace, $suffix, $data);

        File::put($filePath, $content);

        info("Created: $filePath");

        return $namespace.'\\'.$modelData['modelName'].$suffix;
    }

    /**
     * Generate the file path for the generated file.
     *
     * @param array<string, mixed> $modelData Model information
     * @param string $basePath Base path for the file
     * @param string $suffix File suffix
     * @param string|null $module Optional module name
     * @return string Full file path
     * @throws \InvalidArgumentException If path contains invalid characters
     */
    private function generateFilePath(array $modelData, string $basePath, string $suffix, ?string $module = null): string
    {
        // Sanitize path components to prevent directory traversal while preserving valid structure
        $basePath = $this->sanitizePath($basePath);
        $modelName = $this->sanitizeFileName($modelData['modelName']);
        $suffix = $this->sanitizeFileName($suffix);
        
        $isTest = str_starts_with($basePath, 'tests') || str_starts_with($basePath, 'Tests');
        
        if ($module) {
            $modulePath = \Mrmarchone\LaravelAutoCrud\Services\ModuleService::getModulePath($module);
            
            if (!empty($modelData['folders'])) {
                $folders = $this->sanitizePath($modelData['folders']);
                $path = base_path("{$modulePath}/{$basePath}/{$folders}/{$modelName}{$suffix}.php");
            } else {
                $path = base_path("{$modulePath}/{$basePath}/{$modelName}{$suffix}.php");
            }
        } else {
            if (!empty($modelData['folders'])) {
                $folders = $this->sanitizePath($modelData['folders']);
                $path = $isTest 
                    ? base_path("{$basePath}/{$folders}/{$modelName}{$suffix}.php")
                    : app_path("{$basePath}/{$folders}/{$modelName}{$suffix}.php");
            } else {
                $path = $isTest
                    ? base_path("{$basePath}/{$modelName}{$suffix}.php")
                    : app_path("{$basePath}/{$modelName}{$suffix}.php");
            }
        }
        
        // Additional validation: ensure path is within allowed directories
        $allowedBase = $module ? base_path(\Mrmarchone\LaravelAutoCrud\Services\ModuleService::getModulePath($module)) : ($isTest ? base_path() : app_path());
        
        // Create directory if it doesn't exist for validation
        $dirPath = dirname($path);
        if (!is_dir($dirPath)) {
            // Try to create it to see if the path is valid
            try {
                File::ensureDirectoryExists($dirPath, 0755, true);
            } catch (\Exception $e) {
                throw new \InvalidArgumentException("Invalid file path generated: {$path}. Error: {$e->getMessage()}");
            }
        }
        
        $realPath = realpath($dirPath);
        $realAllowedBase = realpath($allowedBase);
        
        if ($realPath === false || ($realAllowedBase !== false && !str_starts_with($realPath, $realAllowedBase))) {
            throw new \InvalidArgumentException("Invalid file path generated: {$path}. Real path: " . ($realPath ?: 'null') . ", Allowed base: " . ($realAllowedBase ?: 'null'));
        }
        
        return $path;
    }
    
    /**
     * Sanitize file name to prevent invalid characters.
     * This is for file names only, not paths.
     *
     * @param string $fileName File name to sanitize
     * @return string Sanitized file name
     */
    private function sanitizeFileName(string $fileName): string
    {
        // Remove directory traversal attempts
        $fileName = str_replace(['../', '..\\'], '', $fileName);
        // Remove path separators from file names
        $fileName = str_replace(['/', '\\'], '', $fileName);
        // Remove any null bytes
        $fileName = str_replace("\0", '', $fileName);
        return $fileName;
    }
    
    /**
     * Sanitize path to prevent directory traversal attacks.
     *
     * @param string $path Path to sanitize
     * @return string Sanitized path
     */
    private function sanitizePath(string $path): string
    {
        // Remove any directory traversal attempts (../ and ..\)
        $path = str_replace(['../', '..\\'], '', $path);
        // Normalize path separators to forward slashes
        $path = str_replace('\\', '/', $path);
        // Remove any null bytes
        $path = str_replace("\0", '', $path);
        // Remove any leading/trailing slashes and clean up multiple slashes
        $path = trim($path, '/');
        $path = preg_replace('#/+#', '/', $path);
        return $path;
    }

    /**
     * Generate file content from stub template.
     *
     * @param string $stubPath Path to stub file
     * @param array<string, mixed> $modelData Model information
     * @param string $namespace Generated namespace
     * @param string $suffix File suffix
     * @param array<string, string> $data Additional replacement data
     * @return string Generated file content
     * @throws \RuntimeException If stub file cannot be read
     */
    private function generateContent(string $stubPath, array $modelData, string $namespace, string $suffix, array $data = []): string
    {
        $stubContent = @file_get_contents($stubPath);
        
        if ($stubContent === false) {
            throw new \RuntimeException("Cannot read stub file: {$stubPath}");
        }
        
        $replacements = [
            '{{ class }}' => $modelData['modelName'].$suffix,
            '{{ namespace }}' => $namespace,
            ...$data,
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $stubContent);
    }
}
