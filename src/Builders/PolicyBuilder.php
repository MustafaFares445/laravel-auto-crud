<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Builders;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;

class PolicyBuilder extends BaseBuilder
{
    public function create(array $modelData, bool $overwrite = false): string
    {
        $this->ensurePermissionEnumsExist($overwrite);
        $this->ensureAuthorizesTraitExists($overwrite);
        $this->ensurePermissionResolverExists($overwrite);

        $modelName = $modelData['modelName'];
        $permissionGroupCase = Str::upper(Str::snake($modelName));
        $permissionGroupValue = Str::snake($modelName);

        $this->addPermissionGroupCase($permissionGroupCase, $permissionGroupValue);

        return $this->fileService->createFromStub($modelData, 'policy', 'Policies', 'Policy', $overwrite, function ($modelData) use ($permissionGroupCase) {
            $model = $this->getFullModelNamespace($modelData);
            $modelName = $modelData['modelName'];
            $modelVariable = lcfirst($modelName);

            return [
                '{{ modelNamespace }}' => $model,
                '{{ model }}' => $modelName,
                '{{ modelVariable }}' => $modelVariable,
                '{{ permissionGroupCase }}' => $permissionGroupCase,
            ];
        });
    }

    private function addPermissionGroupCase(string $caseName, string $caseValue): void
    {
        $enumPath = app_path('Enums/PermissionGroup.php');

        if (! file_exists($enumPath)) {
            return;
        }

        $content = File::get($enumPath);

        // Check if case already exists
        if (str_contains($content, "case {$caseName} =")) {
            return;
        }

        // Find the last case and add the new one after it
        $pattern = '/(case\s+\w+\s*=\s*[\'"][^\'"]+[\'"];)(\s*)(\/\/.*)?(\s*})/s';

        if (preg_match($pattern, $content)) {
            $newCase = "case {$caseName} = '{$caseValue}';";
            $content = preg_replace(
                $pattern,
                "$1\n    {$newCase}$2$3$4",
                $content
            );

            File::put($enumPath, $content);
            info("Added {$caseName} case to PermissionGroup enum");
        }
    }

    private function ensurePermissionEnumsExist(bool $overwrite = false): void
    {
        $this->ensureFileFromStub(
            app_path('Enums/PermissionType.php'),
            'permission_type.enum',
            $overwrite,
            'PermissionType enum'
        );

        $this->ensureFileFromStub(
            app_path('Enums/PermissionGroup.php'),
            'permission_group.enum',
            $overwrite,
            'PermissionGroup enum'
        );
    }

    private function ensureAuthorizesTraitExists(bool $overwrite = false): void
    {
        $this->ensureFileFromStub(
            app_path('Traits/AuthorizesByPermissionGroup.php'),
            'authorizes_by_permission_group.trait',
            $overwrite,
            'AuthorizesByPermissionGroup trait'
        );
    }

    private function ensurePermissionResolverExists(bool $overwrite = false): void
    {
        $this->ensureFileFromStub(
            app_path('Helpers/PermissionNameResolver.php'),
            'permission_name_resolver.helper',
            $overwrite,
            'PermissionNameResolver helper'
        );
    }

    private function ensureFileFromStub(string $filePath, string $stubName, bool $overwrite, string $fileDescription): void
    {
        if (file_exists($filePath) && ! $overwrite) {
            return;
        }

        if (file_exists($filePath)) {
            $shouldOverwrite = confirm(
                label: "{$fileDescription} already exists, do you want to overwrite it? {$filePath}"
            );
            if (! $shouldOverwrite) {
                return;
            }
        }

        File::ensureDirectoryExists(dirname($filePath), 0777, true);

        $projectStubPath = base_path("stubs/{$stubName}.stub");
        $vendorStubPath = base_path("vendor/mustafafares/laravel-auto-crud/src/Stubs/{$stubName}.stub");
        $stubPath = file_exists($projectStubPath) ? $projectStubPath : $vendorStubPath;

        File::copy($stubPath, $filePath);

        info("Created: {$filePath}");
    }
}
