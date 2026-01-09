<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Services;

use Illuminate\Database\Eloquent\SoftDeletes;
use ReflectionClass;

class SoftDeleteDetector
{
    /**
     * Check if a model uses the SoftDeletes trait.
     *
     * @param string $modelClass The fully qualified class name of the model.
     * @return bool True if the model uses SoftDeletes, false otherwise.
     */
    public static function hasSoftDeletes(string $modelClass): bool
    {
        if (!class_exists($modelClass)) {
            return false;
        }

        try {
            $reflection = new ReflectionClass($modelClass);
            $traitNames = $reflection->getTraitNames();
            return in_array(SoftDeletes::class, $traitNames, true);
        } catch (\ReflectionException $e) {
            return false;
        }
    }
}

