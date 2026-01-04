<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

class GenerationReportService
{
    /**
     * Generate a detailed report after CRUD generation.
     *
     * @param array<string, mixed> $modelData Model information
     * @param array<string, mixed> $options Generation options
     * @param array<string, string> $generatedFiles Array of generated file paths
     * @return array<string, mixed> Report data
     */
    public function generateReport(array $modelData, array $options, array $generatedFiles = []): array
    {
        $report = [
            'model' => $modelData['modelName'],
            'namespace' => $modelData['namespace'] ?? 'App\\Models',
            'generated_at' => now()->toIso8601String(),
            'files' => $this->getFileSummary($generatedFiles),
            'routes' => $this->getRoutesSummary($modelData, $options),
            'features' => $this->getFeaturesSummary($options),
            'next_steps' => $this->getNextSteps($modelData, $options),
        ];

        if (config('laravel_auto_crud.documentation.scramble.enabled', false)) {
            $report['scramble_url'] = $this->getScrambleUrl();
        }

        return $report;
    }

    /**
     * Display the report in the console.
     *
     * @param array<string, mixed> $report Report data
     * @return void
     */
    public function displayReport(array $report): void
    {
        echo "\n";
        echo "═══════════════════════════════════════════════════════════════\n";
        echo "  📊 Generation Report: {$report['model']}\n";
        echo "═══════════════════════════════════════════════════════════════\n\n";

        echo "📁 Generated Files:\n";
        foreach ($report['files'] as $file) {
            echo "   ✓ {$file}\n";
        }

        echo "\n🛣️  Routes Created:\n";
        foreach ($report['routes'] as $route) {
            echo "   {$route['method']} {$route['uri']} → {$route['action']}\n";
        }

        echo "\n⚡ Features Enabled:\n";
        foreach ($report['features'] as $feature) {
            echo "   • {$feature}\n";
        }

        if (isset($report['scramble_url'])) {
            echo "\n📖 API Documentation:\n";
            echo "   {$report['scramble_url']}\n";
        }

        echo "\n📋 Next Steps:\n";
        foreach ($report['next_steps'] as $step) {
            echo "   {$step}\n";
        }

        echo "\n═══════════════════════════════════════════════════════════════\n\n";
    }

