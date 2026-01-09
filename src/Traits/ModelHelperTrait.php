<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Traits;

trait ModelHelperTrait
{
    protected function getFullModelNamespace(array $modelData): string
    {
        $namespace = $modelData['namespace'] ?? null;
        $modelName = $modelData['modelName'] ?? '';
        
        return $namespace ? $namespace.'\\'.$modelName : $modelName;
    }

    protected function getHiddenProperties(string $modelClass): array
    {
        try {
            if (!class_exists($modelClass)) {
                return [];
            }

            $reflection = new \ReflectionClass($modelClass);
            
            if ($reflection->hasProperty('hidden')) {
                $hiddenProperty = $reflection->getProperty('hidden');
                $hiddenProperty->setAccessible(true);
                
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

    protected function extractHiddenFromContent(string $content): array
    {
        $hidden = [];

        if (preg_match('/protected\s+(?:array\s+)?\$hidden\s*=\s*\[(.*?)\];/s', $content, $matches)) {
            $arrayContent = $matches[1];
            
            if (preg_match_all("/['\"]([^'\"]+)['\"]/", $arrayContent, $valueMatches)) {
                $hidden = $valueMatches[1];
            }
        }

        return $hidden;
    }
}

