<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Builders;

use Illuminate\Support\Str;
use Mrmarchone\LaravelAutoCrud\Services\TableColumnsService;

class PestBuilder extends BaseBuilder
{
    protected TableColumnsService $tableColumnsService;

    public function __construct()
    {
        parent::__construct();
        $this->tableColumnsService = new TableColumnsService;
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

            return [
                '{{ modelNamespace }}' => $model,
                '{{ model }}' => $modelData['modelName'],
                '{{ modelPlural }}' => Str::plural(Str::snake($modelData['modelName'], ' ')),
                '{{ modelVariable }}' => lcfirst($modelData['modelName']),
                '{{ routePath }}' => '/api/' . Str::plural(Str::snake($modelData['modelName'])),
                '{{ tableName }}' => $tableName,
                '{{ seederClass }}' => config('laravel_auto_crud.test_settings.seeder_class', 'Database\\Seeders\\RolesAndPermissionsSeeder'),
                '{{ responseMessagesNamespace }}' => 'Mrmarchone\\LaravelAutoCrud\\Enums\\ResponseMessages',
                '{{ createPayload }}' => $this->generatePayload($columns),
                '{{ updatePayload }}' => $this->generatePayload($columns, true),
                '{{ authorizationTests }}' => $authorizationTests,
                '{{ extraTests }}' => $extraTests,
            ];
        });
    }

    private function generateExtraTests(array $modelData, string $model, string $searchField): string
    {
        $modelName = $modelData['modelName'];
        $modelVariable = lcfirst($modelName);
        $modelPlural = Str::plural(Str::snake($modelName, ' '));
        $routePath = '/api/' . Str::plural(Str::snake($modelName));

        $tests = "\n";

        // Only add search test if model has search scope
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
});\n";

        return $tests;
    }

    private function generatePayload(array $columns, bool $isUpdate = false): string
    {
        $payload = [];
        foreach ($columns as $column) {
            $value = $this->generateValueForType($column, $isUpdate);
            $payload[] = "        '{$column['name']}' => {$value},";
        }

        return implode("\n", $payload);
    }

    private function generateValueForType(array $column, bool $isUpdate = false): string
    {
        $type = $column['type'];
        $suffix = $isUpdate ? ' updated' : '';

        if ($column['is_translatable'] ?? false) {
            return "['en' => 'Sample " . $column['name'] . $suffix . "']";
        }

        if (!empty($column['allowed_values'])) {
            return "'" . $column['allowed_values'][0] . "'";
        }

        return match ($type) {
            'integer', 'bigint', 'smallint' => '1',
            'decimal', 'float', 'double' => '10.5',
            'boolean' => 'true',
            'date' => "'2025-01-01'",
            'datetime', 'timestamp' => "'2025-01-01 10:00:00'",
            'json' => '[]',
            default => "'Sample " . $column['name'] . $suffix . "'",
        };
    }
}
