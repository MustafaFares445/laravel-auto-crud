<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Console\Commands;

use Illuminate\Console\Command;
use Mrmarchone\LaravelAutoCrud\Services\CRUDGenerator;
use Mrmarchone\LaravelAutoCrud\Services\DatabaseValidatorService;
use Mrmarchone\LaravelAutoCrud\Services\DocumentationGenerator;
use Mrmarchone\LaravelAutoCrud\Services\HelperService;
use Mrmarchone\LaravelAutoCrud\Services\ModelService;

use function Laravel\Prompts\alert;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\note;
use function Laravel\Prompts\select;
use function Laravel\Prompts\warning;

class GenerateAutoCrudCommand extends Command
{
    protected $signature = 'auto-crud:generate
    {--A|all : Generate all files (controller, request, resource, routes, service, docs) without overwriting existing files}
    {--FA|force-all : Generate all files and overwrite existing files}
    {--M|model=* : Specify model name(s) to generate CRUD for (e.g., --model=User --model=Post)}
    {--MP|model-path= : Custom path to models directory (default: app/Models/)}
    {--T|type=* : Output type: "api", "web", or both (e.g., --type=api --type=web)}
    {--S|service : Include a Service class for business logic}
    {--F|filter : Include Spatie Query Builder filter support (requires --pattern=spatie-data)}
    {--O|overwrite : Overwrite existing files without confirmation}
    {--P|pattern=normal : Data pattern: "normal" or "spatie-data" for Spatie Laravel Data}
    {--RM|response-messages : Add ResponseMessages enum for standardized API responses (e.g., "data retrieved successfully")}
    {--NP|no-pagination : Use Model::all() instead of pagination in index method}
    {--PO|policy : Generate Policy class with permission-based authorization}
    {--C|curl : Generate cURL command examples for API endpoints}
    {--PM|postman : Generate Postman collection JSON file}
    {--SA|swagger-api : Generate Swagger/OpenAPI specification}';

    protected $description = 'Generate complete CRUD scaffolding (Controller, Request, Resource, Routes, Service) for Eloquent models';

    public function __construct(
        protected DatabaseValidatorService $databaseValidatorService,
        protected CRUDGenerator $CRUDGenerator,
        protected DocumentationGenerator $documentationGenerator,
    ) {
        parent::__construct();
    }

    public function handle(): void
    {
        HelperService::displaySignature();

        if (! $this->everythingIsOk()) {
            return;
        }

        if ($this->option('all') || $this->option('force-all')) {
            $this->handleBatchMode();

            return;
        }

        if ($this->hasAnyOptionProvided()) {
            $this->handleCommandLineMode();

            return;
        }

        $this->handleInteractiveMode();
    }

    private function handleInteractiveMode(): void
    {
        $modelPath = $this->option('model-path') ?? config('laravel_auto_crud.fallback_models_path', 'app/Models/');

        // Step 1: Select Models
        $availableModels = ModelService::getAvailableModels($modelPath);

        if (empty($availableModels)) {
            alert("No models found in {$modelPath}. Make sure your models exist and the path is correct.");

            return;
        }

        $selectedModels = multiselect(
            label: '📦 Select the model(s) to generate CRUD for',
            options: $availableModels,
            required: true,
            hint: 'Use space to select, enter to confirm'
        );

        // Step 2: Select Controller Type
        $controllerTypes = multiselect(
            label: '🌐 What type of controller do you want to generate?',
            options: [
                'api' => 'API Controller (JSON responses)',
                'web' => 'Web Controller (Blade views)',
            ],
            default: ['api'],
            required: true,
            hint: 'Select one or both'
        );

        // Step 3: Select Data Pattern
        $pattern = select(
            label: '📋 Which data pattern do you want to use?',
            options: [
                'normal' => 'Normal - Standard Laravel Form Requests',
                'spatie-data' => 'Spatie Data - Use spatie/laravel-data DTOs',
            ],
            default: 'normal',
            hint: 'Spatie Data provides type-safe DTOs and validation'
        );

        // Step 4: Select Index Method Style
        $usePagination = select(
            label: '📄 How should the index method return data?',
            options: [
                'pagination' => 'Pagination - Return paginated results (recommended for large datasets)',
                'all' => 'Get All - Return all records using Model::all()',
            ],
            default: 'pagination',
            hint: 'Pagination is recommended for better performance'
        );

        // Step 5: Select Features
        $featureOptions = [
            'service' => '🔧 Service Layer - Extract business logic to service classes',
            'response-messages' => '💬 Response Messages - Add ResponseMessages enum for standardized API responses',
            'policy' => '🔐 Policy - Generate Policy class with permission-based authorization',
        ];

        if ($pattern === 'spatie-data') {
            $featureOptions['filter'] = '🔍 Filters - Add Spatie Query Builder filter support';
        }

        $selectedFeatures = multiselect(
            label: '⚡ Select additional features',
            options: $featureOptions,
            default: [],
            hint: 'Optional - press enter to skip'
        );

        // Step 6: Select Documentation (only for API)
        $selectedDocs = [];
        if (in_array('api', $controllerTypes)) {
            $selectedDocs = multiselect(
                label: '📚 Generate API documentation?',
                options: [
                    'curl' => '🖥️  cURL - Command-line examples',
                    'postman' => '📮 Postman - Collection JSON file',
                    'swagger-api' => '📖 Swagger - OpenAPI specification',
                ],
                default: [],
                hint: 'Optional - press enter to skip'
            );
        }

        // Step 6: Overwrite confirmation
        $overwrite = confirm(
            label: '🔄 Overwrite existing files without asking?',
            default: false,
            hint: 'If No, you will be prompted for each existing file'
        );

        // Step 8: Show summary and confirm
        $this->showSummary($selectedModels, $controllerTypes, $pattern, $usePagination, $selectedFeatures, $selectedDocs, $overwrite);

        if (! confirm(label: '🚀 Proceed with generation?', default: true)) {
            warning('Generation cancelled.');

            return;
        }

        // Set all options
        $this->input->setOption('model', $selectedModels);
        $this->input->setOption('type', $controllerTypes);
        $this->input->setOption('pattern', $pattern);
        $this->input->setOption('no-pagination', $usePagination === 'all');
        $this->input->setOption('service', in_array('service', $selectedFeatures));
        $this->input->setOption('filter', in_array('filter', $selectedFeatures));
        $this->input->setOption('response-messages', in_array('response-messages', $selectedFeatures));
        $this->input->setOption('policy', in_array('policy', $selectedFeatures));
        $this->input->setOption('curl', in_array('curl', $selectedDocs));
        $this->input->setOption('postman', in_array('postman', $selectedDocs));
        $this->input->setOption('swagger-api', in_array('swagger-api', $selectedDocs));
        $this->input->setOption('overwrite', $overwrite);

        // Generate
        $this->generateForModels($selectedModels);
    }

