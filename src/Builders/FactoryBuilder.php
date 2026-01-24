<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Builders;

use function Laravel\Prompts\info;
use Illuminate\Support\Facades\File;
use function Laravel\Prompts\confirm;

use Mrmarchone\LaravelAutoCrud\Services\FileService;
use Mrmarchone\LaravelAutoCrud\Traits\ModelHelperTrait;
use Mrmarchone\LaravelAutoCrud\Services\TableColumnsService;

class FactoryBuilder
{
    use ModelHelperTrait;

    protected FileService $fileService;
    protected TableColumnsService $tableColumnsService;

    public function __construct(FileService $fileService, TableColumnsService $tableColumnsService)
    {
        $this->fileService = $fileService;
        $this->tableColumnsService = $tableColumnsService;
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
        
        // Get hidden properties and filter them out
        $hiddenProperties = $this->getHiddenProperties($model);
        $columns = array_filter($columns, function($column) use ($hiddenProperties) {
            return !in_array($column['name'], $hiddenProperties, true);
        });
        
        // Detect relationships to identify foreign keys
        $relationships = \Mrmarchone\LaravelAutoCrud\Services\RelationshipDetector::detectRelationships($model);
        $foreignKeys = $this->extractForeignKeys($relationships);

        File::ensureDirectoryExists(dirname($filePath), 0777, true);

        $stubPath = base_path('stubs/factory.stub');
        if (!file_exists($stubPath)) {
            $stubPath = base_path('vendor/mustafafares/laravel-auto-crud/src/Stubs/factory.stub');
        }

        $content = file_get_contents($stubPath);
        
        // Collect enum classes for use statements
        $enumUseStatements = $this->collectEnumUseStatements($columns);
        
        // Collect related model use statements for foreign keys
        $relatedModelUseStatements = $this->collectRelatedModelUseStatements($foreignKeys);
        
        $replacements = [
            '{{ modelNamespace }}' => $model,
            '{{ model }}' => $modelName,
            '{{ class }}' => $factoryName,
            '{{ definition }}' => $this->generateDefinition($columns, $foreignKeys),
            '{{ enumUseStatements }}' => $enumUseStatements,
            '{{ relatedModelUseStatements }}' => $relatedModelUseStatements,
        ];

        $content = str_replace(array_keys($replacements), array_values($replacements), $content);
        File::put($filePath, $content);

        info("Created: $filePath");

        return 'Database\\Factories\\' . $factoryName;
    }

    private function generateDefinition(array $columns, array $foreignKeys = []): string
    {
        $definition = [];
        foreach ($columns as $column) {
            $faker = $this->getFakerMethod($column, $foreignKeys);
            $definition[] = "            '{$column['name']}' => {$faker},";
        }

        return implode("\n", $definition);
    }

    private function getFakerMethod(array $column, array $foreignKeys = []): string
    {
        $name = strtolower($column['name']);
        $type = $column['type'];
        $isNullable = $column['isNullable'] ?? false;

        // Handle _id columns (foreign keys) FIRST - before type matching
        if (str_ends_with($name, '_id')) {
            // If detected as a relationship, use the related model factory
            if (isset($foreignKeys[$column['name']])) {
                $relatedModel = $foreignKeys[$column['name']];
                // If nullable, set to null; otherwise use relationship factory
                if ($isNullable) {
                    return 'null';
                }
                // Use relationship factory: User::factory()->create()->id
                return "{$relatedModel}::factory()->create()->id";
            }
            // If not detected as relationship, default to null to avoid constraint violations
            return 'null';
        }

        // Handle foreign keys that don't end with _id (shouldn't happen, but just in case)
        if (isset($foreignKeys[$column['name']])) {
            $relatedModel = $foreignKeys[$column['name']];
            if ($isNullable) {
                return 'null';
            }
            return "{$relatedModel}::factory()->create()->id";
        }

        if ($column['isTranslatable'] ?? false) {
            return "['en' => fake()->word()]";
        }

        if (!empty($column['allowedValues'])) {
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
            'integer', 'bigint', 'smallint', 'tinyint' => 'fake()->randomNumber()',
            'boolean' => 'fake()->boolean()',
            'decimal', 'float', 'double' => 'fake()->randomFloat(2, 0, 1000)',
            'date' => 'fake()->date()',
            'datetime', 'timestamp' => 'fake()->dateTime()',
            'json' => '[]',
            'uuid', 'char' => $name === 'id' ? 'fake()->uuid()' : ($isNullable ? 'null' : 'fake()->uuid()'),
            default => 'fake()->word()',
        };
    }

    private function enumFaker(array $column): string
    {
        $enumClass = $column['enum_class'] ?? null;

        if ($enumClass) {
            // Extract short class name for use in factory (use statement will be added)
            $shortClassName = $this->getShortClassName($enumClass);
            // pick a random enum value (map cases to their ->value)
            return "fake()->randomElement(array_map(fn(\$case) => \$case->value, {$shortClassName}::cases()))";
        }

        return "fake()->randomElement(['" . implode("', '", $column['allowedValues']) . "'])";
    }

    /**
     * Collect enum use statements from columns.
     *
     * @param array $columns
     * @return string
     */
    private function collectEnumUseStatements(array $columns): string
    {
        $enumClasses = [];
        
        foreach ($columns as $column) {
            if (!empty($column['enum_class'])) {
                $enumClass = $column['enum_class'];
                // Only add if it's a full namespace (contains \)
                if (str_contains($enumClass, '\\')) {
                    $enumClasses[$enumClass] = $enumClass;
                }
            }
        }
        
        if (empty($enumClasses)) {
            return '';
        }
        
        $useStatements = [];
        foreach ($enumClasses as $enumClass) {
            $useStatements[] = "\nuse {$enumClass};";
        }
        
        return implode('', $useStatements);
    }

    /**
     * Get short class name from full namespace.
     *
     * @param string $fullClassName
     * @return string
     */
    private function getShortClassName(string $fullClassName): string
    {
        $parts = explode('\\', $fullClassName);
        return end($parts);
    }

    /**
     * Extract foreign keys from relationships.
     *
     * @param array $relationships
     * @return array Array of foreign_key => related_model_name
     */
    private function extractForeignKeys(array $relationships): array
    {
        $foreignKeys = [];
        
        foreach ($relationships as $relationship) {
            if (isset($relationship['foreign_key']) && isset($relationship['related_model'])) {
                $foreignKey = $relationship['foreign_key'];
                $relatedModel = $relationship['related_model'];
                // Extract short model name
                $parts = explode('\\', $relatedModel);
                $relatedModelName = end($parts);
                $foreignKeys[$foreignKey] = $relatedModelName;
            }
        }
        
        return $foreignKeys;
    }

    /**
     * Collect related model use statements for foreign keys.
     *
     * @param array $foreignKeys
     * @return string
     */
    private function collectRelatedModelUseStatements(array $foreignKeys): string
    {
        if (empty($foreignKeys)) {
            return '';
        }
        
        // Get unique related models
        $relatedModels = array_unique(array_values($foreignKeys));
        
        // We need to resolve full namespaces - for now, assume App\Models namespace
        // This could be improved by checking the actual relationship definitions
        $useStatements = [];
        foreach ($relatedModels as $modelName) {
            // Try to find the model class
            $possibleNamespaces = [
                "App\\Models\\{$modelName}",
                "App\\{$modelName}",
            ];
            
            foreach ($possibleNamespaces as $namespace) {
                if (class_exists($namespace)) {
                    $useStatements[] = "\nuse {$namespace};";
                    break;
                }
            }
        }
        
        return implode('', $useStatements);
    }
}
