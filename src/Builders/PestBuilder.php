<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Builders;

use Illuminate\Support\Str;
use Mrmarchone\LaravelAutoCrud\Services\FileService;
use Mrmarchone\LaravelAutoCrud\Helpers\TestDataHelper;
use Mrmarchone\LaravelAutoCrud\Traits\ModelHelperTrait;
use Mrmarchone\LaravelAutoCrud\Services\TableColumnsService;

class PestBuilder
{
    use ModelHelperTrait;

    protected FileService $fileService;
    protected TableColumnsService $tableColumnsService;

    public function __construct(FileService $fileService, TableColumnsService $tableColumnsService)
    {
        $this->fileService = $fileService;
        $this->tableColumnsService = $tableColumnsService;
    }

    public function createFeatureTest(array $modelData, bool $overwrite = false, bool $withPolicy = false): string
    {
        // Generate the main endpoints test file
        $endpointsTestPath = $this->createEndpointsTest($modelData, $overwrite, $withPolicy);
        
        // Generate validation test file if enabled
        if (config('laravel_auto_crud.test_settings.include_validation_tests', true)) {
            $this->createValidationTest($modelData, $overwrite);
        }
        
        // Generate edge cases test file if enabled
        if (config('laravel_auto_crud.test_settings.include_edge_case_tests', true)) {
            $this->createEdgeCasesTest($modelData, $overwrite);
        }
        
        // Generate pagination test file
        $this->createPaginationTest($modelData, $overwrite);
        
        // Generate authorization test file if policy is enabled
        if ($withPolicy && config('laravel_auto_crud.test_settings.include_authorization_tests', true)) {
            $this->createAuthorizationTest($modelData, $overwrite);
        }
        
        return $endpointsTestPath;
    }