    private function showSummary(array $models, array $types, string $pattern, string $pagination, array $features, array $docs, bool $overwrite): void
    {
        note('📋 Generation Summary');

        info('Models: ' . implode(', ', $models));
        info('Controller Types: ' . implode(', ', $types));
        info('Pattern: ' . $pattern);
        info('Index Method: ' . ($pagination === 'pagination' ? 'Paginated' : 'Get All'));

        if (! empty($features)) {
            info('Features: ' . implode(', ', $features));
        }

        if (! empty($docs)) {
            info('Documentation: ' . implode(', ', $docs));
        }

        info('Overwrite: ' . ($overwrite ? 'Yes' : 'No'));
    }

    private function handleBatchMode(): void
    {
        $modelPath = $this->option('model-path') ?? config('laravel_auto_crud.fallback_models_path', 'app/Models/');

        $models = ModelService::showModels($modelPath);

        if (empty($models)) {
            alert("Can't find models. Make sure models exist in the specified path.");

            return;
        }

        $this->forceAllBooleanOptions();
        $this->input->setOption('overwrite', $this->option('force-all'));

        $this->generateForModels($models);
    }

    private function handleCommandLineMode(): void
    {
        $modelPath = $this->option('model-path') ?? config('laravel_auto_crud.fallback_models_path', 'app/Models/');

        $models = [];
        if (count($this->option('model'))) {
            foreach ($this->option('model') as $model) {
                $modelExists = ModelService::isModelExists($model, $modelPath);
                if (! $modelExists) {
                    alert('Model ' . $model . ' does not exist');

                    continue;
                }
                $models[] = $modelExists;
            }
        } else {
            $models = ModelService::showModels($modelPath);
        }

        if (empty($models)) {
            alert("Can't find models. If the models folder is outside app directory, make sure it's loaded in psr-4.");

            return;
        }

        // Ask for type if not provided
        if (empty($this->option('type'))) {
            HelperService::askForType($this->input, $this->option('type'));
        }

        $this->generateForModels($models);
    }

    private function generateForModels(array $models): void
    {
        foreach ($models as $model) {
            $modelData = ModelService::resolveModelName($model);
            $table = ModelService::getFullModelNamespace($modelData, fn($modelName) => new $modelName);

            if (! $this->databaseValidatorService->checkTableExists($table)) {
                $createFiles = confirm(
                    label: "⚠️  Table '{$table}' not found. Create empty CRUD files anyway?",
                    default: false
                );

                if (! $createFiles) {
                    warning("Skipped: {$model}");

                    continue;
                }
            }

            $this->CRUDGenerator->generate($modelData, $this->options());
            $this->documentationGenerator->generate($modelData, $this->options(), count($models) > 1);
        }

        info('✅ CRUD generation completed!');
    }

    private function hasAnyOptionProvided(): bool
    {
        return count($this->option('model')) > 0
            || count($this->option('type')) > 0
            || $this->option('service')
            || $this->option('filter')
            || $this->option('response-messages')
            || $this->option('policy')
            || $this->option('no-pagination')
            || $this->option('curl')
            || $this->option('postman')
            || $this->option('swagger-api')
            || $this->option('pattern') !== 'normal';
    }

    private function everythingIsOk(): bool
    {
        if (! $this->databaseValidatorService->checkDataBaseConnection()) {
            alert('❌ Database connection error. Please check your database configuration.');

            return false;
        }

        if ($this->option('type') && empty(array_intersect($this->option('type'), ['api', 'web']))) {
            alert('❌ Invalid type. Use "api", "web", or both.');

            return false;
        }

        if ($this->option('pattern') === 'spatie-data' && ! class_exists(\Spatie\LaravelData\Data::class)) {
            alert('❌ The "spatie/laravel-data" package is required for spatie-data pattern. Install it with: composer require spatie/laravel-data');

            return false;
        }

        if ($this->option('filter') && $this->option('pattern') !== 'spatie-data') {
            alert('❌ Filter option requires --pattern=spatie-data');

            return false;
        }

        return true;
    }

    private function forceAllBooleanOptions(): void
    {
        $this->input->setOption('service', true);
        $this->input->setOption('policy', true);
        $this->input->setOption('curl', true);
        $this->input->setOption('postman', true);
        $this->input->setOption('swagger-api', true);
        $this->input->setOption('response-messages', true);
        $this->input->setOption('type', ['api', 'web']);
    }
}
