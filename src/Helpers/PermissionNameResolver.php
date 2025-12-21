<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Helpers;

class PermissionNameResolver
{
    public static function resolve(string $group, string $action): string
    {
        $mappings = config('laravel_auto_crud.permission_mappings', [
            'view' => 'view',
            'create' => 'create',
            'update' => 'edit',
            'delete' => 'delete',
        ]);

        $actionName = $mappings[$action] ?? str_replace('_', ' ', $action);

        return $actionName . ' ' . $group;
    }
}
