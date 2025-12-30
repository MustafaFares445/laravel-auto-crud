<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use ReflectionClass;

trait HasModelAttributes
{
    /**
     * Returns an array of key/value pairs from this Data instance
     * for only the model-fillable properties.
     *
     * Null values are excluded from the returned array.
     *
     * @return array<string, mixed> Array of non-null fillable attributes with snake_case keys
     */
    public function onlyModelAttributes(): array
    {
        $allowed = static::modelAttributesList();

        // Filter out null values, then convert keys to snake_case
        $dataArray = collect($this->toArray())
            ->filter(fn ($value) => $value !== null) // Exclude null values
            ->mapWithKeys(fn ($value, $key) => [Str::snake($key) => $value])
            ->all();

        // Return only the attributes that are in the model's fillable array
        return array_intersect_key(
            $dataArray,
            array_flip($allowed)
        );
    }

    
    /**
     * Sync a relationship if the IDs array is set (not null).
     *
     * @param Model $model The model instance to sync relationships on
     * @param string $relation The relationship name to sync
     * @param array|null $ids Array of IDs to sync, or null to skip
     * @return void
     */
    public function syncIfSet(Model $model, string $relation, ?array $ids): void
    {
        if (isset($ids)) {
            $model->{$relation}()->sync($ids);
        }
    }

    /**
     * Get the model attributes that are fillable and exist in this Data class.
     *
     * @return array<string>
     */
    private static function modelAttributesList(): array
    {
        if (property_exists(static::class, 'modelAttributes') && is_array(static::$modelAttributes)) {
            return static::$modelAttributes;
        }

        return static::modelAttributesFromModel();
    }

    /**
     * Get model attributes from the model class.
     *
     * @return array<string>
     * @throws \RuntimeException If model class is invalid or cannot be instantiated
     */
    private static function modelAttributesFromModel(): array
    {
        if (!property_exists(static::class, 'model') || !isset(static::$model)) {
            throw new \RuntimeException('Model property is not defined in ' . static::class);
        }

        /** @var class-string<Model> $modelClass */
        $modelClass = static::$model;

        if (!class_exists($modelClass)) {
            throw new \RuntimeException("Model class does not exist: {$modelClass}");
        }

        if (!is_subclass_of($modelClass, Model::class)) {
            throw new \RuntimeException("Class {$modelClass} is not a valid Eloquent model");
        }

        try {
            $reflection = new ReflectionClass(static::class);
            $dataProps = collect($reflection->getProperties())
                ->pluck('name')
                ->map(fn ($prop) => Str::snake($prop));

            // Try to instantiate model - catch if constructor requires parameters
            try {
                $modelReflection = new ReflectionClass($modelClass);
                $constructor = $modelReflection->getConstructor();
                
                // If constructor requires parameters, we can't instantiate
                if ($constructor && $constructor->getNumberOfRequiredParameters() > 0) {
                    // Fallback: use reflection to get fillable without instantiation
                    if ($modelReflection->hasProperty('fillable')) {
                        $fillableProperty = $modelReflection->getProperty('fillable');
                        $fillableProperty->setAccessible(true);
                        $fillable = $fillableProperty->getValue($modelReflection->newInstanceWithoutConstructor()) ?? [];
                    } else {
                        $fillable = [];
                    }
                } else {
                    /** @var Model $modelInstance */
                    $modelInstance = new $modelClass;
                    $fillable = $modelInstance->getFillable();
                }
            } catch (\Throwable $e) {
                // If instantiation fails, try to get fillable via reflection
                $modelReflection = new ReflectionClass($modelClass);
                if ($modelReflection->hasProperty('fillable')) {
                    $fillableProperty = $modelReflection->getProperty('fillable');
                    $fillableProperty->setAccessible(true);
                    $fillable = $fillableProperty->getValue($modelReflection->newInstanceWithoutConstructor()) ?? [];
                } else {
                    $fillable = [];
                }
            }

            return $dataProps->intersect($fillable)->values()->all();
        } catch (\ReflectionException $e) {
            throw new \RuntimeException("Failed to reflect on class: {$e->getMessage()}", 0, $e);
        }
    }
}
