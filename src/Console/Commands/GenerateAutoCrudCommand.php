<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
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
use function Laravel\Prompts\text;
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
    {--only-selected : Generate only selected options (skip core CRUD files)}
    {--P|pattern=normal : Data pattern: "normal" or "spatie-data" for Spatie Laravel Data}
    {--RM|response-messages : Add ResponseMessages enum for standardized API responses (e.g., "data retrieved successfully")}
    {--NP|no-pagination : Use Model::all() instead of pagination in index method}
    {--PO|policy : Generate Policy class with permission-based authorization}
    {--C|curl : Generate cURL command examples for API endpoints}
    {--PM|postman : Generate Postman collection JSON file}
    {--SA|swagger-api : Generate Swagger/OpenAPI specification}
    {--PT|pest : Generate Pest test files (Feature and Unit)}
    {--FC|factory : Generate Model Factory}
    {--PS|permissions-seeder : Generate permission seeder and update PermissionGroup enum}
    {--CF|controller-folder= : Custom folder path for controllers (e.g., Http/Controllers/Admin or Http/Controllers/API/V1)}
    {--bulk=* : Bulk endpoints to generate (e.g., --bulk=create --bulk=update --bulk=delete or --bulk=all)}';

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

        [$modelOptions, $modelLookup] = $this->indexedOptions(
            array_combine($availableModels, $availableModels),
            includeAll: true,
            allLabel: '✨ All Models'
        );
        $selectedModelIndexes = multiselect(
            label: '📦 Select the model(s) to generate CRUD for',
            options: $modelOptions,
            required: true,
            hint: 'Type number(s) or use space to select, enter to confirm'
        );
        $selectedModels = $this->collectSelections($modelLookup, $selectedModelIndexes);

        // Handle "All" selection
        if (in_array('all', $selectedModels)) {
            $selectedModels = $availableModels;
        }

        // Step 2: Select Controller Type
        [$controllerOptions, $controllerLookup] = $this->indexedOptions(
            [
                'api' => 'API Controller (JSON responses)',
                'web' => 'Web Controller (Blade views)',
            ],
            includeAll: true,
            allLabel: '✨ Both (API + Web)'
        );
        $controllerDefaults = $this->defaultIndexes($controllerLookup, ['api']);
        $controllerTypeIndexes = multiselect(
            label: '🌐 What type of controller do you want to generate?',
            options: $controllerOptions,
            default: $controllerDefaults,
            required: true,
            hint: 'Type number to jump, space to select, enter to confirm'
        );
        $controllerTypes = $this->collectSelections($controllerLookup, $controllerTypeIndexes);

        // Handle "All" selection
        if (in_array('all', $controllerTypes)) {
            $controllerTypes = ['api', 'web'];
        }

        // Step 2.5: Select Controller Folder (if not provided via option)
        $controllerFolder = $this->option('controller-folder');
        if (!$controllerFolder) {
            $defaultApiFolder = config('laravel_auto_crud.default_api_controller_folder', 'Http/Controllers/API');
            $defaultWebFolder = config('laravel_auto_crud.default_web_controller_folder', 'Http/Controllers');

            $existingFolders = $this->getExistingControllerFolders();
            if (!empty($existingFolders)) {
                note('Existing controller folders: ' . implode(', ', $existingFolders));
            }

            $folderOptions = [
                'default' => 'Use default folders (API: ' . $defaultApiFolder . ', Web: ' . $defaultWebFolder . ')',
            ];

            foreach ($existingFolders as $folder) {
                $folderOptions[$folder] = $folder;
            }

            $folderOptions['custom'] = 'Enter custom folder path';
            
            [$folderPromptOptions, $folderLookup] = $this->indexedOptions($folderOptions);
            $selectedFolderIndex = select(
                label: '📁 Where should controllers be placed?',
                options: $folderPromptOptions,
                default: 1,
                hint: 'Type number to select, enter to confirm'
            );
            
            $selectedFolderKey = $folderLookup[$selectedFolderIndex];
            if ($selectedFolderKey === 'custom') {
                $controllerFolder = text(
                    label: 'Enter controller folder path',
                    placeholder: 'e.g., Admin or Http/Controllers/Admin',
                    default: '',
                    required: true,
                    validate: fn ($value) => empty(trim($value)) ? 'Folder path cannot be empty' : null
                );
                
                // Normalize the path
                $controllerFolder = $this->normalizeControllerFolderPath($controllerFolder);
            } elseif ($selectedFolderKey !== 'default') {
                $controllerFolder = $this->normalizeControllerFolderPath($selectedFolderKey);
            }
        }

        // Step 3: Select Data Pattern
        [$patternOptions, $patternLookup] = $this->indexedOptions([
            'normal' => 'Normal - Standard Laravel Form Requests',
            'spatie-data' => 'Spatie Data - Use spatie/laravel-data DTOs',
        ]);
        $patternDefault = $this->defaultIndexes($patternLookup, ['normal'])[0] ?? array_key_first($patternOptions);
        $selectedPatternIndex = select(
            label: '📋 Which data pattern do you want to use?',
            options: $patternOptions,
            default: $patternDefault,
            hint: 'Type number to select, enter to confirm'
        );
        $pattern = $patternLookup[$selectedPatternIndex];

        // Step 4: Select Index Method Style
        [$paginationOptions, $paginationLookup] = $this->indexedOptions([
            'pagination' => 'Pagination - Return paginated results (recommended)',
            'all' => 'Get All - Return all records using Model::all()',
        ]);
        $paginationDefault = $this->defaultIndexes($paginationLookup, ['pagination'])[0] ?? array_key_first($paginationOptions);
        $selectedPaginationIndex = select(
            label: '📄 How should the index method return data?',
            options: $paginationOptions,
            default: $paginationDefault,
            hint: 'Type number to select, enter to confirm'
        );
        $usePagination = $paginationLookup[$selectedPaginationIndex];

        // Step 5: Select Features
        $featureOptions = [
            'service' => '🔧 Service Layer - Extract business logic to service classes',
            'response-messages' => '💬 Response Messages - Add ResponseMessages enum for standardized API responses',
            'policy' => '🔐 Policy - Generate Policy class with permission-based authorization',
            'pest' => '🧪 Pest Tests - Generate Pest test files (Feature and Unit tests)',
            'factory' => '🏭 Factory - Generate Model Factory',
            'permissions-seeder' => '🔑 Permissions Seeder - Generate permission seeder and update PermissionGroup enum',
        ];

        if ($pattern === 'spatie-data') {
            $featureOptions['filter'] = '🔍 Filters - Add Spatie Query Builder filter support';
        }

        [$featurePromptOptions, $featureLookup] = $this->indexedOptions(
            $featureOptions,
            includeAll: true,
            allLabel: '✨ All Features'
        );
        $selectedFeatureIndexes = multiselect(
            label: '⚡ Select additional features',
            options: $featurePromptOptions,
            default: [],
            hint: 'Type number to jump, space to select, enter to confirm (optional)'
        );
        $selectedFeatures = $this->collectSelections($featureLookup, $selectedFeatureIndexes);

        // Handle "All" selection
        if (in_array('all', $selectedFeatures)) {
            $selectedFeatures = array_keys(array_filter($featureOptions, fn($key) => $key !== 'all', ARRAY_FILTER_USE_KEY));
        }

        // Step 6: Select Documentation (only for API)
        $selectedDocs = [];
        if (in_array('api', $controllerTypes)) {
            [$docsPromptOptions, $docsLookup] = $this->indexedOptions(
                [
                    'curl' => '🖥️  cURL - Command-line examples',
                    'postman' => '📮 Postman - Collection JSON file',
                    'swagger-api' => '📖 Swagger - OpenAPI specification',
                ],
                includeAll: true,
                allLabel: '✨ All Documentation'
            );
            $selectedDocsIndexes = multiselect(
                label: '📚 Generate API documentation?',
                options: $docsPromptOptions,
                default: [],
                hint: 'Type number to jump, space to select, enter to confirm (optional)'
            );
            $selectedDocs = $this->collectSelections($docsLookup, $selectedDocsIndexes);

            // Handle "All" selection
            if (in_array('all', $selectedDocs)) {
                $selectedDocs = ['curl', 'postman', 'swagger-api'];
            }
        }

        // Step 6.5: Select Bulk Endpoints
        $selectedBulkEndpoints = [];
        [$bulkOptions, $bulkLookup] = $this->indexedOptions(
            [
                'create' => '📝 Bulk Create - Create multiple records',
                'update' => '✏️  Bulk Update - Update multiple records',
                'delete' => '🗑️  Bulk Delete - Delete multiple records',
            ],
            includeAll: true,
            allLabel: '✨ All Bulk Endpoints'
        );
        $selectedBulkIndexes = multiselect(
            label: '📦 Select bulk endpoints to generate',
            options: $bulkOptions,
            default: [],
            hint: 'Type number to jump, space to select, enter to confirm (optional)'
        );
        $selectedBulkEndpoints = $this->collectSelections($bulkLookup, $selectedBulkIndexes);

        // Handle "All" selection
        if (in_array('all', $selectedBulkEndpoints)) {
            $selectedBulkEndpoints = ['create', 'update', 'delete'];
        }

        // Step 7: Generation scope
        $onlySelected = confirm(
            label: 'Generate only selected options (skip core CRUD files)?',
            default: false,
            hint: 'Use this when you want tests-only or factory-only output'
        );

        // Step 8: Overwrite confirmation
        $overwrite = confirm(
            label: '🔄 Overwrite existing files without asking?',
            default: false,
            hint: 'If No, you will be prompted for each existing file'
        );

        // Step 9: Show summary and confirm
        $this->showSummary($selectedModels, $controllerTypes, $pattern, $usePagination, $selectedFeatures, $selectedDocs, $selectedBulkEndpoints, $overwrite, $controllerFolder, $onlySelected);

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
        $this->input->setOption('pest', in_array('pest', $selectedFeatures));
        $this->input->setOption('factory', in_array('factory', $selectedFeatures));
        $this->input->setOption('permissions-seeder', in_array('permissions-seeder', $selectedFeatures));
        $this->input->setOption('curl', in_array('curl', $selectedDocs));
        $this->input->setOption('postman', in_array('postman', $selectedDocs));
        $this->input->setOption('swagger-api', in_array('swagger-api', $selectedDocs));
        $this->input->setOption('bulk', $selectedBulkEndpoints);
        $this->input->setOption('overwrite', $overwrite);
        $this->input->setOption('only-selected', $onlySelected);
        if ($controllerFolder) {
            $this->input->setOption('controller-folder', $controllerFolder);
        }

        // Generate
        $this->generateForModels($selectedModels);
    }

    private function showSummary(array $models, array $types, string $pattern, string $pagination, array $features, array $docs, array $bulkEndpoints, bool $overwrite, ?string $controllerFolder = null, bool $onlySelected = false): void
    {
        note('📋 Generation Summary');

        info('Models: ' . implode(', ', $models));
        info('Controller Types: ' . implode(', ', $types));
        if ($controllerFolder) {
            info('Controller Folder: ' . $controllerFolder);
        }
        info('Generation Scope: ' . ($onlySelected ? 'Selected options only' : 'Full CRUD'));
        info('Pattern: ' . $pattern);
        info('Index Method: ' . ($pagination === 'pagination' ? 'Paginated' : 'Get All'));

        if (! empty($features)) {
            info('Features: ' . implode(', ', $features));
        }

        if (! empty($docs)) {
            info('Documentation: ' . implode(', ', $docs));
        }

        if (! empty($bulkEndpoints)) {
            info('Bulk Endpoints: ' . implode(', ', $bulkEndpoints));
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
            $table = ModelService::getTableName($modelData, fn($modelName) => new $modelName);

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

            $options = $this->options();
            // Normalize controller-folder path if provided
            if (!empty($options['controller-folder'])) {
                $options['controller-folder'] = $this->normalizeControllerFolderPath($options['controller-folder']);
            }

            $this->CRUDGenerator->generate($modelData, $options);
            $this->documentationGenerator->generate($modelData, $options, count($models) > 1);
        }

        info('✅ CRUD generation completed!');
    }

    /**
     * Normalize controller folder path.
     * If path doesn't start with Http/Controllers, prepend it.
     *
     * @param string $path
     * @return string
     */
    private function normalizeControllerFolderPath(string $path): string
    {
        $path = trim($path);
        // Remove leading/trailing slashes
        $path = trim($path, '/\\');
        
        if (!str_starts_with($path, 'Http/Controllers') && !str_starts_with($path, 'Http\\Controllers')) {
            $path = 'Http/Controllers/' . $path;
        }
        
        // Normalize to use forward slashes
        return str_replace('\\', '/', $path);
    }

    private function getExistingControllerFolders(): array
    {
        $controllersPath = base_path('app/Http/Controllers');
        if (!is_dir($controllersPath)) {
            return [];
        }

        $directories = File::allDirectories($controllersPath);
        $folders = [];
        foreach ($directories as $directory) {
            $relative = str_replace(base_path('app') . DIRECTORY_SEPARATOR, '', $directory);
            $relative = trim(str_replace('\\', '/', $relative), '/');
            if ($relative !== '') {
                $folders[] = $relative;
            }
        }

        sort($folders);

        return $folders;
    }

    private function hasAnyOptionProvided(): bool
    {
        return count($this->option('model')) > 0
            || count($this->option('type')) > 0
            || $this->option('service')
            || $this->option('filter')
            || $this->option('response-messages')
            || $this->option('policy')
            || $this->option('pest')
            || $this->option('factory')
            || $this->option('permissions-seeder')
            || $this->option('no-pagination')
            || $this->option('curl')
            || $this->option('postman')
            || $this->option('swagger-api')
            || $this->option('only-selected')
            || count($this->option('bulk')) > 0
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

    private function indexedOptions(array $options, bool $includeAll = false, string $allKey = 'all', ?string $allLabel = null): array
    {
        $indexedOptions = [];
        $lookup = [];
        $index = 1;

        if ($includeAll) {
            $indexedOptions[$index] = $allLabel ?? 'All';
            $lookup[$index] = $allKey;
            $index++;
        }

        foreach ($options as $key => $label) {
            if (is_int($key)) {
                $key = $label;
            }

            $indexedOptions[$index] = $label;
            $lookup[$index] = $key;
            $index++;
        }

        return [$indexedOptions, $lookup];
    }

    private function collectSelections(array $lookup, array $selectedIndexes): array
    {
        return array_values(array_intersect_key($lookup, array_flip($selectedIndexes)));
    }

    private function defaultIndexes(array $lookup, array $desired): array
    {
        $reverseLookup = array_flip($lookup);
        $defaults = [];

        foreach ($desired as $value) {
            if (isset($reverseLookup[$value])) {
                $defaults[] = $reverseLookup[$value];
            }
        }

        return $defaults;
    }

    private function forceAllBooleanOptions(): void
    {
        $this->input->setOption('service', true);
        $this->input->setOption('policy', true);
        $this->input->setOption('pest', true);
        $this->input->setOption('factory', true);
        $this->input->setOption('permissions-seeder', true);
        $this->input->setOption('curl', true);
        $this->input->setOption('postman', true);
        $this->input->setOption('swagger-api', true);
        $this->input->setOption('response-messages', true);
        $this->input->setOption('type', ['api', 'web']);
    }
}
