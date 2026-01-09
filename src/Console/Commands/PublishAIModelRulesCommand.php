<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Mrmarchone\LaravelAutoCrud\Services\AIModelRulesGenerator;

use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\info;
use function Laravel\Prompts\warning;

class PublishAIModelRulesCommand extends Command
{
    protected $signature = 'auto-crud:publish-ai-rules
                            {--tool=* : AI tools to generate rules for (cursor, junie, github-copilot, codeium, tabnine, all)}
                            {--force : Overwrite existing files}';

    protected $description = 'Publish AI model rules documentation files for coding assistants';

    private const AVAILABLE_TOOLS = [
        'cursor' => [
            'name' => 'Cursor',
            'file' => '.cursorrules',
            'description' => 'Cursor AI coding assistant rules',
        ],
        'junie' => [
            'name' => 'Junie',
            'file' => 'junie-rules.md',
            'description' => 'Junie AI coding assistant rules',
        ],
        'github-copilot' => [
            'name' => 'GitHub Copilot',
            'file' => '.github/copilot-instructions.md',
            'description' => 'GitHub Copilot instructions',
        ],
        'codeium' => [
            'name' => 'Codeium',
            'file' => 'codeium-rules.md',
            'description' => 'Codeium AI coding assistant rules',
        ],
        'tabnine' => [
            'name' => 'Tabnine',
            'file' => 'tabnine-rules.md',
            'description' => 'Tabnine AI coding assistant rules',
        ],
        'generic' => [
            'name' => 'Generic Markdown',
            'file' => 'ai-coding-rules.md',
            'description' => 'Generic markdown format for any AI tool',
        ],
    ];

    public function __construct(
        private AIModelRulesGenerator $rulesGenerator,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Publishing AI Model Rules...');

        $selectedTools = $this->getSelectedTools();

        if (empty($selectedTools)) {
            warning('No tools selected. Exiting.');

            return Command::FAILURE;
        }

        $force = $this->option('force');
        $generated = 0;
        $skipped = 0;

        foreach ($selectedTools as $tool) {
            $toolConfig = self::AVAILABLE_TOOLS[$tool];
            $filePath = base_path($toolConfig['file']);

            if (file_exists($filePath) && ! $force) {
                warning("File already exists: {$toolConfig['file']}. Use --force to overwrite.");
                $skipped++;

                continue;
            }

            $directory = dirname($filePath);
            if ($directory !== '.' && $directory !== base_path()) {
                File::ensureDirectoryExists($directory, 0755, true);
            }

            $content = $this->rulesGenerator->generateForTool($tool);

            File::put($filePath, $content);

            info("Generated: {$toolConfig['file']}");
            $generated++;
        }

        $this->newLine();
        info("Successfully generated {$generated} file(s)" . ($skipped > 0 ? ", skipped {$skipped} file(s)" : '') . '.');

        return Command::SUCCESS;
    }

    private function getSelectedTools(): array
    {
        $tools = $this->option('tool');

        if (empty($tools)) {
            return $this->selectToolsInteractively();
        }

        if (in_array('all', $tools, true)) {
            return array_keys(self::AVAILABLE_TOOLS);
        }

        $validTools = [];
        foreach ($tools as $tool) {
            if (isset(self::AVAILABLE_TOOLS[$tool])) {
                $validTools[] = $tool;
            } else {
                warning("Unknown tool: {$tool}. Skipping.");
            }
        }

        return $validTools;
    }

    private function selectToolsInteractively(): array
    {
        $options = [];
        foreach (self::AVAILABLE_TOOLS as $key => $config) {
            $options[$key] = "{$config['name']} - {$config['description']}";
        }

        $selected = multiselect(
            label: 'Select AI tools to generate rules for',
            options: $options,
            default: array_keys(self::AVAILABLE_TOOLS),
        );

        return $selected;
    }
}
