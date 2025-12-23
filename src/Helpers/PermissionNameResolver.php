<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Helpers;

/**
 * Helper class for resolving permission names based on configurable mappings.
 *
 * Used in generated Policy classes to create consistent permission names
 * following the pattern: "{action} {group}" (e.g., "create users", "edit posts").
 */
class PermissionNameResolver
{
    /**
     * Resolve permission name from group and action using configurable mappings.
     *
     * Uses permission mappings from config/laravel_auto_crud.php to translate
     * action names. If no mapping exists, converts underscores to spaces.
     * Returns permission name in format: "{action} {group}".
     *
     * @param string $group The permission group (e.g., 'users', 'posts', 'products')
     * @param string $action The action name (e.g., 'view', 'create', 'update', 'delete')
     * @return string The resolved permission name (e.g., "create users", "edit posts")
     *
     * @example
     * // Using default mappings
     * PermissionNameResolver::resolve('users', 'create');
     * // Returns: "create users"
     *
     * // Using custom mapping (update -> edit)
     * PermissionNameResolver::resolve('posts', 'update');
     * // Returns: "edit posts" (based on config mapping)
     *
     * // Action without mapping
     * PermissionNameResolver::resolve('products', 'publish');
     * // Returns: "publish products" (underscores converted to spaces)
     */
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
