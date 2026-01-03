<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Fallback Models Path
    |--------------------------------------------------------------------------
    |
    | This value is the fallback path of your models, which will be used when you
    | don't use --model-path option.
    |
    */
    'fallback_models_path' => 'app/Models/',

    /*
    |--------------------------------------------------------------------------
    | Response Messages
    |--------------------------------------------------------------------------
    |
    | These values are the default response messages for CRUD operations.
    | You can override them here.
    |
    */
    'response_messages' => [
        'retrieved' => 'data retrieved successfully.',
        'created' => 'data created successfully.',
        'updated' => 'data updated successfully.',
        'deleted' => 'data deleted successfully.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Error Messages
    |--------------------------------------------------------------------------
    |
    | Standardized error messages for API error responses.
    |
    */
    'error_messages' => [
        'generic' => 'An error occurred while processing your request.',
        'store' => 'An error occurred while creating the resource.',
        'update' => 'An error occurred while updating the resource.',
        'delete' => 'An error occurred while deleting the resource.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Presets
    |--------------------------------------------------------------------------
    |
    | Configuration for saving and reusing common generation configurations.
    |
    */
    'presets_path' => storage_path('app/auto-crud-presets'),

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Cache settings for generated controllers when --cache flag is used.
    |
    */
    'cache' => [
        'ttl' => 3600, // Time to live in seconds (1 hour)
        'prefix' => 'laravel_auto_crud',
        'use_tags' => true, // Use cache tags (requires Redis or Memcached)
    ],

    /*
    |--------------------------------------------------------------------------
    | Permission Mappings
    |--------------------------------------------------------------------------
    |
    | Map policy actions to specific permission names.
    |
    */
    'permission_mappings' => [
        'view' => 'view',
        'create' => 'create',
        'update' => 'edit',
        'delete' => 'delete',
    ],

    /*
    |--------------------------------------------------------------------------
    | Test Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for generated Pest tests.
    |
    */
    'test_settings' => [
        /*
         * Seeder class to run in test beforeEach hook.
         * Set to null or empty string to skip seeder call.
         * The seeder will only be added to test files if the class exists.
         */
        'seeder_class' => 'Database\\Seeders\\RolesAndPermissionsSeeder',
        
        /*
         * Whether to include authorization tests in generated test files.
         */
        'include_authorization_tests' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Pagination
    |--------------------------------------------------------------------------
    |
    | Default number of items per page for paginated results.
    |
    */
    'default_pagination' => 20,

    /*
    |--------------------------------------------------------------------------
    | Custom Stub Path
    |--------------------------------------------------------------------------
    |
    | Custom path for stub files. If set, the package will look for stubs
    | in this directory first before falling back to package stubs.
    |
    */
    'custom_stub_path' => null,

    /*
    |--------------------------------------------------------------------------
    | File Permissions
    |--------------------------------------------------------------------------
    |
    | Default file permissions for generated files and directories.
    |
    */
    'file_permissions' => [
        'file' => 0644,
        'directory' => 0755,
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Controller Folders
    |--------------------------------------------------------------------------
    |
    | Default folder paths for generated controllers.
    | These are used when no custom folder is specified.
    |
    */
    'default_api_controller_folder' => 'Http/Controllers/API',
    'default_web_controller_folder' => 'Http/Controllers',

    /*
    |--------------------------------------------------------------------------
    | Permission Seeder Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for permission seeder generation.
    |
    */
    'permissions_seeder_path' => 'database/seeders/Permissions',

    /*
    |--------------------------------------------------------------------------
    | Permission Group Enum Settings
    |--------------------------------------------------------------------------
    |
    | Path to the PermissionGroup enum file.
    | This enum will be created/updated when generating permission seeders.
    |
    */
    'permission_group_enum_path' => 'app/Enums/PermissionGroup.php',

    /*
    |--------------------------------------------------------------------------
    | Authentication Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for API authentication middleware.
    |
    */
    'authentication' => [
        'driver' => 'sanctum', // sanctum, passport, or none
        'middleware' => 'auth:sanctum',
        'guard' => 'sanctum',
    ],
];