    private function createEndpointsTest(array $modelData, bool $overwrite = false, bool $withPolicy = false): string
    {
        return $this->fileService->createFromStub($modelData, 'pest_feature_api', 'tests/Feature/' . $modelData['modelName'], 'EndpointsTest', $overwrite, function ($modelData) use ($withPolicy) {
            $model = $this->getFullModelNamespace($modelData);
            $modelInstance = new $model;
            $tableName = $modelInstance->getTable();
            $columns = $this->tableColumnsService->getAvailableColumns($tableName, ['created_at', 'updated_at'], $model);

            // Get hidden properties and filter them out
            $hiddenProperties = $this->getHiddenProperties($model);
            $columns = array_filter($columns, function($column) use ($hiddenProperties) {
                return !in_array($column['name'], $hiddenProperties, true);
            });

            $routePath = '/api/' . Str::plural(Str::snake($modelData['modelName']));
            
            // Generate property and relationship assertions
            $listPropertyAssertions = $this->generatePropertyAssertions($columns, $model, true);
            $showPropertyAssertions = $this->generatePropertyAssertions($columns, $model, false);
            
            $relationships = \Mrmarchone\LaravelAutoCrud\Services\RelationshipDetector::detectRelationships($model);
            $listRelationshipAssertions = $this->generateRelationshipAssertions($relationships, true);
            $showRelationshipAssertions = $this->generateRelationshipAssertions($relationships, false);
            
            $notFoundTests = $this->generateNotFoundTests($modelData, $model, $routePath);
            $relationshipTests = $this->generateRelationshipTests($modelData, $model, $relationships, $routePath);
            
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
                '{{ authorizationTests }}' => '', // Moved to separate file
                '{{ validationTests }}' => '', // Moved to separate file
                '{{ notFoundTests }}' => $notFoundTests,
                '{{ edgeCaseTests }}' => '', // Moved to separate file
                '{{ relationshipTests }}' => $relationshipTests,
                '{{ paginationTests }}' => '', // Moved to separate file
                '{{ extraTests }}' => '',
                '{{ listPropertyAssertions }}' => $listPropertyAssertions,
                '{{ showPropertyAssertions }}' => $showPropertyAssertions,
                '{{ listRelationshipAssertions }}' => $listRelationshipAssertions,
                '{{ showRelationshipAssertions }}' => $showRelationshipAssertions,
                '{{ enumUseStatements }}' => $enumUseStatements,
            ];
        });
    }

    private function createValidationTest(array $modelData, bool $overwrite = false): string
    {
        return $this->fileService->createFromStub($modelData, 'pest_feature_validation', 'tests/Feature/' . $modelData['modelName'], 'ValidationTest', $overwrite, function ($modelData) {
            $model = $this->getFullModelNamespace($modelData);
            $modelInstance = new $model;
            $tableName = $modelInstance->getTable();
            $columns = $this->tableColumnsService->getAvailableColumns($tableName, ['created_at', 'updated_at'], $model);

            $hiddenProperties = $this->getHiddenProperties($model);
            $columns = array_filter($columns, function($column) use ($hiddenProperties) {
                return !in_array($column['name'], $hiddenProperties, true);
            });

            $routePath = '/api/' . Str::plural(Str::snake($modelData['modelName']));
            $validationTests = $this->generateValidationTests($modelData, $model, $columns, $routePath);
            
            $enumUseStatements = $this->collectEnumUseStatements($columns, $modelData);
            $seederClass = $this->getSeederClass();

            return [
                '{{ modelNamespace }}' => $model,
                '{{ model }}' => $modelData['modelName'],
                '{{ modelPlural }}' => Str::plural(Str::snake($modelData['modelName'], ' ')),
                '{{ modelVariable }}' => lcfirst($modelData['modelName']),
                '{{ routePath }}' => $routePath,
                '{{ seederClass }}' => $seederClass,
                '{{ seederCall }}' => $this->generateSeederCall($seederClass),
                '{{ validationTests }}' => $validationTests,
                '{{ enumUseStatements }}' => $enumUseStatements,
            ];
        });
    }

    private function createEdgeCasesTest(array $modelData, bool $overwrite = false): string
    {
        return $this->fileService->createFromStub($modelData, 'pest_feature_edge_cases', 'tests/Feature/' . $modelData['modelName'], 'EdgeCasesTest', $overwrite, function ($modelData) {
            $model = $this->getFullModelNamespace($modelData);
            $modelInstance = new $model;
            $tableName = $modelInstance->getTable();
            $columns = $this->tableColumnsService->getAvailableColumns($tableName, ['created_at', 'updated_at'], $model);

            $hiddenProperties = $this->getHiddenProperties($model);
            $columns = array_filter($columns, function($column) use ($hiddenProperties) {
                return !in_array($column['name'], $hiddenProperties, true);
            });

            $routePath = '/api/' . Str::plural(Str::snake($modelData['modelName']));
            $edgeCaseTests = $this->generateEdgeCaseTests($modelData, $model, $columns, $routePath);
            
            $enumUseStatements = $this->collectEnumUseStatements($columns, $modelData);
            $seederClass = $this->getSeederClass();

            return [
                '{{ modelNamespace }}' => $model,
                '{{ model }}' => $modelData['modelName'],
                '{{ modelPlural }}' => Str::plural(Str::snake($modelData['modelName'], ' ')),
                '{{ modelVariable }}' => lcfirst($modelData['modelName']),
                '{{ routePath }}' => $routePath,
                '{{ seederClass }}' => $seederClass,
                '{{ seederCall }}' => $this->generateSeederCall($seederClass),
                '{{ edgeCaseTests }}' => $edgeCaseTests,
                '{{ enumUseStatements }}' => $enumUseStatements,
            ];
        });
    }

    private function createPaginationTest(array $modelData, bool $overwrite = false): string
    {
        return $this->fileService->createFromStub($modelData, 'pest_feature_pagination', 'tests/Feature/' . $modelData['modelName'], 'PaginationTest', $overwrite, function ($modelData) {
            $model = $this->getFullModelNamespace($modelData);
            $routePath = '/api/' . Str::plural(Str::snake($modelData['modelName']));
            $paginationTests = $this->generatePaginationTests($modelData, $model, $routePath);
            
            $seederClass = $this->getSeederClass();

            return [
                '{{ modelNamespace }}' => $model,
                '{{ model }}' => $modelData['modelName'],
                '{{ modelPlural }}' => Str::plural(Str::snake($modelData['modelName'], ' ')),
                '{{ routePath }}' => $routePath,
                '{{ seederClass }}' => $seederClass,
                '{{ seederCall }}' => $this->generateSeederCall($seederClass),
                '{{ paginationTests }}' => $paginationTests,
            ];
        });
    }

    private function createAuthorizationTest(array $modelData, bool $overwrite = false): string
    {
        return $this->fileService->createFromStub($modelData, 'pest_feature_authorization', 'tests/Feature/' . $modelData['modelName'], 'AuthorizationTest', $overwrite, function ($modelData) {
            $model = $this->getFullModelNamespace($modelData);
            $modelInstance = new $model;
            $tableName = $modelInstance->getTable();
            $columns = $this->tableColumnsService->getAvailableColumns($tableName, ['created_at', 'updated_at'], $model);

            $hiddenProperties = $this->getHiddenProperties($model);
            $columns = array_filter($columns, function($column) use ($hiddenProperties) {
                return !in_array($column['name'], $hiddenProperties, true);
            });

            $routePath = '/api/' . Str::plural(Str::snake($modelData['modelName']));
            $authorizationTests = $this->generateEnhancedAuthorizationTests($modelData, $model, $columns, $routePath);
            
            $enumUseStatements = $this->collectEnumUseStatements($columns, $modelData);
            $seederClass = $this->getSeederClass();

            return [
                '{{ modelNamespace }}' => $model,
                '{{ model }}' => $modelData['modelName'],
                '{{ modelPlural }}' => Str::plural(Str::snake($modelData['modelName'], ' ')),
                '{{ modelVariable }}' => lcfirst($modelData['modelName']),
                '{{ routePath }}' => $routePath,
                '{{ seederClass }}' => $seederClass,
                '{{ seederCall }}' => $this->generateSeederCall($seederClass),
                '{{ authorizationTests }}' => $authorizationTests,
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

            // Get hidden properties and filter them out
            $hiddenProperties = $this->getHiddenProperties($model);
            $columns = array_filter($columns, function($column) use ($hiddenProperties) {
                return !in_array($column['name'], $hiddenProperties, true);
            });

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
        
        // Start with the first assertion (ID check)
        if ($isUuid) {
            $firstAssertion = "        expect({$itemVar}['id'])->toBeString()";
        } else {
            $firstAssertion = "        expect({$itemVar}['id'])->toBeInt()";
        }
        
        // Chain subsequent assertions using ->and()
        $chainedAssertions = [];
        
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
                $chainedAssertions[] = "\n            ->and({$itemVar}['{$camelCase}'])->toBeArray()";
            } else {
                // For non-translatable, just check that the key exists
                $chainedAssertions[] = "\n            ->and({$itemVar})->toHaveKey('{$camelCase}')";
            }
        }
        
        // Combine first assertion with chained ones
        if (!empty($chainedAssertions)) {
            return $firstAssertion . implode('', $chainedAssertions) . ';';
        }
        
        return $firstAssertion . ';';
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

    /**
     * Generate validation error tests for Form Request validation rules.
     *
     * @param array $modelData
     * @param string $model
     * @param array $columns
     * @param string $routePath
     * @return string
     */
    private function generateValidationTests(array $modelData, string $model, array $columns, string $routePath): string
    {
        $modelName = $modelData['modelName'];
        $modelVariable = lcfirst($modelName);
        $modelPlural = Str::plural(Str::snake($modelName, ' '));
        $tests = '';

        // Test empty payload (missing required fields)
        $requiredFields = [];
        foreach ($columns as $column) {
            $isNullable = $column['isNullable'] ?? false;
            if (!$isNullable && !in_array($column['name'], ['id', 'created_at', 'updated_at', 'deleted_at'])) {
                $requiredFields[] = Str::camel($column['name']);
            }
        }

        if (!empty($requiredFields)) {
            $tests .= "it('validates required fields when creating a {$modelVariable}', function () {
    // Arrange
    \$payload = [];
    
    // Act
    \$response = \$this->postJson('{$routePath}', \$payload);
    
    // Assert
    \$response->assertStatus(422)
        ->assertJsonValidationErrors([" . implode(', ', array_map(fn($f) => "'{$f}'", $requiredFields)) . "]);
});\n\n";
        }

        // Test validation for each column based on type
        foreach ($columns as $column) {
            $columnName = $column['name'];
            $camelCaseName = Str::camel($columnName);
            $isNullable = $column['isNullable'] ?? false;
            $type = $column['type'];
            $name = strtolower($columnName);

            // Skip timestamps and id
            if (in_array($columnName, ['id', 'created_at', 'updated_at', 'deleted_at'])) {
                continue;
            }

            // Test invalid type for integers
            if (in_array($type, ['integer', 'bigint', 'smallint', 'tinyint'])) {
                $tests .= "it('validates {$camelCaseName} must be an integer', function () {
    // Arrange
    \$payload = [" . $this->generatePayloadString($columns, [$camelCaseName => "'not-a-number'"]) . "];
    
    // Act
    \$response = \$this->postJson('{$routePath}', \$payload);
    
    // Assert
    \$response->assertStatus(422)
        ->assertJsonValidationErrors(['{$camelCaseName}']);
});\n\n";
            }

            // Test invalid email format
            if (str_contains($name, 'email')) {
                $tests .= "it('validates {$camelCaseName} must be a valid email', function () {
    // Arrange
    \$payload = [" . $this->generatePayloadString($columns, [$camelCaseName => "'invalid-email'"]) . "];
    
    // Act
    \$response = \$this->postJson('{$routePath}', \$payload);
    
    // Assert
    \$response->assertStatus(422)
        ->assertJsonValidationErrors(['{$camelCaseName}']);
});\n\n";
            }

            // Test invalid URL format
            if (str_contains($name, 'url') || str_contains($name, 'website')) {
                $tests .= "it('validates {$camelCaseName} must be a valid URL', function () {
    // Arrange
    \$payload = [" . $this->generatePayloadString($columns, [$camelCaseName => "'not-a-url'"]) . "];
    
    // Act
    \$response = \$this->postJson('{$routePath}', \$payload);
    
    // Assert
    \$response->assertStatus(422)
        ->assertJsonValidationErrors(['{$camelCaseName}']);
});\n\n";
            }

            // Test max length
            if (isset($column['maxLength']) && $column['maxLength'] > 0) {
                $maxLength = (int) $column['maxLength'];
                $tests .= "it('validates {$camelCaseName} must not exceed max length', function () {
    // Arrange
    \$payload = [" . $this->generatePayloadString($columns, [$camelCaseName => "'" . str_repeat('a', $maxLength + 1) . "'"]) . "];
    
    // Act
    \$response = \$this->postJson('{$routePath}', \$payload);
    
    // Assert
    \$response->assertStatus(422)
        ->assertJsonValidationErrors(['{$camelCaseName}']);
});\n\n";
            }

            // Test enum values
            if (!empty($column['allowedValues'])) {
                $enumClass = $column['enum_class'] ?? null;
                if ($enumClass) {
                    $shortClassName = $this->getShortClassName($enumClass);
                    $tests .= "it('validates {$camelCaseName} must be a valid enum value', function () {
    // Arrange
    \$payload = [" . $this->generatePayloadString($columns, [$camelCaseName => "'invalid-enum-value'"]) . "];
    
    // Act
    \$response = \$this->postJson('{$routePath}', \$payload);
    
    // Assert
    \$response->assertStatus(422)
        ->assertJsonValidationErrors(['{$camelCaseName}']);
});\n\n";
                }
            }

            // Test date format
            if (in_array($type, ['date', 'datetime', 'timestamp'])) {
                $tests .= "it('validates {$camelCaseName} must be a valid date format', function () {
    // Arrange
    \$payload = [" . $this->generatePayloadString($columns, [$camelCaseName => "'invalid-date'"]) . "];
    
    // Act
    \$response = \$this->postJson('{$routePath}', \$payload);
    
    // Assert
    \$response->assertStatus(422)
        ->assertJsonValidationErrors(['{$camelCaseName}']);
});\n\n";
            }

            // Test JSON format
            if ($type === 'json') {
                $tests .= "it('validates {$camelCaseName} must be valid JSON', function () {
    // Arrange
    \$payload = [" . $this->generatePayloadString($columns, [$camelCaseName => "'not-json'"]) . "];
    
    // Act
    \$response = \$this->postJson('{$routePath}', \$payload);
    
    // Assert
    \$response->assertStatus(422)
        ->assertJsonValidationErrors(['{$camelCaseName}']);
});\n\n";
            }
        }

        return $tests;
    }

    /**
     * Generate not found (404) tests.
     *
     * @param array $modelData
     * @param string $model
     * @param string $routePath
     * @return string
     */
    private function generateNotFoundTests(array $modelData, string $model, string $routePath): string
    {
        $modelName = $modelData['modelName'];
        $modelVariable = lcfirst($modelName);
        $modelPlural = Str::plural(Str::snake($modelName, ' '));

        // Determine if using UUID or integer ID
        $isUuid = false;
        try {
            if (class_exists($model)) {
                $modelInstance = new $model;
                $keyType = $modelInstance->getKeyType();
                $isUuid = ($keyType === 'string' && $modelInstance->getIncrementing() === false);
            }
        } catch (\Throwable $e) {
            // Default to integer
        }

        $nonExistentId = $isUuid ? "'00000000-0000-0000-0000-000000000000'" : '99999';
        $invalidId = $isUuid ? "'invalid-uuid'" : "'not-a-number'";

        $tests = "it('returns 404 when showing non-existent {$modelVariable}', function () {
    // Arrange
    \$nonExistentId = {$nonExistentId};
    
    // Act
    \$response = \$this->getJson(\"{$routePath}/\" . \$nonExistentId);
    
    // Assert
    \$response->assertNotFound();
});\n\n";

        $tests .= "it('returns 404 when updating non-existent {$modelVariable}', function () {
    // Arrange
    \$nonExistentId = {$nonExistentId};
    \$payload = [" . $this->generatePayload($this->tableColumnsService->getAvailableColumns((new $model)->getTable(), ['created_at', 'updated_at'], $model), true) . "];
    
    // Act
    \$response = \$this->putJson(\"{$routePath}/\" . \$nonExistentId, \$payload);
    
    // Assert
    \$response->assertNotFound();
});\n\n";

        $tests .= "it('returns 404 when deleting non-existent {$modelVariable}', function () {
    // Arrange
    \$nonExistentId = {$nonExistentId};
    
    // Act
    \$response = \$this->deleteJson(\"{$routePath}/\" . \$nonExistentId);
    
    // Assert
    \$response->assertNotFound();
});\n\n";

        if ($isUuid) {
            $tests .= "it('returns 404 when ID format is invalid', function () {
    // Arrange
    \$invalidId = {$invalidId};
    
    // Act
    \$response = \$this->getJson(\"{$routePath}/\" . \$invalidId);
    
    // Assert
    \$response->assertNotFound();
});\n\n";
        }

        return $tests;
    }

    /**
     * Generate enhanced authorization tests.
     *
     * @param array $modelData
     * @param string $model
     * @param array $columns
     * @param string $routePath
     * @return string
     */
    private function generateEnhancedAuthorizationTests(array $modelData, string $model, array $columns, string $routePath): string
    {
        $modelName = $modelData['modelName'];
        $modelVariable = lcfirst($modelName);
        $modelPlural = Str::plural(Str::snake($modelName, ' '));
        $authPayload = $this->generatePayload($columns);

        $tests = "it('forbids unauthorized user from viewing {$modelPlural}', function () {
    // Arrange
    \$user = User::factory()->create();
    {$modelName}::factory()->create();
    Sanctum::actingAs(\$user);
    
    // Act
    \$response = \$this->getJson('{$routePath}');
    
    // Assert
    \$response->assertForbidden();
});\n\n";

        $tests .= "it('forbids unauthorized user from creating {$modelVariable}', function () {
    // Arrange
    \$user = User::factory()->create();
    Sanctum::actingAs(\$user);
    
    \$payload = [
{$authPayload}
    ];
    
    // Act
    \$response = \$this->postJson('{$routePath}', \$payload);
    
    // Assert
    \$response->assertForbidden();
});\n\n";

        $tests .= "it('forbids unauthorized user from updating {$modelVariable}', function () {
    // Arrange
    \$user = User::factory()->create();
    \$model = {$modelName}::factory()->create();
    Sanctum::actingAs(\$user);
    
    \$payload = [
{$authPayload}
    ];
    
    // Act
    \$response = \$this->putJson(\"{$routePath}/\" . \$model->id, \$payload);
    
    // Assert
    \$response->assertForbidden();
});\n\n";

        $tests .= "it('forbids unauthorized user from deleting {$modelVariable}', function () {
    // Arrange
    \$user = User::factory()->create();
    \$model = {$modelName}::factory()->create();
    Sanctum::actingAs(\$user);
    
    // Act
    \$response = \$this->deleteJson(\"{$routePath}/\" . \$model->id);
    
    // Assert
    \$response->assertForbidden();
});\n\n";

        return $tests;
    }

    /**
     * Generate edge case tests.
     *
     * @param array $modelData
     * @param string $model
     * @param array $columns
     * @param string $routePath
     * @return string
     */
    private function generateEdgeCaseTests(array $modelData, string $model, array $columns, string $routePath): string
    {
        $modelName = $modelData['modelName'];
        $modelVariable = lcfirst($modelName);
        $modelPlural = Str::plural(Str::snake($modelName, ' '));
        $tests = '';

        // Test empty payload
        $tests .= "it('handles empty payload gracefully', function () {
    // Arrange
    \$payload = [];
    
    // Act
    \$response = \$this->postJson('{$routePath}', \$payload);
    
    // Assert
    \$response->assertStatus(422);
});\n\n";

        // Test SQL injection attempt
        $sqlInjection = addslashes(TestDataHelper::sqlInjectionAttempt());
        $tests .= "it('sanitizes SQL injection attempts in string fields', function () {
    // Arrange
    \$payload = [" . $this->generatePayloadString($columns, ['name' => "'{$sqlInjection}'"]) . "];
    
    // Act
    \$response = \$this->postJson('{$routePath}', \$payload);
    
    // Assert
    // Should either validate and reject, or sanitize and accept
    expect(\$response->status())->toBeIn([201, 422]);
});\n\n";

        // Test XSS attempt
        $xssAttempt = addslashes(TestDataHelper::xssAttempt());
        $tests .= "it('sanitizes XSS attempts in string fields', function () {
    // Arrange
    \$payload = [" . $this->generatePayloadString($columns, ['name' => "'{$xssAttempt}'"]) . "];
    
    // Act
    \$response = \$this->postJson('{$routePath}', \$payload);
    
    // Assert
    // Should either validate and reject, or sanitize and accept
    expect(\$response->status())->toBeIn([201, 422]);
});\n\n";

        // Test boundary values for numeric fields
        foreach ($columns as $column) {
            $columnName = $column['name'];
            $camelCaseName = Str::camel($columnName);
            $type = $column['type'];

            if (in_array($type, ['integer', 'bigint', 'smallint'])) {
                $tests .= "it('handles negative values for {$camelCaseName}', function () {
    // Arrange
    \$payload = [" . $this->generatePayloadString($columns, [$camelCaseName => '-1']) . "];
    
    // Act
    \$response = \$this->postJson('{$routePath}', \$payload);
    
    // Assert
    // Should either validate and reject, or accept if negative values are allowed
    expect(\$response->status())->toBeIn([201, 422]);
});\n\n";
            }

            // Test max length boundary
            if (isset($column['maxLength']) && $column['maxLength'] > 0) {
                $maxLength = (int) $column['maxLength'];
                $tests .= "it('handles max length boundary for {$camelCaseName}', function () {
    // Arrange
    \$maxLengthString = str_repeat('a', {$maxLength});
    \$payload = [" . $this->generatePayloadString($columns, [$camelCaseName => "'\$maxLengthString'"]) . "];
    
    // Act
    \$response = \$this->postJson('{$routePath}', \$payload);
    
    // Assert
    \$response->assertStatus(201);
});\n\n";
            }
        }

        return $tests;
    }

    /**
     * Generate relationship tests.
     *
     * @param array $modelData
     * @param string $model
     * @param array $relationships
     * @param string $routePath
     * @return string
     */
    private function generateRelationshipTests(array $modelData, string $model, array $relationships, string $routePath): string
    {
        if (empty($relationships)) {
            return '';
        }

        $modelName = $modelData['modelName'];
        $modelVariable = lcfirst($modelName);
        $modelPlural = Str::plural(Str::snake($modelName, ' '));
        $tests = '';

        // Test eager loading relationships
        $belongsToRelations = array_filter($relationships, fn($rel) => $rel['type'] === 'belongsTo');
        if (!empty($belongsToRelations)) {
            $firstRelation = reset($belongsToRelations);
            $relationName = Str::camel($firstRelation['name']);
            $tests .= "it('can eager load {$relationName} relationship', function () {
    // Arrange
    \${$modelVariable} = {$modelName}::factory()->create();
    
    // Act
    \$response = \$this->getJson(\"{$routePath}/\" . \${$modelVariable}->id . '?with=' . '{$relationName}');
    
    // Assert
    \$response->assertOk();
    \$data = \$response->json('data');
    expect(\$data)->toHaveKey('{$relationName}');
});\n\n";
        }

        // Test relationship data in responses
        foreach ($belongsToRelations as $relationship) {
            $relationName = Str::camel($relationship['name']);
            $tests .= "it('includes {$relationName} relationship data when loaded', function () {
    // Arrange
    \${$modelVariable} = {$modelName}::factory()->with('{$relationship['name']}')->create();
    
    // Act
    \$response = \$this->getJson(\"{$routePath}/\" . \${$modelVariable}->id);
    
    // Assert
    \$response->assertOk();
    \$data = \$response->json('data');
    if (isset(\$data['{$relationName}'])) {
        expect(\$data['{$relationName}'])->toBeArray();
    }
});\n\n";
        }

        return $tests;
    }

    /**
     * Generate pagination tests.
     *
     * @param array $modelData
     * @param string $model
     * @param string $routePath
     * @return string
     */
    private function generatePaginationTests(array $modelData, string $model, string $routePath): string
    {
        $modelName = $modelData['modelName'];
        $modelPlural = Str::plural(Str::snake($modelName, ' '));
        $defaultPagination = config('laravel_auto_crud.default_pagination', 20);

        $tests = "it('paginates {$modelPlural} with default per page', function () {
    // Arrange
    {$modelName}::factory()->count(25)->create();
    
    // Act
    \$response = \$this->getJson('{$routePath}');
    
    // Assert
    \$response->assertOk();
    \$data = \$response->json('data');
    expect(\$data)->toHaveCount({$defaultPagination});
    expect(\$response->json('meta.current_page'))->toBe(1);
    expect(\$response->json('meta.per_page'))->toBe({$defaultPagination});
});\n\n";

        $tests .= "it('paginates {$modelPlural} with custom per page', function () {
    // Arrange
    {$modelName}::factory()->count(15)->create();
    
    // Act
    \$response = \$this->getJson('{$routePath}?per_page=5');
    
    // Assert
    \$response->assertOk();
    \$data = \$response->json('data');
    expect(\$data)->toHaveCount(5);
    expect(\$response->json('meta.per_page'))->toBe(5);
});\n\n";

        $tests .= "it('handles pagination for empty result set', function () {
    // Arrange
    // No models created
    
    // Act
    \$response = \$this->getJson('{$routePath}');
    
    // Assert
    \$response->assertOk();
    \$data = \$response->json('data');
    expect(\$data)->toBeArray();
    expect(\$data)->toHaveCount(0);
    expect(\$response->json('meta.total'))->toBe(0);
});\n\n";

        $tests .= "it('handles pagination beyond last page', function () {
    // Arrange
    {$modelName}::factory()->count(5)->create();
    
    // Act
    \$response = \$this->getJson('{$routePath}?page=999');
    
    // Assert
    \$response->assertOk();
    \$data = \$response->json('data');
    expect(\$data)->toBeArray();
    expect(\$data)->toHaveCount(0);
});\n\n";

        $tests .= "it('includes pagination metadata', function () {
    // Arrange
    {$modelName}::factory()->count(25)->create();
    
    // Act
    \$response = \$this->getJson('{$routePath}');
    
    // Assert
    \$response->assertOk();
    expect(\$response->json('meta'))->toHaveKeys(['current_page', 'per_page', 'total', 'last_page']);
});\n\n";

        return $tests;
    }

    /**
     * Generate payload string with optional overrides.
     *
     * @param array $columns
     * @param array $overrides
     * @return string
     */
    private function generatePayloadString(array $columns, array $overrides = []): string
    {
        $payload = [];
        foreach ($columns as $column) {
            $camelCaseName = Str::camel($column['name']);
            
            if (isset($overrides[$camelCaseName])) {
                $payload[] = "        '{$camelCaseName}' => {$overrides[$camelCaseName]},";
            } else {
                $value = $this->generateValueForType($column, false);
                $payload[] = "        '{$camelCaseName}' => {$value},";
            }
        }

        return implode("\n", $payload);
    }

    /**
     * Create unit test for Service class.
     *
     * @param array $modelData
     * @param bool $overwrite
     * @return string
     */
    public function createServiceUnitTest(array $modelData, bool $overwrite = false): string
    {
        return $this->fileService->createFromStub($modelData, 'pest_unit_service', 'tests/Unit/Services', 'ServiceTest', $overwrite, function ($modelData) {
            $model = $this->getFullModelNamespace($modelData);
            $modelInstance = new $model;
            $tableName = $modelInstance->getTable();
            $columns = $this->tableColumnsService->getAvailableColumns($tableName, ['created_at', 'updated_at'], $model);

            $hiddenProperties = $this->getHiddenProperties($model);
            $columns = array_filter($columns, function($column) use ($hiddenProperties) {
                return !in_array($column['name'], $hiddenProperties, true);
            });

            $serviceClass = 'App\\Services\\' . $modelData['modelName'] . 'Service';
            $dataClass = 'App\\Data\\' . $modelData['modelName'] . 'Data';
            $modelVariable = lcfirst($modelData['modelName']);

            $createPayload = $this->generatePayload($columns);
            $updatePayload = $this->generatePayload($columns, true);

            $databaseAssertions = [];
            $updateDatabaseAssertions = [];
            foreach ($columns as $column) {
                if (!in_array($column['name'], ['id', 'created_at', 'updated_at', 'deleted_at'])) {
                    $camelCase = Str::camel($column['name']);
                    $value = $this->generateValueForType($column, false);
                    $databaseAssertions[] = "        '{$column['name']}' => " . (str_starts_with($value, "'") ? $value : "{$value}") . ",";
                    
                    $updateValue = $this->generateValueForType($column, true);
                    $updateDatabaseAssertions[] = "        '{$column['name']}' => " . (str_starts_with($updateValue, "'") ? $updateValue : "{$updateValue}") . ",";
                }
            }

            return [
                '{{ modelNamespace }}' => $model,
                '{{ model }}' => $modelData['modelName'],
                '{{ modelVariable }}' => $modelVariable,
                '{{ serviceNamespace }}' => $serviceClass,
                '{{ serviceClass }}' => $modelData['modelName'] . 'Service',
                '{{ dataNamespace }}' => $dataClass,
                '{{ dataClass }}' => $modelData['modelName'] . 'Data',
                '{{ tableName }}' => $tableName,
                '{{ createPayload }}' => $createPayload,
                '{{ updatePayload }}' => $updatePayload,
                '{{ databaseAssertions }}' => implode("\n", $databaseAssertions),
                '{{ updateDatabaseAssertions }}' => implode("\n", $updateDatabaseAssertions),
            ];
        });
    }

    /**
     * Create unit test for Form Request class.
     *
     * @param array $modelData
     * @param string $requestType 'store' or 'update'
     * @param bool $overwrite
     * @return string
     */
    public function createRequestValidationTest(array $modelData, string $requestType, bool $overwrite = false): string
    {
        $suffix = $requestType === 'store' ? 'Store' : 'Update';
        return $this->fileService->createFromStub($modelData, 'pest_unit_request', 'tests/Unit/Requests', $suffix . 'RequestTest', $overwrite, function ($modelData) use ($requestType, $suffix) {
            $model = $this->getFullModelNamespace($modelData);
            $modelInstance = new $model;
            $tableName = $modelInstance->getTable();
            $columns = $this->tableColumnsService->getAvailableColumns($tableName, ['created_at', 'updated_at'], $model);

            $hiddenProperties = $this->getHiddenProperties($model);
            $columns = array_filter($columns, function($column) use ($hiddenProperties) {
                return !in_array($column['name'], $hiddenProperties, true);
            });

            $requestClass = 'App\\Http\\Requests\\' . $modelData['modelName'] . 'Requests\\' . $modelData['modelName'] . $suffix . 'Request';

            $requiredFields = [];
            $typeAssertions = [];
            $formatAssertions = [];

            foreach ($columns as $column) {
                $camelCase = Str::camel($column['name']);
                $isNullable = $column['isNullable'] ?? false;

                if (!$isNullable && !in_array($column['name'], ['id', 'created_at', 'updated_at', 'deleted_at'])) {
                    $requiredFields[] = "    expect(\$rules)->toHaveKey('{$camelCase}');";
                }

                if (in_array($column['type'], ['integer', 'bigint', 'smallint'])) {
                    $typeAssertions[] = "    expect(\$rules['{$camelCase}'])->toContain('integer');";
                }

                if (str_contains(strtolower($column['name']), 'email')) {
                    $formatAssertions[] = "    expect(\$rules['{$camelCase}'])->toContain('email');";
                }

                if (str_contains(strtolower($column['name']), 'url')) {
                    $formatAssertions[] = "    expect(\$rules['{$camelCase}'])->toContain('url');";
                }
            }

            return [
                '{{ requestNamespace }}' => $requestClass,
                '{{ requestClass }}' => $modelData['modelName'] . $suffix . 'Request',
                '{{ requiredFieldsAssertions }}' => implode("\n", $requiredFields),
                '{{ typeValidationAssertions }}' => implode("\n", $typeAssertions),
                '{{ formatValidationAssertions }}' => implode("\n", $formatAssertions),
            ];
        });
    }

    /**
     * Create unit test for Policy class.
     *
     * @param array $modelData
     * @param bool $overwrite
     * @return string
     */
    public function createPolicyTest(array $modelData, bool $overwrite = false): string
    {
        return $this->fileService->createFromStub($modelData, 'pest_unit_policy', 'tests/Unit/Policies', 'PolicyTest', $overwrite, function ($modelData) {
            $model = $this->getFullModelNamespace($modelData);
            $policyClass = 'App\\Policies\\' . $modelData['modelName'] . 'Policy';
            $modelVariable = lcfirst($modelData['modelName']);
            $modelPlural = Str::plural(Str::snake($modelData['modelName'], ' '));
            $seederClass = $this->getSeederClass();

            return [
                '{{ modelNamespace }}' => $model,
                '{{ model }}' => $modelData['modelName'],
                '{{ modelVariable }}' => $modelVariable,
                '{{ modelPlural }}' => $modelPlural,
                '{{ policyNamespace }}' => $policyClass,
                '{{ policyClass }}' => $modelData['modelName'] . 'Policy',
                '{{ seederCall }}' => $this->generateSeederCall($seederClass),
            ];
        });
    }

    /**
     * Create unit test for Resource class.
     *
     * @param array $modelData
     * @param bool $overwrite
     * @return string
     */
    public function createResourceTest(array $modelData, bool $overwrite = false): string
    {
        return $this->fileService->createFromStub($modelData, 'pest_unit_resource', 'tests/Unit/Resources', 'ResourceTest', $overwrite, function ($modelData) {
            $model = $this->getFullModelNamespace($modelData);
            $modelInstance = new $model;
            $tableName = $modelInstance->getTable();
            $columns = $this->tableColumnsService->getAvailableColumns($tableName, ['created_at', 'updated_at'], $model);

            $hiddenProperties = $this->getHiddenProperties($model);
            $columns = array_filter($columns, function($column) use ($hiddenProperties) {
                return !in_array($column['name'], $hiddenProperties, true);
            });

            $resourceClass = 'App\\Http\\Resources\\' . $modelData['modelName'] . 'Resource';
            $modelVariable = lcfirst($modelData['modelName']);

            $relationships = \Mrmarchone\LaravelAutoCrud\Services\RelationshipDetector::detectRelationships($model);
            
            $resourcePropertyAssertions = [];
            foreach ($columns as $column) {
                if (!in_array($column['name'], ['id', 'created_at', 'updated_at', 'deleted_at'])) {
                    $camelCase = Str::camel($column['name']);
                    $resourcePropertyAssertions[] = "    expect(\$array)->toHaveKey('{$camelCase}');";
                }
            }

            $relationshipSetup = '';
            $relationshipAssertions = [];
            if (!empty($relationships)) {
                $firstRelation = reset($relationships);
                $relationName = $firstRelation['name'];
                $camelCase = Str::camel($relationName);
                $relationshipSetup = "    \${$modelVariable}->load('{$relationName}');";
                $relationshipAssertions[] = "    if (isset(\$array['{$camelCase}'])) {";
                $relationshipAssertions[] = "        expect(\$array['{$camelCase}'])->toBeArray();";
                $relationshipAssertions[] = "    }";
            }

            return [
                '{{ modelNamespace }}' => $model,
                '{{ model }}' => $modelData['modelName'],
                '{{ modelVariable }}' => $modelVariable,
                '{{ resourceNamespace }}' => $resourceClass,
                '{{ resourceClass }}' => $modelData['modelName'] . 'Resource',
                '{{ resourcePropertyAssertions }}' => implode("\n", $resourcePropertyAssertions),
                '{{ relationshipSetup }}' => $relationshipSetup,
                '{{ relationshipAssertions }}' => implode("\n", $relationshipAssertions),
            ];
        });
    }

    public function createBulkFeatureTest(array $modelData, bool $overwrite = false): string
    {
        return $this->fileService->createFromStub($modelData, 'pest_feature_bulk', 'tests/Feature/' . $modelData['modelName'], 'BulkEndpointsTest', $overwrite, function ($modelData) {
            $model = $this->getFullModelNamespace($modelData);
            $modelInstance = new $model;
            $tableName = $modelInstance->getTable();
            $columns = $this->tableColumnsService->getAvailableColumns($tableName, ['created_at', 'updated_at'], $model);

            $hiddenProperties = $this->getHiddenProperties($model);
            $columns = array_filter($columns, function($column) use ($hiddenProperties) {
                return !in_array($column['name'], $hiddenProperties, true);
            });

            $routePath = '/api/' . Str::plural(Str::snake($modelData['modelName']));
            $modelVariable = lcfirst($modelData['modelName']);

            $createPayloadItem = '[' . $this->generatePayload($columns) . ']';
            $createPayloadItems = $this->generatePayload($columns);
            $lines = explode("\n", trim($createPayloadItems));
            $createPayloadItems = implode(",\n", $lines) . ",\n" . implode(",\n", $lines);

            $updatePayloadItem = '[' . $this->generatePayload($columns, true) . ']';

            $enumUseStatements = $this->collectEnumUseStatements($columns, $modelData);
            $seederClass = $this->getSeederClass();

            $uniqueKey = $this->detectUniqueKey($columns);
            $uniqueKeyStoreTests = '';
            $uniqueKeyUpdateTests = '';

            if ($uniqueKey) {
                $uniqueKeyStoreTests = $this->generateUniqueKeyStoreTests($modelData, $routePath, $createPayloadItem);
                $uniqueKeyUpdateTests = $this->generateUniqueKeyUpdateTests($modelData, $routePath, $modelVariable, $updatePayloadItem);
            }

            return [
                '{{ modelNamespace }}' => $model,
                '{{ model }}' => $modelData['modelName'],
                '{{ modelPlural }}' => Str::plural(Str::snake($modelData['modelName'], ' ')),
                '{{ modelVariable }}' => $modelVariable,
                '{{ routePath }}' => $routePath,
                '{{ tableName }}' => $tableName,
                '{{ seederClass }}' => $seederClass,
                '{{ seederCall }}' => $this->generateSeederCall($seederClass),
                '{{ responseMessagesNamespace }}' => 'Mrmarchone\\LaravelAutoCrud\\Enums\\ResponseMessages',
                '{{ createPayloadItem }}' => $createPayloadItem,
                '{{ createPayloadItems }}' => $createPayloadItems,
                '{{ updatePayloadItem }}' => $updatePayloadItem,
                '{{ uniqueKeyStoreTests }}' => $uniqueKeyStoreTests,
                '{{ uniqueKeyUpdateTests }}' => $uniqueKeyUpdateTests,
                '{{ enumUseStatements }}' => $enumUseStatements,
            ];
        });
    }

    private function detectUniqueKey(array $columns): ?string
    {
        $uniqueFieldNames = ['slug', 'code', 'sku', 'email', 'username', 'uuid'];
        
        foreach ($columns as $column) {
            if (in_array($column['name'], $uniqueFieldNames, true)) {
                return $column['name'];
            }
        }
        
        return null;
    }

    private function generateUniqueKeyStoreTests(array $modelData, string $routePath, string $createPayloadItem): string
    {
        return "\n" . "it('validates duplicate unique keys on store', function () {
    \$payload = [
        'items' => [
            {$createPayloadItem},
            {$createPayloadItem},
        ]
    ];

    \$response = \$this->postJson('{$routePath}/bulk', \$payload);
    \$response->assertStatus(422);
});\n";
    }

    private function generateUniqueKeyUpdateTests(array $modelData, string $routePath, string $modelVariable, string $updatePayloadItem): string
    {
        $modelName = $modelData['modelName'];
        
        return "\n" . "it('validates duplicate unique keys on update', function () {
    \${$modelVariable}1 = {$modelName}::factory()->create();
    \${$modelVariable}2 = {$modelName}::factory()->create();
    
    \$payload = [
        'items' => [
            array_merge(['id' => \${$modelVariable}1->id], {$updatePayloadItem}),
            array_merge(['id' => \${$modelVariable}2->id], {$updatePayloadItem}),
        ]
    ];

    \$response = \$this->putJson('{$routePath}/bulk', \$payload);
    \$response->assertStatus(422);
});\n";
    }

    public function createBulkServiceUnitTest(array $modelData, bool $overwrite = false, ?string $uniqueKey = null): string
    {
        $modifiedModelData = $modelData;
        $modifiedModelData['modelName'] = $modelData['modelName'] . 'Bulk';

        return $this->fileService->createFromStub($modifiedModelData, 'pest_unit_bulk_service', 'tests/Unit/Services', 'ServiceTest', $overwrite, function ($modelData) use ($uniqueKey) {
            $originalModelName = str_replace('Bulk', '', $modelData['modelName']);
            $model = $this->getFullModelNamespace([
                'modelName' => $originalModelName,
                'namespace' => $modelData['namespace'] ?? null,
            ]);
            $modelInstance = new $model;
            $tableName = $modelInstance->getTable();
            $columns = $this->tableColumnsService->getAvailableColumns($tableName, ['created_at', 'updated_at'], $model);

            $hiddenProperties = $this->getHiddenProperties($model);
            $columns = array_filter($columns, function($column) use ($hiddenProperties) {
                return !in_array($column['name'], $hiddenProperties, true);
            });

            $modelVariable = lcfirst($originalModelName);
            $serviceClass = 'App\\Services\\' . $originalModelName . 'BulkService';
            $dataClass = 'App\\Data\\' . $originalModelName . 'Data';

            $createPayloadItem = '[' . $this->generatePayload($columns) . ']';
            $createPayloadItems = $this->generatePayload($columns);
            $lines = explode("\n", trim($createPayloadItems));
            $createPayloadItems = "            [" . implode(",\n            ", array_map('trim', $lines)) . "],\n            [" . implode(",\n            ", array_map('trim', $lines)) . "]";

            $updatePayloadItem = $this->generatePayload($columns, true);

            $detectedUniqueKey = $uniqueKey ?? $this->detectUniqueKey($columns);
            $uniqueKeyStoreTests = '';
            $uniqueKeyUpdateTests = '';

            if ($detectedUniqueKey) {
                $uniqueKeyStoreTests = $this->generateBulkServiceUniqueKeyStoreTests($originalModelName, $model, $createPayloadItem, $detectedUniqueKey);
                $uniqueKeyUpdateTests = $this->generateBulkServiceUniqueKeyUpdateTests($originalModelName, $model, $modelVariable, $updatePayloadItem, $detectedUniqueKey);
            }

            return [
                '{{ modelNamespace }}' => $model,
                '{{ model }}' => $originalModelName,
                '{{ modelVariable }}' => $modelVariable,
                '{{ serviceNamespace }}' => $serviceClass,
                '{{ service }}' => $originalModelName . 'BulkService',
                '{{ dataNamespace }}' => $dataClass,
                '{{ tableName }}' => $tableName,
                '{{ createPayloadItem }}' => $createPayloadItem,
                '{{ createPayloadItems }}' => $createPayloadItems,
                '{{ updatePayloadItem }}' => $updatePayloadItem,
                '{{ uniqueKeyStoreTests }}' => $uniqueKeyStoreTests,
                '{{ uniqueKeyUpdateTests }}' => $uniqueKeyUpdateTests,
            ];
        });
    }

    private function generateBulkServiceUniqueKeyStoreTests(string $modelName, string $model, string $createPayloadItem, string $uniqueKey): string
    {
        return "\n" . "it('validates duplicate {$uniqueKey} on store', function () {
    \$service = app({$modelName}BulkService::class);
    \$dataArray = [
        {$createPayloadItem},
        {$createPayloadItem},
    ];

    expect(fn () => \$service->store(\$dataArray))->toThrow(ValidationException::class);
});

