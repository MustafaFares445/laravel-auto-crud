<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Console\Commands;

use Illuminate\Console\Command;
use Mrmarchone\LaravelAutoCrud\Services\BulkEndpointsGenerator;
use Mrmarchone\LaravelAutoCrud\Services\DatabaseValidatorService;
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

class GenerateBulkEndpointsCommand extends Command
{
    protected $signature = 'auto-crud:generate-bulk
    {--M|model=* : Specify model name(s) to generate bulk endpoints for (e.g., --model=User --model=Post)}
    {--MP|model-path= : Custom path to models directory (default: app/Models/)}
    {--T|type=* : Output type: "api", "web", or both (e.g., --type=api --type=web)}
    {--B|bulk=* : Bulk endpoints to generate (e.g., --bulk=create --bulk=update --bulk=delete or --bulk=all)}
    {--P|pattern=normal : Data pattern: "normal" or "spatie-data" for Spatie Laravel Data}
    {--RM|response-messages : Add ResponseMessages enum for standardized API responses}
    {--CF|controller-folder= : Custom folder path for controllers (e.g., Http/Controllers/Admin or Http/Controllers/API/V1)}
    {--O|overwrite : Overwrite existing files without confirmation}';

    protected $description = 'Generate bulk endpoints (store, update, destroy) with dedicated controller and request classes';

    public function __construct(
        protected DatabaseValidatorService $databaseValidatorService,
        protected BulkEndpointsGenerator $bulkEndpointsGenerator,
    ) {
        parent::__construct();
    }

    public function handle(): void
    {
        HelperService::displaySignature();

        if (! $this->everythingIsOk()) {
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
            label: '📦 Select the model(s) to generate bulk endpoints for',
            options: $modelOptions,
            required: true,
            hint: 'Type number(s) or use space to select, enter to confirm'
        );
        $selectedModels = $this->collectSelections($modelLookup, $selectedModelIndexes);

        if (in_array('all', $selectedModels)) {
            $selectedModels = $availableModels;
        }

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

        if (in_array('all', $controllerTypes)) {
            $controllerTypes = ['api', 'web'];
        }

        $controllerFolder = $this->option('controller-folder');
        if (!$controllerFolder) {
            $defaultApiFolder = config('laravel_auto_crud.default_api_controller_folder', 'Http/Controllers/API');
            $defaultWebFolder = config('laravel_auto_crud.default_web_controller_folder', 'Http/Controllers');

            $folderOptions = [
                'default' => 'Use default folders (API: ' . $defaultApiFolder . ', Web: ' . $defaultWebFolder . ')',
                'custom' => 'Enter custom folder path',
            ];

            [$folderPromptOptions, $folderLookup] = $this->indexedOptions($folderOptions);
            $selectedFolderIndex = select(
                label: '📁 Where should controllers be placed?',
                options: $folderPromptOptions,
                default: 1,
                hint: 'Type number to select, enter to confirm'
            );

            if ($folderLookup[$selectedFolderIndex] === 'custom') {
                $controllerFolder = text(
                    label: 'Enter controller folder path',
                    placeholder: 'e.g., Admin or Http/Controllers/Admin',
                    default: '',
                    required: true,
                    validate: fn ($value) => empty(trim($value)) ? 'Folder path cannot be empty' : null
                );

                $controllerFolder = $this->normalizeControllerFolderPath($controllerFolder);
            }
        }

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
            required: true,
            hint: 'Type number to jump, space to select, enter to confirm'
        );
        $selectedBulkEndpoints = $this->collectSelections($bulkLookup, $selectedBulkIndexes);

        if (in_array('all', $selectedBulkEndpoints)) {
            $selectedBulkEndpoints = ['create', 'update', 'delete'];
        }

        $useResponseMessages = false;
        if (in_array('api', $controllerTypes)) {
            $useResponseMessages = confirm(
                label: '💬 Use ResponseMessages enum for standardized API responses?',
                default: false,
                hint: 'Adds standardized response messages to API responses'
            );
        }

        $overwrite = confirm(
            label: '🔄 Overwrite existing files without asking?',
            default: false,
            hint: 'If No, you will be prompted for each existing file'
        );

        $this->showSummary($selectedModels, $controllerTypes, $pattern, $selectedBulkEndpoints, $useResponseMessages, $overwrite, $controllerFolder);

        if (! confirm(label: '🚀 Proceed with generation?', default: true)) {
            warning('Generation cancelled.');

            return;
        }

        $this->input->setOption('model', $selectedModels);
        $this->input->setOption('type', $controllerTypes);
        $this->input->setOption('pattern', $pattern);
        $this->input->setOption('bulk', $selectedBulkEndpoints);
        $this->input->setOption('response-messages', $useResponseMessages);
        $this->input->setOption('overwrite', $overwrite);
        if ($controllerFolder) {
            $this->input->setOption('controller-folder', $controllerFolder);
        }

        $this->generateForModels($selectedModels);
    }

    private function showSummary(array $models, array $types, string $pattern, array $bulkEndpoints, bool $useResponseMessages, bool $overwrite, ?string $controllerFolder = null): void
    {
        note('📋 Generation Summary');

        info('Models: ' . implode(', ', $models));
        info('Controller Types: ' . implode(', ', $types));
        if ($controllerFolder) {
            info('Controller Folder: ' . $controllerFolder);
        }
        info('Pattern: ' . $pattern);
        info('Bulk Endpoints: ' . implode(', ', $bulkEndpoints));
        if ($useResponseMessages) {
            info('Response Messages: Enabled');
        }
        info('Overwrite: ' . ($overwrite ? 'Yes' : 'No'));
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
                    label: "⚠️  Table '{$table}' not found. Create empty bulk endpoint files anyway?",
                    default: false
                );

                if (! $createFiles) {
                    warning("Skipped: {$model}");

                    continue;
                }
            }

            $options = $this->options();
            if (!empty($options['controller-folder'])) {
                $options['controller-folder'] = $this->normalizeControllerFolderPath($options['controller-folder']);
            }

            $bulkEndpoints = $options['bulk'] ?? [];
            if (empty($bulkEndpoints)) {
                warning("No bulk endpoints selected for {$model}. Skipping.");

                continue;
            }

            $this->bulkEndpointsGenerator->generate($modelData, $options);
        }

        info('✅ Bulk endpoints generation completed!');
    }

    private function normalizeControllerFolderPath(string $path): string
    {
        $path = trim($path);
        $path = trim($path, '/\\');

        if (!str_starts_with($path, 'Http/Controllers') && !str_starts_with($path, 'Http\\Controllers')) {
            $path = 'Http/Controllers/' . $path;
        }

        return str_replace('\\', '/', $path);
    }

    private function hasAnyOptionProvided(): bool
    {
        return count($this->option('model')) > 0
            || count($this->option('type')) > 0
            || count($this->option('bulk')) > 0
            || $this->option('response-messages')
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
}
