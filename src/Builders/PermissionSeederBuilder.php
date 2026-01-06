<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Builders;

use Illuminate\Support\Str;
use Mrmarchone\LaravelAutoCrud\Services\FileService;
use Mrmarchone\LaravelAutoCrud\Traits\ModelHelperTrait;

class PermissionSeederBuilder
{
    use ModelHelperTrait;

    protected FileService $fileService;

    public function __construct(FileService $fileService)
    {
        $this->fileService = $fileService;
    }
    public function create(array $modelData, bool $overwrite = false): string
    {
        $modelName = $modelData['modelName'];
        $seederPath = config('laravel_auto_crud.permissions_seeder_path', 'database/seeders/Permissions');
        
        // Generate file path manually for seeders (they use Database\Seeders namespace, not App\)
        $filePath = $this->generateSeederFilePath($modelData, $seederPath);
        $namespace = $this->generateSeederNamespace($seederPath);
        
        // Check if file exists
        if (! $overwrite && file_exists($filePath)) {
            $overwrite = \Laravel\Prompts\confirm(
                label: 'Permission seeder file already exists, do you want to overwrite it? '.$filePath
            );
            if (! $overwrite) {
                return $namespace.'\\'.$modelName.'PermissionsSeeder';
            }
        }
        
        \Illuminate\Support\Facades\File::ensureDirectoryExists(dirname($filePath), 0777, true);
        
        $stubPath = $this->getStubPath('permission_seeder');
        $content = $this->generateSeederContent($stubPath, $modelData, $namespace, $modelName);
        
        \Illuminate\Support\Facades\File::put($filePath, $content);
        \Laravel\Prompts\info("Created: $filePath");
        
        return $namespace.'\\'.$modelName.'PermissionsSeeder';
    }
    
    private function generateSeederFilePath(array $modelData, string $basePath): string
    {
        $fileName = $modelData['modelName'].'PermissionsSeeder.php';
        $path = base_path($basePath);
        
        return rtrim($path, '/\\').DIRECTORY_SEPARATOR.$fileName;
    }
    
    private function generateSeederNamespace(string $basePath): string
    {
        // Convert database/seeders/Permissions to Database\Seeders\Permissions
        $parts = explode('/', $basePath);
        $namespaceParts = array_map(function($part) {
            return ucfirst(strtolower($part));
        }, $parts);
        
        return implode('\\', $namespaceParts);
    }
    
    private function getStubPath(string $stubType): string
    {
        $customStubPath = config('laravel_auto_crud.custom_stub_path');
        $projectStubPath = $customStubPath 
            ? rtrim($customStubPath, '/') . "/{$stubType}.stub"
            : base_path("stubs/{$stubType}.stub");
        $packageStubPath = __DIR__ . "/../Stubs/{$stubType}.stub";
        
        $stubPath = file_exists($projectStubPath) ? $projectStubPath : $packageStubPath;
        
        if (!file_exists($stubPath)) {
            throw new \RuntimeException("Stub file not found: {$stubType}.stub. Checked: {$projectStubPath} and {$packageStubPath}");
        }
        
        return $stubPath;
    }
    
    private function generateSeederContent(string $stubPath, array $modelData, string $namespace, string $modelName): string
    {
        $stubContent = file_get_contents($stubPath);
        if ($stubContent === false) {
            throw new \RuntimeException("Cannot read stub file: {$stubPath}");
        }
        
        $groupName = Str::plural(Str::snake($modelName));
        
        $replacements = [
            '{{ namespace }}' => $namespace,
            '{{ class }}' => $modelName.'PermissionsSeeder',
            '{{ groupName }}' => $groupName,
        ];
        
        return str_replace(array_keys($replacements), array_values($replacements), $stubContent);
    }
}

