<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Builders;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Mrmarchone\LaravelAutoCrud\Services\FileService;
use Mrmarchone\LaravelAutoCrud\Traits\ModelHelperTrait;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\warning;

class PermissionGroupEnumBuilder
{
    use ModelHelperTrait;

    protected FileService $fileService;

    public function __construct(FileService $fileService)
    {
        $this->fileService = $fileService;
    }
    public function updateEnum(array $modelData, bool $overwrite = false): string
    {
        $enumPath = config('laravel_auto_crud.permission_group_enum_path', 'app/Enums/PermissionGroup.php');
        $fullPath = base_path($enumPath);
        
        $modelName = $modelData['modelName'];
        $groupName = Str::plural(Str::snake($modelName));
        $enumCaseName = Str::upper(Str::snake($modelName));
        
        $existingCases = [];
        $enumContent = '';
        
        // Read existing enum if it exists
        if (file_exists($fullPath)) {
            $enumContent = File::get($fullPath);
            $existingCases = $this->extractExistingCases($enumContent);
            
            // Safety check: if file exists but we couldn't extract any cases, warn the user
            if (empty($existingCases) && !empty(trim($enumContent))) {
                warning("Warning: Could not extract existing enum cases from {$fullPath}. Existing cases may be lost. Please check the file format.");
            }
            
            // Check if case already exists
            if (in_array($enumCaseName, array_keys($existingCases))) {
                info("PermissionGroup enum case '{$enumCaseName}' already exists. Skipping update.");
                return 'App\\Enums\\PermissionGroup';
            }
        }
        
        // Add new case
        $existingCases[$enumCaseName] = $groupName;
        
        // Generate enum cases
        $enumCases = $this->generateEnumCases($existingCases);
        
        // Create or update enum file
        File::ensureDirectoryExists(dirname($fullPath), 0777, true);
        
        $stubPath = base_path('stubs/permission_group_enum.stub');
        if (!file_exists($stubPath)) {
            $stubPath = __DIR__ . '/../Stubs/permission_group_enum.stub';
        }
        
        $content = file_get_contents($stubPath);
        $content = str_replace('{{ enumCases }}', $enumCases, $content);
        
        if (file_exists($fullPath) && !$overwrite) {
            $shouldOverwrite = confirm(
                label: 'PermissionGroup enum already exists, do you want to update it? ' . $fullPath
            );
            if (!$shouldOverwrite) {
                return 'App\\Enums\\PermissionGroup';
            }
        }
        
        File::put($fullPath, $content);
        info("Created/Updated: {$fullPath}");
        
        return 'App\\Enums\\PermissionGroup';
    }
    
    /**
     * Extract existing enum cases from enum file content.
     *
     * @param string $content
     * @return array Array of [CASE_NAME => 'value']
     */
    private function extractExistingCases(string $content): array
    {
        $cases = [];
        
        // Match pattern: case CASE_NAME = 'value'; (more robust regex)
        // Handles: single quotes, double quotes, various whitespace, multi-line cases
        if (preg_match_all("/case\s+([A-Z_][A-Z0-9_]*)\s*=\s*['\"]([^'\"]+)['\"]\s*;/m", $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $caseName = trim($match[1]);
                $caseValue = trim($match[2]);
                if (!empty($caseName) && !empty($caseValue)) {
                    $cases[$caseName] = $caseValue;
                }
            }
        }
        
        return $cases;
    }
    
    /**
     * Generate enum cases string from array.
     *
     * @param array $cases Array of [CASE_NAME => 'value']
     * @return string
     */
    private function generateEnumCases(array $cases): string
    {
        $enumCases = [];
        
        // Sort cases alphabetically for consistency
        ksort($cases);
        
        foreach ($cases as $caseName => $value) {
            $enumCases[] = "    case {$caseName} = '{$value}';";
        }
        
        return implode("\n", $enumCases);
    }
}

