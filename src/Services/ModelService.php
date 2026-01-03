<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Mrmarchone\LaravelAutoCrud\Exceptions\GenerationException;
use Mrmarchone\LaravelAutoCrud\Exceptions\ModelNotFoundException;

use function Laravel\Prompts\multiselect;

class ModelService
{
    public static function getAvailableModels(string $modelsPath): array
    {
        return self::getAllModels($modelsPath)
            ->filter(function ($fullNamespace) {
                if (! $fullNamespace) {
                    return false;
                }

                if (! class_exists($fullNamespace)) {
                    return false;
                }

                return is_subclass_of($fullNamespace, Model::class);
            })
            ->values()
            ->toArray();
    }

    public static function showModels(string $modelsPath): ?array
    {
        $models = self::getAvailableModels($modelsPath);

        return count($models) ? multiselect(label: 'Select your model, use your space-bar to select.', options: $models) : null;
    }

    /**
     * Resolve model name into structured data.
     *
     * @param string $modelName Full model class name with namespace
     * @return array{modelName: string, folders: string|null, namespace: string|null}
     */
    public static function resolveModelName(string $modelName): array
    {
        $parts = explode('\\', $modelName);

        return [
            'modelName' => array_pop($parts),
            'folders' => implode('/', $parts) !== 'App/Models' ? implode('/', $parts) : null,
            'namespace' => str_replace('/', '\\', implode('/', $parts)) ?: null,
        ];
    }

    public static function isModelExists(string $modelName, string $modelsPath): ?string
    {
        return self::getAllModels($modelsPath)
            ->filter(function ($fullNamespace) use ($modelName) {
                if (! $fullNamespace) {
                    return false;
                }

                // Check if the class name matches (without namespace)
                $classParts = explode('\\', $fullNamespace);
                $actualClassName = end($classParts);

                return $actualClassName === $modelName;
            })
            ->first();
    }

    public static function getFullModelNamespace(array $modelData): string
    {
        if (isset($modelData['namespace']) && $modelData['namespace']) {
            return $modelData['namespace'].'\\'.$modelData['modelName'];
        }

        return $modelData['modelName'];
    }

    /**
     * Get table name for the model.
     *
     * @param array<string, mixed> $modelData Model information
     * @param callable|null $modelFactory Optional factory function to create model instance
     * @return string Table name
     * @throws InvalidArgumentException If model is invalid
     */
    public static function getTableName(array $modelData, ?callable $modelFactory = null): string
    {
        $modelName = self::getFullModelNamespace($modelData);

        if (!class_exists($modelName)) {
            throw new \Mrmarchone\LaravelAutoCrud\Exceptions\ModelNotFoundException($modelName);
        }

        try {
            // Use the Factory if provided, otherwise create the object normally
            $model = $modelFactory ? $modelFactory($modelName) : new $modelName;

            if (!($model instanceof Model)) {
                throw new ModelNotFoundException($modelName, "Class {$modelName} is not an Eloquent model");
            }

            return $model->getTable();
        } catch (\Throwable $e) {
            if ($e instanceof ModelNotFoundException) {
                throw $e;
            }
            throw new GenerationException(
                'table name retrieval',
                $modelName,
                $e->getMessage(),
                $e
            );
        }
    }

    public static function handleModelsPath(string $modelsPath): string
    {
        return str_ends_with($modelsPath, '/') ? $modelsPath : $modelsPath.DIRECTORY_SEPARATOR;
    }

    /**
     * Get all model classes from the specified path.
     *
     * @param string $modelsPath Path to models directory
     * @return \Illuminate\Support\Collection<int, string|null>
     */
    private static function getAllModels(string $modelsPath): \Illuminate\Support\Collection
    {
        $modelsPath = static::handleModelsPath($modelsPath);
        $fullPath = static::getModelNameFromPath($modelsPath);

        if (!is_dir($fullPath)) {
            return collect([]);
        }

        try {
            return collect(File::allFiles($fullPath))->map(function ($file) {
                try {
                    $content = static::getClassContent($file->getRealPath());
                    if ($content === false) {
                        return null;
                    }
                    
                    $namespace = '';
                    if (preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
                        $namespace = trim($matches[1]);
                    }
                    $className = pathinfo($file->getFilename(), PATHINFO_FILENAME);

                    return $namespace ? $namespace.'\\'.$className : null;
                } catch (\Throwable $e) {
                    // Skip files that can't be read
                    return null;
                }
            })->filter();
        } catch (\Throwable $e) {
            // Return empty collection if directory can't be read
            return collect([]);
        }
    }

    private static function getModelNameFromPath(string $modelsPath): string
    {
        return base_path($modelsPath);
    }

    private static function getClassContent($file): false|string
    {
        return File::get($file);
    }
}
