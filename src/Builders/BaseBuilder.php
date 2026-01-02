<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Builders;

use Mrmarchone\LaravelAutoCrud\Services\FileService;

abstract class BaseBuilder
{
    protected FileService $fileService;

    public function __construct(FileService $fileService)
    {
        $this->fileService = $fileService;
    }

    protected function getFullModelNamespace(array $modelData): string
    {
        return $modelData['namespace'] ? $modelData['namespace'].'\\'.$modelData['modelName'] : $modelData['modelName'];
    }

    /**
     * Get hidden properties from model.
     *
     * @param string $modelClass Full model class name
     * @return array<string> Array of hidden property names
     */
    protected function getHiddenProperties(string $modelClass): array
    {
        try {
            if (!class_exists($modelClass)) {
                return [];
            }

            $reflection = new \ReflectionClass($modelClass);
            
            // Try to get $hidden property via reflection
            if ($reflection->hasProperty('hidden')) {
                $hiddenProperty = $reflection->getProperty('hidden');
                $hiddenProperty->setAccessible(true);
                
                // Try to get value without instantiating
                try {
                    $modelInstance = $reflection->newInstanceWithoutConstructor();
                    $hidden = $hiddenProperty->getValue($modelInstance);
                    if (is_array($hidden)) {
                        return $hidden;
                    }
                } catch (\Throwable $e) {
                    // If instantiation fails, try reading from file
                }
            }

            // Fallback: Read from model file
            $modelFile = $reflection->getFileName();
            if ($modelFile && file_exists($modelFile)) {
                $content = \Illuminate\Support\Facades\File::get($modelFile);
                return $this->extractHiddenFromContent($content);
            }
        } catch (\Throwable $e) {
            // Ignore errors and return empty array
        }

        return [];
    }

    /**
     * Extract hidden properties from model file content.
     *
     * @param string $content Model file content
     * @return array<string> Array of hidden property names
     */
    protected function extractHiddenFromContent(string $content): array
    {
        $hidden = [];

        // Match: protected $hidden = ['password', 'remember_token'];
        // Match: protected array $hidden = ['password', 'remember_token'];
        if (preg_match('/protected\s+(?:array\s+)?\$hidden\s*=\s*\[(.*?)\];/s', $content, $matches)) {
            $arrayContent = $matches[1];
            
            // Extract string values from array
            if (preg_match_all("/['\"]([^'\"]+)['\"]/", $arrayContent, $valueMatches)) {
                $hidden = $valueMatches[1];
            }
        }

        return $hidden;
    }
}