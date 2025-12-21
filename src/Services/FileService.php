<?php

namespace Mrmarchone\LaravelAutoCrud\Services;

use Illuminate\Support\Facades\File;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;

class FileService
{
    public function createFromStub(array $modelData, string $stubType, string $basePath, string $suffix, bool $overwrite = false, ?callable $dataCallback = null): string
    {
        // Check project stubs directory first, then fall back to vendor stubs
        $projectStubPath = base_path("stubs/{$stubType}.stub");
        $vendorStubPath = base_path("vendor/mustafafares/laravel-auto-crud/src/Stubs/{$stubType}.stub");
        
        $stubPath = file_exists($projectStubPath) ? $projectStubPath : $vendorStubPath;
        
        $isTest = str_starts_with($basePath, 'tests');
        $namespace = $isTest ? 'Tests\\' : 'App\\';
        $namespace .= str_replace('/', '\\', $isTest ? $basePath : $basePath);

        if ($modelData['folders']) {
            $namespace .= '\\'.str_replace('/', '\\', $modelData['folders']);
        }

        $filePath = $this->generateFilePath($modelData, $basePath, $suffix);

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

    private function generateFilePath(array $modelData, string $basePath, string $suffix): string
    {
        $isTest = str_starts_with($basePath, 'tests');
        
        if ($modelData['folders']) {
            return $isTest 
                ? base_path("{$basePath}/{$modelData['folders']}/{$modelData['modelName']}{$suffix}.php")
                : app_path("{$basePath}/{$modelData['folders']}/{$modelData['modelName']}{$suffix}.php");
        }

        return $isTest
            ? base_path("{$basePath}/{$modelData['modelName']}{$suffix}.php")
            : app_path("{$basePath}/{$modelData['modelName']}{$suffix}.php");
    }

    private function generateContent(string $stubPath, array $modelData, string $namespace, string $suffix, array $data = []): string
    {
        $replacements = [
            '{{ class }}' => $modelData['modelName'].$suffix,
            '{{ namespace }}' => $namespace,
            ...$data,
        ];

        return str_replace(array_keys($replacements), array_values($replacements), file_get_contents($stubPath));
    }
}
