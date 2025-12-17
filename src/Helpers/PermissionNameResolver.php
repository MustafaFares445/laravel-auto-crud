<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Helpers;

class PermissionNameResolver
{
    public static function resolve(string $group, string $action): string
    {
        $actionName = match ($action) {
            'view' => 'view',
            'create' => 'create',
            'update' => 'edit',
            'delete' => 'delete',
            default => str_replace('_', ' ', $action),
        };

        return $actionName . ' ' . $group;
    }
}
