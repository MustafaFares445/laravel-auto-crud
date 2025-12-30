<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Console\Commands;

use Illuminate\Console\Command;
use Mrmarchone\LaravelAutoCrud\Builders\PestBuilder;
use Mrmarchone\LaravelAutoCrud\Services\ModelService;

use function Laravel\Prompts\alert;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\info;

class GenerateTestsCommand extends Command
{
    protected $signature = 'auto-crud:generate-tests
    {--M|model=* : Specify model name(s) to generate tests for}
    {--MP|model-path= : Custom path to models directory}
    {--O|overwrite : Overwrite existing test files}';

    protected $description = 'Generate Pest tests (Feature) for existing models';

    public function __construct(protected PestBuilder $pestBuilder)
    {
        parent::__construct();
    }

    public function handle(): void
    {
        $modelPath = $this->option('model-path') ?? config('laravel_auto_crud.fallback_models_path', 'app/Models/');
        $availableModels = ModelService::getAvailableModels($modelPath);

        if (empty($availableModels)) {
            alert("No models found in {$modelPath}.");
            return;
        }

        $selectedModels = $this->option('model') ?: multiselect(
            label: '🧪 Select the model(s) to generate tests for',
            options: $availableModels,
            required: true,
            hint: 'Use space to select, enter to confirm'
        );

        foreach ($selectedModels as $model) {
            $modelData = ModelService::resolveModelName($model);
            
            // Check if policy exists for this model
            $policyExists = file_exists(app_path("Policies/{$modelData['modelName']}Policy.php"));

            $this->pestBuilder->createFeatureTest($modelData, $this->option('overwrite'), $policyExists);
            
            // Check if filter trait exists and generate filter tests
            $traitPath = app_path("Traits/FilterQueries/{$modelData['modelName']}FilterQuery.php");
            if (file_exists($traitPath)) {
                $this->pestBuilder->createFilterTest($modelData, $this->option('overwrite'));
            }
        }

        info('✅ Test generation completed!');
    }
}