    /**
     * Save report to file.
     *
     * @param array<string, mixed> $report Report data
     * @param string $format Report format (json or markdown)
     * @param string|null $path Custom path for report file
     * @return string Path to saved report file
     */
    public function saveReport(array $report, string $format = 'json', ?string $path = null): string
    {
        $modelName = $report['model'];
        $timestamp = now()->format('Y-m-d_His');
        $filename = "{$modelName}_generation_report_{$timestamp}";

        if ($path === null) {
            $path = storage_path("app/auto-crud-reports");
        }

        File::ensureDirectoryExists($path);

        $filePath = "{$path}/{$filename}.{$format}";

        if ($format === 'json') {
            File::put($filePath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            File::put($filePath, $this->formatMarkdownReport($report));
        }

        return $filePath;
    }

    /**
     * Get summary of generated files.
     *
     * @param array<string, string> $generatedFiles Generated file paths
     * @return array<int, string> Array of file paths
     */
    private function getFileSummary(array $generatedFiles): array
    {
        return array_values($generatedFiles);
    }

    /**
     * Get summary of created routes.
     *
     * @param array<string, mixed> $modelData Model information
     * @param array<string, mixed> $options Generation options
     * @return array<int, array<string, string>> Array of route information
     */
    private function getRoutesSummary(array $modelData, array $options): array
    {
        $routes = [];
        $modelName = strtolower($modelData['modelName']);
        $pluralModel = str_plural($modelName);
        $controllerTypes = $options['type'] ?? ['api'];

        foreach ($controllerTypes as $type) {
            if ($type === 'api') {
                $prefix = config('laravel_auto_crud.api_prefix', 'api');
                $routes[] = ['method' => 'GET', 'uri' => "/{$prefix}/{$pluralModel}", 'action' => 'index'];
                $routes[] = ['method' => 'POST', 'uri' => "/{$prefix}/{$pluralModel}", 'action' => 'store'];
                $routes[] = ['method' => 'GET', 'uri' => "/{$prefix}/{$pluralModel}/{id}", 'action' => 'show'];
                $routes[] = ['method' => 'PUT', 'uri' => "/{$prefix}/{$pluralModel}/{id}", 'action' => 'update'];
                $routes[] = ['method' => 'DELETE', 'uri' => "/{$prefix}/{$pluralModel}/{id}", 'action' => 'destroy'];

                if ($options['hasSoftDeletes'] ?? false) {
                    $routes[] = ['method' => 'POST', 'uri' => "/{$prefix}/{$pluralModel}/{id}/restore", 'action' => 'restore'];
                    $routes[] = ['method' => 'DELETE', 'uri' => "/{$prefix}/{$pluralModel}/{id}/force", 'action' => 'forceDelete'];
                }
            } elseif ($type === 'web') {
                $routes[] = ['method' => 'GET', 'uri' => "/{$pluralModel}", 'action' => 'index'];
                $routes[] = ['method' => 'GET', 'uri' => "/{$pluralModel}/create", 'action' => 'create'];
                $routes[] = ['method' => 'POST', 'uri' => "/{$pluralModel}", 'action' => 'store'];
                $routes[] = ['method' => 'GET', 'uri' => "/{$pluralModel}/{id}", 'action' => 'show'];
                $routes[] = ['method' => 'GET', 'uri' => "/{$pluralModel}/{id}/edit", 'action' => 'edit'];
                $routes[] = ['method' => 'PUT', 'uri' => "/{$pluralModel}/{id}", 'action' => 'update'];
                $routes[] = ['method' => 'DELETE', 'uri' => "/{$pluralModel}/{id}", 'action' => 'destroy'];
            }
        }

        return $routes;
    }

    /**
     * Get summary of enabled features.
     *
     * @param array<string, mixed> $options Generation options
     * @return array<int, string> Array of feature names
     */
    private function getFeaturesSummary(array $options): array
    {
        $features = [];

        if ($options['service'] ?? false) {
            $features[] = 'Service Layer';
        }

        if ($options['policy'] ?? false) {
            $features[] = 'Policy Authorization';
        }

        if ($options['filter'] ?? false) {
            $features[] = 'Spatie Query Builder Filters';
        }

        if ($options['factory'] ?? false) {
            $features[] = 'Model Factory';
        }

        if ($options['pest'] ?? false) {
            $features[] = 'Pest Tests';
        }

        if ($options['response-messages'] ?? false) {
            $features[] = 'Response Messages';
        }

        if ($options['permissions-seeder'] ?? false) {
            $features[] = 'Permission Seeder';
        }

        if (($options['hasSoftDeletes'] ?? false)) {
            $features[] = 'Soft Deletes';
        }

        return $features;
    }

    /**
     * Get next steps for the developer.
     *
     * @param array<string, mixed> $modelData Model information
     * @param array<string, mixed> $options Generation options
     * @return array<int, string> Array of next step instructions
     */
    private function getNextSteps(array $modelData, array $options): array
    {
        $steps = [];

        $steps[] = "✓ Review generated files in app/Http/Controllers/";
        $steps[] = "✓ Test the generated endpoints";

        if ($options['policy'] ?? false) {
            $steps[] = "✓ Configure permissions in your database seeder";
        }

        if ($options['filter'] ?? false) {
            $steps[] = "✓ Test filter endpoints with query parameters";
        }

        if ($options['pest'] ?? false) {
            $steps[] = "✓ Run tests: php artisan test";
        }

        if (config('laravel_auto_crud.documentation.scramble.enabled', false)) {
            $steps[] = "✓ View API documentation at: {$this->getScrambleUrl()}";
        }

        return $steps;
    }

    /**
     * Get Scramble documentation URL.
     *
     * @return string Scramble URL
     */
    private function getScrambleUrl(): string
    {
        $baseUrl = config('app.url', 'http://localhost');
        $scramblePath = config('scramble.ui_path', '/api/documentation');

        return rtrim($baseUrl, '/') . $scramblePath;
    }

    /**
     * Format report as Markdown.
     *
     * @param array<string, mixed> $report Report data
     * @return string Markdown formatted report
     */
    private function formatMarkdownReport(array $report): string
    {
        $markdown = "# Generation Report: {$report['model']}\n\n";
        $markdown .= "**Generated at:** {$report['generated_at']}\n\n";

        $markdown .= "## Generated Files\n\n";
        foreach ($report['files'] as $file) {
            $markdown .= "- `{$file}`\n";
        }

        $markdown .= "\n## Routes Created\n\n";
        foreach ($report['routes'] as $route) {
            $markdown .= "- `{$route['method']}` {$route['uri']} → `{$route['action']}`\n";
        }

        $markdown .= "\n## Features Enabled\n\n";
        foreach ($report['features'] as $feature) {
            $markdown .= "- {$feature}\n";
        }

        if (isset($report['scramble_url'])) {
            $markdown .= "\n## API Documentation\n\n";
            $markdown .= "[View Documentation]({$report['scramble_url']})\n\n";
        }

        $markdown .= "\n## Next Steps\n\n";
        foreach ($report['next_steps'] as $step) {
            $markdown .= "1. {$step}\n";
        }

        return $markdown;
    }
}

