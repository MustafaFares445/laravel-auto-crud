<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Builders;

use Illuminate\Support\Str;
use Mrmarchone\LaravelAutoCrud\Services\FileService;

class PermissionSeederBuilder extends BaseBuilder
{
    public function create(array $modelData, bool $overwrite = false): string
    {
        $modelName = $modelData['modelName'];
        $seederName = $modelName . 'PermissionsSeeder';
        $seederPath = config('laravel_auto_crud.permissions_seeder_path', 'Database/Seeders/Permissions');
        
        return $this->fileService->createFromStub($modelData, 'permission_seeder', $seederPath, $seederName, $overwrite, function ($modelData) use ($modelName) {
            $groupName = Str::plural(Str::snake($modelName));
            
            return [
                '{{ class }}' => $modelName . 'PermissionsSeeder',
                '{{ groupName }}' => $groupName,
            ];
        });
    }
}

