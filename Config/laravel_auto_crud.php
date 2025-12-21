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
        'seeder_class' => 'Database\\Seeders\\RolesAndPermissionsSeeder',
        'include_authorization_tests' => true,
    ],
];
