<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Builders;

use Illuminate\Support\Facades\File;
use Mrmarchone\LaravelAutoCrud\Services\TableColumnsService;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;

class FactoryBuilder extends BaseBuilder
{
    protected TableColumnsService $tableColumnsService;

    public function __construct()
    {
        parent::__construct();
        $this->tableColumnsService = new TableColumnsService;
    }

    public function create(array $modelData, bool $overwrite = false): string
    {
        $model = $this->getFullModelNamespace($modelData);
        $modelName = $modelData['modelName'];
        $factoryName = $modelName . 'Factory';
        $filePath = database_path("factories/{$factoryName}.php");

        if (file_exists($filePath) && ! $overwrite) {
            $shouldOverwrite = confirm(
                label: 'Factory file already exists, do you want to overwrite it? ' . $filePath
            );
            if (! $shouldOverwrite) {
                return 'Database\\Factories\\' . $factoryName;
            }
        }

        $modelInstance = new $model;
        $tableName = $modelInstance->getTable();
        $columns = $this->tableColumnsService->getAvailableColumns($tableName, ['created_at', 'updated_at'], $model);

        File::ensureDirectoryExists(dirname($filePath), 0777, true);

        $stubPath = base_path('stubs/factory.stub');
        if (!file_exists($stubPath)) {
            $stubPath = base_path('vendor/mustafafares/laravel-auto-crud/src/Stubs/factory.stub');
        }

        $content = file_get_contents($stubPath);
        $replacements = [
            '{{ modelNamespace }}' => $model,
            '{{ model }}' => $modelName,
            '{{ class }}' => $factoryName,
            '{{ definition }}' => $this->generateDefinition($columns),
        ];

        $content = str_replace(array_keys($replacements), array_values($replacements), $content);
        File::put($filePath, $content);

        info("Created: $filePath");

        return 'Database\\Factories\\' . $factoryName;
    }

    private function generateDefinition(array $columns): string
    {
        $definition = [];
        foreach ($columns as $column) {
            $faker = $this->getFakerMethod($column);
            $definition[] = "            '{$column['name']}' => {$faker},";
        }

        return implode("\n", $definition);
    }

    private function getFakerMethod(array $column): string
    {
        $name = strtolower($column['name']);
        $type = $column['type'];

        if ($column['is_translatable'] ?? false) {
            return "['en' => fake()->word()]";
        }

        if (!empty($column['allowed_values'])) {
            return $this->enumFaker($column);
        }

        if (str_contains($name, 'name')) return 'fake()->name()';
        if (str_contains($name, 'email')) return 'fake()->unique()->safeEmail()';
        if (str_contains($name, 'password')) return "bcrypt('password')";
        if (str_contains($name, 'phone')) return 'fake()->phoneNumber()';
        if (str_contains($name, 'address')) return 'fake()->address()';
        if (str_contains($name, 'city')) return 'fake()->city()';
        if (str_contains($name, 'country')) return 'fake()->country()';
        if (str_contains($name, 'zip') || str_contains($name, 'postcode')) return 'fake()->postcode()';
        if (str_contains($name, 'text') || str_contains($name, 'description') || str_contains($name, 'notes')) return 'fake()->paragraph()';
        if (str_contains($name, 'title') || str_contains($name, 'subject')) return 'fake()->sentence()';
        if (str_contains($name, 'slug')) return 'fake()->slug()';
        if (str_contains($name, 'url') || str_contains($name, 'website')) return 'fake()->url()';
        if (str_contains($name, 'image') || str_contains($name, 'avatar')) return 'fake()->imageUrl()';
        if (str_contains($name, 'color')) return 'fake()->safeColorName()';

        return match ($type) {
            'integer', 'bigint', 'smallint', 'tinyint' => str_ends_with($name, '_id') ? 'null' : 'fake()->randomNumber()',
            'boolean' => 'fake()->boolean()',
            'decimal', 'float', 'double' => 'fake()->randomFloat(2, 0, 1000)',
            'date' => 'fake()->date()',
            'datetime', 'timestamp' => 'fake()->dateTime()',
            'json' => '[]',
            default => 'fake()->word()',
        };
    }

    private function enumFaker(array $column): string
    {
        $enumClass = $column['enum_class'] ?? null;

        if ($enumClass) {
            return "fake()->randomElement(array_values({$enumClass}::cases()))";
        }

        return "fake()->randomElement(['" . implode("', '", $column['allowed_values']) . "'])";
    }
}
