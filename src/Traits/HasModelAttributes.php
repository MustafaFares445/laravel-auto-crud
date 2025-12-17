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
     * @return array<string, mixed>
     */
    public function onlyModelAttributes(): array
    {
        $allowed = static::modelAttributesList();

        $dataArray = collect($this->toArray())
            ->filter(fn ($value) => $value !== null)
            ->mapWithKeys(fn ($value, $key) => [Str::snake($key) => $value])
            ->all();

        return array_intersect_key(
            $dataArray,
            array_flip($allowed)
        );
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
     * @return array<string>
     */
    private static function modelAttributesFromModel(): array
    {
        $reflection = new ReflectionClass(static::class);
        $dataProps = collect($reflection->getProperties())
            ->pluck('name')
            ->map(fn ($prop) => Str::snake($prop));

        /** @var class-string<Model> $modelClass */
        $modelClass = static::$model;

        /** @var Model $modelInstance */
        $modelInstance = new $modelClass;

        return $dataProps->intersect($modelInstance->getFillable())->values()->all();
    }
}
