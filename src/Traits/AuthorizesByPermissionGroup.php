<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Traits;

use Illuminate\Support\Str;
use Mrmarchone\LaravelAutoCrud\Helpers\PermissionNameResolver;

trait AuthorizesByPermissionGroup
{
    protected function authorizeAction($user, string $action): bool
    {
        $group = $this->resolvePermissionGroup();

        return $user->can(PermissionNameResolver::resolve($group, $action));
    }

    protected function resolvePermissionGroup(): string
    {
        // ProductPolicy -> Product -> products
        $className = class_basename(static::class);
        $modelName = str_replace('Policy', '', $className);

        return Str::plural(Str::snake($modelName));
    }
}
