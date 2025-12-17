<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Traits;

use Illuminate\Support\Str;

trait ConvertsKeysToSnakeCase
{
    protected function prepareForValidation(): void
    {
        $this->replace($this->convertKeysToSnakeCase($this->all()));
    }

    protected function convertKeysToSnakeCase(array $data): array
    {
        $converted = [];

        foreach ($data as $key => $value) {
            $snakeKey = Str::snake($key);

            $converted[$snakeKey] = is_array($value)
                ? $this->convertKeysToSnakeCase($value)
                : $value;
        }

        return $converted;
    }
}
