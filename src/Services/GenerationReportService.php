<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Services;

use function Laravel\Prompts\info;
use function Laravel\Prompts\table;

class GenerationReportService
{
    private array $generatedFiles = [];
    private array $skippedFiles = [];
    private array $warnings = [];
    private array $nextSteps = [];

    /**
     * Add a generated file to the report.
     *
     * @param string $type Type of file (e.g., 'Controller', 'Request', 'Resource')
     * @param string $filePath Full file path
     * @param string $namespace Full namespace of the generated class
     * @return void
     */
    public function addGeneratedFile(string $type, string $filePath, string $namespace): void
    {
        $this->generatedFiles[] = [
            'type' => $type,
            'path' => $filePath,
            'namespace' => $namespace,
        ];
    }

    /**
     * Add a skipped file to the report.
     *
     * @param string $filePath File path that was skipped
     * @param string $reason Reason for skipping
     * @return void
     */
    public function addSkippedFile(string $filePath, string $reason): void
    {
        $this->skippedFiles[] = [
            'path' => $filePath,
            'reason' => $reason,
        ];
    }

    /**
     * Add a warning message.
     *
     * @param string $message Warning message
     * @return void
     */
    public function addWarning(string $message): void
    {
        $this->warnings[] = $message;
    }

    /**
     * Add a next step suggestion.
     *
     * @param string $step Next step description
     * @return void
     */
    public function addNextStep(string $step): void
    {
        $this->nextSteps[] = $step;
    }

    /**
     * Generate and display the report.
     *
     * @param string $modelName Model name that was processed
     * @param array<string, mixed> $options Generation options
     * @return void
     */
    public function displayReport(string $modelName, array $options = []): void
    {
        info("📊 Generation Report for {$modelName}");

        // Summary table
        $summary = [];
        $fileTypes = [];
        
        foreach ($this->generatedFiles as $file) {
            $type = $file['type'];
            if (!isset($fileTypes[$type])) {
                $fileTypes[$type] = 0;
            }
            $fileTypes[$type]++;
        }

        foreach ($fileTypes as $type => $count) {
            $summary[] = [$type, (string) $count];
        }

        if (!empty($summary)) {
            table(
                ['File Type', 'Count'],
                $summary
            );
        }

        // Generated files list
        if (!empty($this->generatedFiles)) {
            info("\n✅ Generated Files:");
            $filesTable = [];
            foreach ($this->generatedFiles as $file) {
                $filesTable[] = [
                    $file['type'],
                    str_replace(base_path(), '', $file['path']),
                ];
            }
            table(['Type', 'Path'], $filesTable);
        }

        // Skipped files
        if (!empty($this->skippedFiles)) {
            info("\n⏭️  Skipped Files:");
            foreach ($this->skippedFiles as $skipped) {
                info("  - {$skipped['path']}: {$skipped['reason']}");
            }
        }

        // Warnings
        if (!empty($this->warnings)) {
            info("\n⚠️  Warnings:");
            foreach ($this->warnings as $warning) {
                info("  - {$warning}");
            }
        }

        // Next steps
        $this->generateNextSteps($modelName, $options);
        if (!empty($this->nextSteps)) {
            info("\n📋 Next Steps:");
            foreach ($this->nextSteps as $index => $step) {
                info("  " . ($index + 1) . ". {$step}");
            }
        }
    }

    /**
     * Generate suggested next steps based on generated files and options.
     *
     * @param string $modelName Model name
     * @param array<string, mixed> $options Generation options
     * @return void
     */
    private function generateNextSteps(string $modelName, array $options): void
    {
        $hasController = $this->hasGeneratedFileType('Controller');
        $hasRequests = $this->hasGeneratedFileType('Request');
        $hasPolicy = $this->hasGeneratedFileType('Policy');
        $hasTests = $this->hasGeneratedFileType('Test');
        $hasFactory = $this->hasGeneratedFileType('Factory');
        $hasService = $this->hasGeneratedFileType('Service');

        if ($hasController && in_array('api', $options['type'] ?? [])) {
            $this->addNextStep("Test your API endpoints: php artisan serve");
            
            if (!($options['sanctum'] ?? false)) {
                $this->addNextStep("Consider adding authentication with --sanctum flag");
            }
        }

        if ($hasPolicy && !$hasTests) {
            $this->addNextStep("Generate tests with --pest flag to verify policy authorization");
        }

        if ($hasFactory && !$hasTests) {
            $this->addNextStep("Generate tests with --pest flag to use your factory");
        }

        if ($hasRequests) {
            $this->addNextStep("Review and customize validation rules in Request classes");
        }

        if ($hasService) {
            $this->addNextStep("Add business logic to your Service class");
        }

        if (in_array('api', $options['type'] ?? [])) {
            $this->addNextStep("Review generated routes in routes/api.php");
            
            if (!($options['swagger-api'] ?? false)) {
                $this->addNextStep("Generate API documentation with --swagger-api flag");
            }
        }

        if (!($options['response-messages'] ?? false) && $hasController) {
            $this->addNextStep("Consider using --response-messages for standardized API responses");
        }
    }

    /**
     * Check if a file type was generated.
     *
     * @param string $type File type to check
     * @return bool
     */
    private function hasGeneratedFileType(string $type): bool
    {
        foreach ($this->generatedFiles as $file) {
            if (str_contains($file['type'], $type)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Clear the report data.
     *
     * @return void
     */
    public function clear(): void
    {
        $this->generatedFiles = [];
        $this->skippedFiles = [];
        $this->warnings = [];
        $this->nextSteps = [];
    }

    /**
     * Get summary statistics.
     *
     * @return array<string, mixed>
     */
    public function getSummary(): array
    {
        return [
            'generated_count' => count($this->generatedFiles),
            'skipped_count' => count($this->skippedFiles),
            'warnings_count' => count($this->warnings),
            'file_types' => array_count_values(array_column($this->generatedFiles, 'type')),
        ];
    }
}