it('validates existing {$uniqueKey} on store', function () {
    \$service = app({$modelName}BulkService::class);
    {$modelName}::factory()->create({$createPayloadItem});

    \$dataArray = [
        {$createPayloadItem},
    ];

    expect(fn () => \$service->store(\$dataArray))->toThrow(ValidationException::class);
});\n";
    }

    private function generateBulkServiceUniqueKeyUpdateTests(string $modelName, string $model, string $modelVariable, string $updatePayloadItem, string $uniqueKey): string
    {
        return "\n" . "it('validates duplicate {$uniqueKey} on update', function () {
    \$service = app({$modelName}BulkService::class);
    \${$modelVariable}1 = {$modelName}::factory()->create();
    \${$modelVariable}2 = {$modelName}::factory()->create();

    \$dataArray = [
        array_merge(['id' => \${$modelVariable}1->id], [{$updatePayloadItem}]),
        array_merge(['id' => \${$modelVariable}2->id], [{$updatePayloadItem}]),
    ];

    expect(fn () => \$service->update(\$dataArray))->toThrow(ValidationException::class);
});

it('validates {$uniqueKey} conflicts on update', function () {
    \$service = app({$modelName}BulkService::class);
    \$existing = {$modelName}::factory()->create();
    \${$modelVariable}1 = {$modelName}::factory()->create();

    \$dataArray = [
        ['id' => \${$modelVariable}1->id, '{$uniqueKey}' => \$existing->{$uniqueKey}],
    ];

    expect(fn () => \$service->update(\$dataArray))->toThrow(ValidationException::class);
});\n";
    }
}

