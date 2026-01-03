<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use ReflectionClass;

class SoftDeleteDetector
{
    /**
     * Check if a model uses SoftDeletes trait.
     *
     * @param string $modelClass Full class name of the model
     * @return bool
     */
    public static function hasSoftDeletes(string $modelClass): bool
    {
        if (!class_exists($modelClass)) {
            return false;
        }

        try {
            $reflection = new ReflectionClass($modelClass);
            
            if (!$reflection->isSubclassOf(Model::class)) {
                return false;
            }

            $traits = $reflection->getTraitNames();
            
            foreach ($traits as $trait) {
                if (str_contains($trait, 'SoftDeletes')) {
                    return true;
                }
            }

            // Also check parent classes
            $parentClass = $reflection->getParentClass();
            while ($parentClass !== false) {
                $parentTraits = $parentClass->getTraitNames();
                foreach ($parentTraits as $trait) {
                    if (str_contains($trait, 'SoftDeletes')) {
                        return true;
                    }
                }
                $parentClass = $parentClass->getParentClass();
            }

            return false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Check if a model instance uses SoftDeletes trait.
     *
     * @param Model $model Model instance
     * @return bool
     */
    public static function modelUsesSoftDeletes(Model $model): bool
    {
        return in_array(SoftDeletes::class, class_uses_recursive($model), true);
    }
}

