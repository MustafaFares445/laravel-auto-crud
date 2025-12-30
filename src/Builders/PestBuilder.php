<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Builders;

use Illuminate\Support\Str;
use Mrmarchone\LaravelAutoCrud\Services\FileService;
use Mrmarchone\LaravelAutoCrud\Services\TableColumnsService;

class PestBuilder extends BaseBuilder
{
    protected TableColumnsService $tableColumnsService;

    public function __construct(FileService $fileService, TableColumnsService $tableColumnsService)
    {
        parent::__construct($fileService);
        $this->tableColumnsService = $tableColumnsService;
    }

    public function createFeatureTest(array $modelData, bool $overwrite = false, bool $withPolicy = false): string
    {
        return $this->fileService->createFromStub($modelData, 'pest_feature_api', 'tests/Feature/' . $modelData['modelName'], 'EndpointsTest', $overwrite, function ($modelData) use ($withPolicy) {
            $model = $this->getFullModelNamespace($modelData);
            $modelInstance = new $model;
            $tableName = $modelInstance->getTable();
            $columns = $this->tableColumnsService->getAvailableColumns($tableName, ['created_at', 'updated_at'], $model);

            $searchField = 'name';
            foreach ($columns as $column) {
                if (in_array($column['name'], ['name', 'title', 'label', 'display_name'])) {
                    $searchField = $column['name'];
                    break;
                }
            }

            $authorizationTests = '';
            if ($withPolicy && config('laravel_auto_crud.test_settings.include_authorization_tests', true)) {
                $routePath = '/api/' . Str::plural(Str::snake($modelData['modelName']));
                $authPayload = $this->generatePayload($columns);

                $authorizationTests = "\n" . "it('forbids unauthorized access to " . Str::plural(Str::snake($modelData['modelName'], ' ')) . " CRUD operations', function () {
    \$user = User::factory()->create();
    \$model = {$modelData['modelName']}::factory()->create();
    Sanctum::actingAs(\$user);

    \$this->postJson('{$routePath}', [
{$authPayload}
    ])->assertForbidden();

    \$this->putJson(\"{$routePath}/\" . \$model->id, [
{$authPayload}
    ])->assertForbidden();

    \$this->deleteJson(\"{$routePath}/\" . \$model->id)->assertForbidden();
});";
            }

            $extraTests = $this->generateExtraTests($modelData, $model, $searchField);

            // Generate property and relationship assertions
            $listPropertyAssertions = $this->generatePropertyAssertions($columns, $model, true);
            $showPropertyAssertions = $this->generatePropertyAssertions($columns, $model, false);
            
            $relationships = \Mrmarchone\LaravelAutoCrud\Services\RelationshipDetector::detectRelationships($model);
            $listRelationshipAssertions = $this->generateRelationshipAssertions($relationships, true);
            $showRelationshipAssertions = $this->generateRelationshipAssertions($relationships, false);
            
            // Collect enum use statements for test file
            $enumUseStatements = $this->collectEnumUseStatements($columns, $modelData);
            
            // Get seeder class and check if it exists
            $seederClass = $this->getSeederClass();

            return [
                '{{ modelNamespace }}' => $model,
                '{{ model }}' => $modelData['modelName'],
                '{{ modelPlural }}' => Str::plural(Str::snake($modelData['modelName'], ' ')),
                '{{ modelVariable }}' => lcfirst($modelData['modelName']),
                '{{ routePath }}' => '/api/' . Str::plural(Str::snake($modelData['modelName'])),
                '{{ tableName }}' => $tableName,
                '{{ seederClass }}' => $seederClass,
                '{{ seederCall }}' => $this->generateSeederCall($seederClass),
                '{{ responseMessagesNamespace }}' => 'Mrmarchone\\LaravelAutoCrud\\Enums\\ResponseMessages',
                '{{ createPayload }}' => $this->generatePayload($columns),
                '{{ updatePayload }}' => $this->generatePayload($columns, true),
                '{{ authorizationTests }}' => $authorizationTests,
                '{{ extraTests }}' => $extraTests,
                '{{ listPropertyAssertions }}' => $listPropertyAssertions,
                '{{ showPropertyAssertions }}' => $showPropertyAssertions,
                '{{ listRelationshipAssertions }}' => $listRelationshipAssertions,
                '{{ showRelationshipAssertions }}' => $showRelationshipAssertions,
                '{{ enumUseStatements }}' => $enumUseStatements,
            ];
        });
    }

    public function createFilterTest(array $modelData, bool $overwrite = false): string
    {
        return $this->fileService->createFromStub($modelData, 'pest_feature_filters', 'tests/Feature/' . $modelData['modelName'], 'FiltersTest', $overwrite, function ($modelData) {
            $model = $this->getFullModelNamespace($modelData);
            $modelInstance = new $model;
            $tableName = $modelInstance->getTable();
            $columns = $this->tableColumnsService->getAvailableColumns($tableName, ['created_at', 'updated_at'], $model);

            // Find search field
            $searchField = 'name';
            foreach ($columns as $column) {
                if (in_array($column['name'], ['name', 'title', 'label', 'display_name'])) {
                    $searchField = $column['name'];
                    break;
                }
            }

            $filterTests = $this->generateFilterTests($modelData, $model, $columns, $searchField);
            
            // Get seeder class and check if it exists
            $seederClass = $this->getSeederClass();

            return [
                '{{ modelNamespace }}' => $model,
                '{{ model }}' => $modelData['modelName'],
                '{{ modelPlural }}' => Str::plural(Str::snake($modelData['modelName'], ' ')),
                '{{ modelVariable }}' => lcfirst($modelData['modelName']),
                '{{ routePath }}' => '/api/' . Str::plural(Str::snake($modelData['modelName'])),
                '{{ seederClass }}' => $seederClass,
                '{{ seederCall }}' => $this->generateSeederCall($seederClass),
                '{{ responseMessagesNamespace }}' => 'Mrmarchone\\LaravelAutoCrud\\Enums\\ResponseMessages',
                '{{ filterTests }}' => $filterTests,
            ];
        });
    }

    private function generateExtraTests(array $modelData, string $model, string $searchField): string
    {
        // Search and sort tests have been moved to filter tests
        return '';
    }

    private function generateFilterTests(array $modelData, string $model, array $columns, string $searchField): string
    {
        $modelName = $modelData['modelName'];
        $modelVariable = lcfirst($modelName);
        $modelPlural = Str::plural(Str::snake($modelName, ' '));
        $routePath = '/api/' . Str::plural(Str::snake($modelName));

        $tests = "";

        // Search test - only add if model has search scope
        if (method_exists($model, 'scopeSearch')) {
            $tests .= "it('filters {$modelPlural} by search term', function () {
    {$modelName}::factory()->create(['{$searchField}' => 'Target {$modelVariable}']);
    {$modelName}::factory()->create(['{$searchField}' => 'Other {$modelVariable}']);

    \$response = \$this->withHeader('Accept-Language', 'en')
        ->getJson('{$routePath}?search=Target');

    \$response->assertOk();
    // Adjusted check for translatable or normal string
    \$data = \$response->json('data');
    \$found = false;
    foreach (\$data as \$item) {
        \$val = is_array(\$item['{$searchField}']) ? json_encode(\$item['{$searchField}']) : (string)\$item['{$searchField}'];
        if (str_contains(\$val, 'Target')) {
            \$found = true;
            break;
        }
    }
    expect(\$found)->toBeTrue();
});\n\n";
        }

        // Sort test
        $tests .= "it('sorts {$modelPlural}', function () {
    {$modelName}::factory()->create(['{$searchField}' => 'A {$modelVariable}']);
    {$modelName}::factory()->create(['{$searchField}' => 'Z {$modelVariable}']);

    \$response = \$this->getJson('{$routePath}?sort={$searchField}');
    \$response->assertOk();

    \$val1 = \$response->json('data.0.{$searchField}');
    if (is_array(\$val1)) \$val1 = \$val1['en'] ?? array_values(\$val1)[0];

    expect((string)\$val1)->toContain('A');

    \$response = \$this->getJson('{$routePath}?sort=-{$searchField}');
    \$response->assertOk();

    \$val2 = \$response->json('data.0.{$searchField}');
    if (is_array(\$val2)) \$val2 = \$val2['en'] ?? array_values(\$val2)[0];

    expect((string)\$val2)->toContain('Z');
});\n\n";

        // Filter by each column
        foreach ($columns as $column) {
            $columnName = $column['name'];
            $camelCaseName = Str::camel($columnName);
            $displayName = Str::of($columnName)->replace('_', ' ');
                $testValue = $this->generateValueForType($column, false);

                $tests .= "it('filters {$modelPlural} by {$columnName}', function () {
            {$modelName}::factory()->create(['{$columnName}' => {$testValue}]);
            {$modelName}::factory()->create();

            \$response = \$this->getJson('{$routePath}?filter[{$camelCaseName}]=' . ({$testValue}));

            \$response->assertOk();
            expect(\$response->json('data'))->toHaveCount(1);
        });\n\n";
        }

        // Filter by date range
        $tests .= "it('filters {$modelPlural} by date range', function () {
    {$modelName}::factory()->create(['created_at' => now()->subDays(5)]);
    {$modelName}::factory()->create(['created_at' => now()]);

    \$after = now()->subDays(2)->format('Y-m-d');
    \$before = now()->format('Y-m-d');
    
    \$response = \$this->getJson('{$routePath}?filter[createdAfter]=' . \$after . '&filter[createdBefore]=' . \$before);

    \$response->assertOk();
    expect(\$response->json('data'))->toHaveCount(1);
});\n\n";

        // Test pagination with filters
        $tests .= "it('paginates filtered {$modelPlural}', function () {
    {$modelName}::factory()->count(15)->create();

    \$response = \$this->getJson('{$routePath}?per_page=5&page=1');

    \$response->assertOk();
    expect(\$response->json('data'))->toHaveCount(5);
});\n";

        return $tests;
    }

    private function generatePayload(array $columns, bool $isUpdate = false): string
    {
        $payload = [];
        foreach ($columns as $column) {
            $value = $this->generateValueForType($column, $isUpdate);
            // Convert snake_case to camelCase for API payloads
            $camelCaseName = Str::camel($column['name']);
            $payload[] = "        '{$camelCaseName}' => {$value},";
        }

        return implode("\n", $payload);
    }

    private function generateValueForType(array $column, bool $isUpdate = false): string
    {
        $type = $column['type'];
        $name = strtolower($column['name']);
        $suffix = $isUpdate ? ' updated' : '';

        if ($column['isTranslatable'] ?? false) {
            return "['en' => 'Sample " . $column['name'] . $suffix . "']";
        }

        if (!empty($column['allowedValues'])) {
            // Prefer enum class if available
            $enumClass = $column['enum_class'] ?? null;
            if ($enumClass) {
                // Extract short class name for use in test
                $shortClassName = $this->getShortClassName($enumClass);
                return "{$shortClassName}::cases()[0]->value";
            }

            return "'" . $column['allowedValues'][0] . "'";
        }

        // Handle special column names
        if (str_contains($name, 'email')) {
            return "'test" . ($isUpdate ? '_updated' : '') . "@example.com'";
        }
        if (str_contains($name, 'phone')) {
            return "'+1234567890'";
        }
        if (str_contains($name, 'url') || str_contains($name, 'website')) {
            return "'https://example.com'";
        }
        if (str_contains($name, 'slug')) {
            return "'sample-" . strtolower($column['name']) . ($isUpdate ? '-updated' : '') . "'";
        }

        return match ($type) {
            'integer', 'bigint', 'smallint' => '1',
            'decimal', 'float', 'double' => '10.5',
            'boolean' => 'true',
            'date' => "'2025-01-01'",
            'datetime', 'timestamp' => "'2025-01-01 10:00:00'",
            'json' => '[]',
            'uuid', 'char' => str_ends_with($name, '_id') ? 'null' : "'" . fake()->uuid() . "'",
            default => "'Sample " . $column['name'] . $suffix . "'",
        };
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
     * Get seeder class from config and verify it exists.
     *
     * @return string|null Seeder class name if exists, null otherwise
     */
    private function getSeederClass(): ?string
    {
        $seederClass = config('laravel_auto_crud.test_settings.seeder_class', 'Database\\Seeders\\RolesAndPermissionsSeeder');
        
        if (empty($seederClass)) {
            return null;
        }
        
        // Check if the class exists
        if (class_exists($seederClass)) {
            return $seederClass;
        }
        
        // If class doesn't exist, return null
        return null;
    }

    /**
     * Generate seeder call code if seeder exists.
     *
     * @param string|null $seederClass
     * @return string
     */
    private function generateSeederCall(?string $seederClass): string
    {
        if ($seederClass) {
            return "    \$this->seed({$seederClass}::class);\n";
        }
        
        return '';
    }

    /**
     * Collect enum use statements from columns and payload values for test files.
     *
     * @param array $columns
     * @param array $modelData
     * @return string
     */
    private function collectEnumUseStatements(array $columns, array $modelData): string
    {
        $enumClasses = [];
        
        // Collect from columns
        foreach ($columns as $column) {
            if (!empty($column['enum_class'])) {
                $enumClass = $column['enum_class'];
                // Ensure we have full namespace
                if (!str_contains($enumClass, '\\')) {
                    $enumClass = "App\\Enums\\{$enumClass}";
                }
                $enumClasses[$enumClass] = $enumClass;
            } elseif (!empty($column['allowedValues'])) {
                // Check if enum exists for this column
                $model = $this->getFullModelNamespace($modelData);
                $modelName = $modelData['modelName'];
                $enumClass = \Mrmarchone\LaravelAutoCrud\Services\EnumDetector::detectExistingEnum($model, $column['name'], $modelName);
                if ($enumClass) {
                    $enumClasses[$enumClass] = $enumClass;
                }
            }
        }
        
        if (empty($enumClasses)) {
            return '';
        }
        
        $useStatements = [];
        foreach ($enumClasses as $enumClass) {
            $useStatements[] = "use {$enumClass};";
        }
        
        return implode("\n", $useStatements);
    }

    /**
     * Generate property assertions for test responses
     *
     * @param array $columns Array of column definitions
     * @param string $model Full model class name
     * @param bool $isList Whether this is for list endpoint (uses firstItem) or show endpoint
     * @return string Generated assertion code
     */
    private function generatePropertyAssertions(array $columns, string $model, bool $isList = false): string
    {
        if (empty($columns)) {
            return '';
        }

        $assertions = [];
        $itemVar = $isList ? '$firstItem' : '$data';
        
        // Check for id - determine if it's UUID or integer by checking model's key type
        $isUuid = false;
        try {
            if (class_exists($model)) {
                $modelInstance = new $model;
                $keyType = $modelInstance->getKeyType();
                $isUuid = ($keyType === 'string' && $modelInstance->getIncrementing() === false);
            }
        } catch (\Throwable $e) {
            // If we can't determine, check column type
            foreach ($columns as $column) {
                if ($column['name'] === 'id') {
                    $isUuid = in_array($column['type'], ['uuid', 'char']) || str_contains(strtolower($column['type']), 'uuid');
                    break;
                }
            }
        }
        
        if ($isUuid) {
            $assertions[] = "        expect({$itemVar}['id'])->toBeString();";
        } else {
            $assertions[] = "        expect({$itemVar}['id'])->toBeInt();";
        }
        
        // Check other important columns (skip timestamps for now, they're always present)
        foreach ($columns as $column) {
            $columnName = $column['name'];
            
            // Skip timestamps and id (already handled)
            if (in_array($columnName, ['created_at', 'updated_at', 'id'])) {
                continue;
            }
            
            $camelCase = Str::camel($columnName);
            
            // For translatable fields, check if it's an array
            if ($column['isTranslatable'] ?? false) {
                $assertions[] = "        expect({$itemVar}['{$camelCase}'])->toBeArray();";
            } else {
                // For non-translatable, just check that the key exists
                $assertions[] = "        expect({$itemVar})->toHaveKey('{$camelCase}');";
            }
        }
        
        return implode("\n", $assertions);
    }

    /**
     * Generate relationship assertions for test responses
     *
     * @param array $relationships Array of relationship definitions
     * @param bool $isList Whether this is for list endpoint or show endpoint
     * @return string Generated assertion code
     */
    private function generateRelationshipAssertions(array $relationships, bool $isList = false): string
    {
        if (empty($relationships)) {
            return '';
        }

        $assertions = [];
        $itemVar = $isList ? '$firstItem' : '$data';
        
        // Get relationships that are typically loaded (belongsTo, hasOne)
        $loadedRelations = array_filter($relationships, function($rel) {
            return in_array($rel['type'], ['belongsTo', 'hasOne']);
        });
        
        foreach ($loadedRelations as $relationship) {
            $relationshipName = $relationship['name'];
            $camelCase = Str::camel($relationshipName);
            
            // Check if relationship exists in response (may or may not be loaded)
            $assertions[] = "        // Relationship '{$relationshipName}' may be present if loaded";
            $assertions[] = "        if (isset({$itemVar}['{$camelCase}'])) {";
            $assertions[] = "            expect({$itemVar}['{$camelCase}'])->toBeArray();";
            $assertions[] = "        }";
        }
        
        // For hasMany, belongsToMany relationships (typically in show endpoint)
        if (!$isList) {
            $collectionRelations = array_filter($relationships, function($rel) {
                return in_array($rel['type'], ['hasMany', 'belongsToMany', 'morphMany', 'morphToMany']);
            });
            
            foreach ($collectionRelations as $relationship) {
                $relationshipName = $relationship['name'];
                $camelCase = Str::camel($relationshipName);
                
                $assertions[] = "        // Relationship '{$relationshipName}' may be present if loaded";
                $assertions[] = "        if (isset({$itemVar}['{$camelCase}'])) {";
                $assertions[] = "            expect({$itemVar}['{$camelCase}'])->toBeArray();";
                $assertions[] = "        }";
            }
        }
        
        return !empty($assertions) ? implode("\n", $assertions) : '';
    }
}
