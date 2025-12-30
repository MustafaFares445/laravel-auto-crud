# Laravel Auto CRUD

A powerful Laravel package that automatically generates complete CRUD scaffolding including controllers, requests, resources, services, policies, factories, routes, views, and Pest tests for your Eloquent models.

## Table of Contents

- [Installation](#installation)
- [Configuration](#configuration)
- [Basic Usage](#basic-usage)
- [Commands](#commands)
- [Available Options](#available-options)
- [Features](#features)
- [Generated Files](#generated-files)
- [Examples](#examples)
- [License](#license)

## Installation

Install the package via Composer:

```bash
composer require mustafafares/laravel-auto-crud
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag=auto-crud-config
```

This will create a `config/laravel_auto_crud.php` file where you can customize package settings.

Publish ResponseMessages translation files:

```bash
php artisan auto-crud:publish-translations
```

This will publish translation files to `lang/vendor/laravel-auto-crud/` where you can customize the response messages for different languages.

## Configuration

After publishing the config file, you can customize the following settings in `config/laravel_auto_crud.php`:

### Default Configuration

```php
return [
    // Fallback path for models (used when --model-path is not specified)
    'fallback_models_path' => 'app/Models/',

    // Response messages for API endpoints
    'response_messages' => [
        'retrieved' => 'data retrieved successfully.',
        'created' => 'data created successfully.',
        'updated' => 'data updated successfully.',
        'deleted' => 'data deleted successfully.',
    ],

    // Permission mappings for policy authorization
    'permission_mappings' => [
        'view' => 'view',
        'create' => 'create',
        'update' => 'edit',
        'delete' => 'delete',
    ],

    // Test generation settings
    'test_settings' => [
        'seeder_class' => 'Database\\Seeders\\RolesAndPermissionsSeeder',
        'include_authorization_tests' => true,
    ],

    // Default pagination per page
    'default_pagination' => 20,

    // Custom stub path (optional)
    'custom_stub_path' => null,

    // Default controller folders
    'default_api_controller_folder' => 'Http/Controllers/API',
    'default_web_controller_folder' => 'Http/Controllers',
];
```

## Basic Usage

### Interactive Mode

Run the command without options to enter interactive mode:

```bash
php artisan auto-crud:generate
```

This will guide you through:
- Model selection (Searchable)
- Controller type selection (API/Web)
- Controller folder selection (Default or custom path)
- Data pattern selection (Normal/Spatie Data)
- Index method style (Pagination or Get All)
- Feature selection (Service, Policy, Factory, Tests, etc. - Searchable)
- Documentation generation options

**Note**: Most prompts are now searchable. Just start typing to filter options!

### Command Line Mode

Generate CRUD for a specific model:

```bash
php artisan auto-crud:generate --model=User
```

Generate for multiple models:

```bash
php artisan auto-crud:generate --model=User --model=Post --model=Category
```

## Commands

### `auto-crud:generate`

Main command for generating CRUD scaffolding.

**Signature:**
```bash
php artisan auto-crud:generate
    {--A|all : Generate all files without overwriting existing files}
    {--FA|force-all : Generate all files and overwrite existing files}
    {--M|model=* : Specify model name(s) to generate CRUD for}
    {--MP|model-path= : Custom path to models directory}
    {--T|type=* : Output type: "api", "web", or both}
    {--S|service : Include a Service class for business logic}
    {--F|filter : Include Spatie Query Builder filter support (requires --pattern=spatie-data)}
    {--O|overwrite : Overwrite existing files without confirmation}
    {--P|pattern=normal : Data pattern: "normal" or "spatie-data"}
    {--RM|response-messages : Add ResponseMessages enum for standardized API responses}
    {--NP|no-pagination : Use Model::all() instead of pagination in index method}
    {--PO|policy : Generate Policy class with permission-based authorization}
    {--PS|permissions-seeder : Generate permission seeder and update PermissionGroup enum}
    {--FC|factory : Generate Model Factory}
    {--C|curl : Generate cURL command examples for API endpoints}
    {--PM|postman : Generate Postman collection JSON file}
    {--SA|swagger-api : Generate Swagger/OpenAPI specification}
    {--PT|pest : Generate Pest test files (Feature)}
```

### `auto-crud:generate-tests`

Generate Pest tests for existing models without generating CRUD files.

**Signature:**
```bash
php artisan auto-crud:generate-tests
    {--M|model=* : Specify model name(s) to generate tests for}
    {--MP|model-path= : Custom path to models directory}
    {--O|overwrite : Overwrite existing test files}
```

### `auto-crud:publish-translations`

Publish ResponseMessages translation files to your application for customization.

**Signature:**
```bash
php artisan auto-crud:publish-translations
    {--force : Overwrite existing translation files}
```

This command publishes translation files to `lang/vendor/laravel-auto-crud/` where you can customize response messages for different languages.

## Features

### 1. Automatic Media Detection
The package automatically detects if your model uses media traits (`InteractsWithMedia`, `HasMediaConversions`, `HasMedia`) and generates appropriate code for handling media uploads.

### 2. Type Safety with Spatie Data
When using `--pattern=spatie-data`, the package generates type-safe Data Transfer Objects (DTOs) using Spatie Laravel Data. All properties are nullable by default to support optional values in update endpoints. The generated Data classes include:
- **`syncRelationships()` method**: Automatically syncs `belongsToMany` relationships when provided
- **`HasModelAttributes` trait**: Provides `onlyModelAttributes()` to filter fillable attributes and `syncIfSet()` for conditional relationship syncing

### 3. Transaction Safety
All store/update operations in Service classes are wrapped in `DB::transaction()` for data integrity. Services automatically handle:
- Media uploads using `MediaHelper` (automatically imported)
- Relationship syncing via `syncRelationships()` method in Data classes
- Model attribute updates using `onlyModelAttributes()` which filters out null values

### 4. Dynamic Permission System
The package generates policies with dynamic permission resolution. Permission names are resolved using configurable mappings.

#### Permission Seeder Generation

When using the `--permissions-seeder` option, the package will:

1. **Generate Individual Permission Seeders**: Creates a seeder file for each model in `Database/Seeders/Permissions/` folder
   - Example: `WorkerPermissionsSeeder.php` for Worker model
   - Generates permissions for: view, create, update, delete actions
   - Uses `PermissionNameResolver` to ensure consistent permission naming

2. **Update PermissionGroup Enum**: Creates or updates `app/Enums/PermissionGroup.php` with a case for the model
   - Example: `case WORKERS = 'workers';`
   - Maintains a single enum with all permission groups
   - Automatically adds new cases when generating seeders for new models

**Example Generated Seeder:**
```php
<?php

namespace Database\Seeders\Permissions;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Mrmarchone\LaravelAutoCrud\Helpers\PermissionNameResolver;

class WorkerPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $group = 'workers';
        $actions = ['view', 'create', 'update', 'delete'];
        
        foreach ($actions as $action) {
            $permissionName = PermissionNameResolver::resolve($group, $action);
            Permission::firstOrCreate(['name' => $permissionName]);
        }
    }
}
```

**Example PermissionGroup Enum:**
```php
<?php

namespace App\Enums;

enum PermissionGroup: string
{
    case WORKERS = 'workers';
    case USERS = 'users';
    case POSTS = 'posts';
}
```

**Usage:**
```bash
php artisan auto-crud:generate --model=Worker --permissions-seeder
```

This will generate:
- `Database/Seeders/Permissions/WorkerPermissionsSeeder.php`
- Update `app/Enums/PermissionGroup.php` with `WORKERS` case

**Running Seeders:**
```php
// In DatabaseSeeder.php or your main seeder
$this->call([
    \Database\Seeders\Permissions\WorkerPermissionsSeeder::class,
    \Database\Seeders\Permissions\UserPermissionsSeeder::class,
    // ... other permission seeders
]);
```

Or create a master seeder that calls all permission seeders:
```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            \Database\Seeders\Permissions\WorkerPermissionsSeeder::class,
            \Database\Seeders\Permissions\UserPermissionsSeeder::class,
            // Add other permission seeders here
        ]);
    }
}
```

### 5. Standardized Response Messages with Translation Support
With `--response-messages`, the package generates a `ResponseMessages` enum that provides consistent API response messages. The enum includes full translation support:

- **Multi-language support**: Messages are automatically translated based on your application's locale
- **Translation priority**: 
  1. Package translation file (`laravel-auto-crud::response_messages.{key}`)
  2. Config value (`laravel_auto_crud.response_messages.{key}`)
  3. Enum default value
- **Customizable**: Publish translation files to customize messages for your application
- **Built-in languages**: Includes English and Arabic translations out of the box

**Publishing translations:**
```bash
php artisan auto-crud:publish-translations
```

**Translation files location:** `lang/vendor/laravel-auto-crud/{locale}/response_messages.php`

### 6. Pest Test Generation
The `--pest` option generates comprehensive Pest feature tests:
- **Endpoints Test**: Generated in `tests/Feature/{ModelName}/EndpointsTest.php` - covers full CRUD lifecycle
- **Filters Test**: Generated in `tests/Feature/{ModelName}/FiltersTest.php` - includes:
  - Search term filtering (with translatable support)
  - Sorting (ascending and descending)
  - Column-based filtering
  - Date range filtering
  - Pagination with filters
- **Authorization**: Automatic policy testing when policies are present

### 7. Smart Factory Generation
The `--factory` option generates model factories by intelligently mapping database column types to appropriate Faker methods.

### 8. Translatable Support
Full support for Spatie Translatable models:
- **Resources**: Automatically return translated values based on the `Accept-Language` header.
- **Factories**: Generates translatable dummy data.
- **Tests**: Validates translatable fields in CRUD operations.

### 9. Automatic Trait Injection
The package automatically adds necessary traits (`HasFactory`, `FilterQuery`) to your models if they are missing during generation.

### 10. Searchable Prompts
All terminal selection prompts are now searchable, making it much easier to select models and features in large projects.

### 11. Controller Folder Customization
During interactive mode, you can select a custom folder path for generated controllers. This allows you to organize controllers in custom directories like `Http/Controllers/API/V1` or `Http/Controllers/Admin`.

### 12. Relationship Syncing
When using Spatie Data pattern, the package automatically generates `syncRelationships()` method in Data classes for `belongsToMany` relationships. This method uses the `syncIfSet()` helper from `HasModelAttributes` trait to conditionally sync relationships only when IDs are provided.

## Generated Files

### Standard CRUD Files
1. **Controller** (`app/Http/Controllers/API/ModelNameController.php`)
2. **Form Request** (`app/Http/Requests/ModelNameRequest.php`)
3. **API Resource** (`app/Http/Resources/ModelNameResource.php`)
4. **Routes** (`routes/api.php` or `routes/web.php`)
5. **Service** (`app/Services/ModelNameService.php`)
6. **Policy** (`app/Policies/ModelNamePolicy.php`)
7. **Factory** (`database/factories/ModelNameFactory.php`)

### Spatie Data Pattern Files
8. **Data Class** (`app/Data/ModelNameData.php`)
9. **Filter Request** (`app/Http/Requests/ModelNameFilterRequest.php`)
10. **Filter Builder** (`app/FilterBuilders/ModelNameFilterBuilder.php`)

### Response Messages
11. **ResponseMessages Enum** (`app/Enums/ResponseMessages.php`) - Includes translation support
12. **Translation Files** (`lang/vendor/laravel-auto-crud/{locale}/response_messages.php`) - Published via `auto-crud:publish-translations`

### Test Files
13. **Feature Test** (`tests/Feature/ModelName/EndpointsTest.php`) - Full CRUD operations
14. **Filters Test** (`tests/Feature/ModelName/FiltersTest.php`) - Search, sort, and filter operations

### Permission Files
15. **Permission Seeder** (`database/seeders/Permissions/ModelNamePermissionsSeeder.php`) - Generates permissions for the model
16. **PermissionGroup Enum** (`app/Enums/PermissionGroup.php`) - Single enum with all permission groups (auto-updated)

## Helper Files

The package includes several helper classes that are automatically used in generated code to provide common functionality:

### MediaHelper

Located at `Mrmarchone\LaravelAutoCrud\Helpers\MediaHelper`, this helper provides static methods for handling media uploads with Spatie Media Library:

- **`uploadMedia()`**: Handles single or multiple file uploads to a media collection
- **`updateMedia()`**: Updates media by clearing existing collection and uploading new files
- **`deleteCollection()`**: Deletes all media in a collection
- **`deleteMedia()`**: Deletes a specific media item after verifying ownership
- **`deleteManyMedia()`**: Deletes multiple media items with ownership verification

**Usage in generated services:**
```php
use Mrmarchone\LaravelAutoCrud\Helpers\MediaHelper;

// Upload media (automatically imported in services)
MediaHelper::uploadMedia($data->primaryImage, $worker, 'primary-image');
MediaHelper::uploadMedia($data->images, $worker, 'images');

// Update media
MediaHelper::updateMedia($data->primaryImage, $worker, 'primary-image');
MediaHelper::updateMedia($data->images, $worker, 'images');
```

### PermissionNameResolver

Located at `Mrmarchone\LaravelAutoCrud\Helpers\PermissionNameResolver`, this helper resolves permission names based on configurable mappings:

- **`resolve()`**: Resolves permission names using mappings from `config/laravel_auto_crud.php`

**Usage in generated policies:**
```php
use Mrmarchone\LaravelAutoCrud\Helpers\PermissionNameResolver;

$permission = PermissionNameResolver::resolve('users', 'create');
// Returns: "create users" (based on config mappings)
```

### SearchTermEscaper

Located at `Mrmarchone\LaravelAutoCrud\Helpers\SearchTermEscaper`, this helper safely escapes search terms for SQL LIKE queries:

- **`escape()`**: Escapes special characters (`%`, `_`, and escape character) in search terms to prevent SQL injection

**Usage in generated services:**
```php
use Mrmarchone\LaravelAutoCrud\Helpers\SearchTermEscaper;

$escapedTerm = SearchTermEscaper::escape($searchTerm);
// Returns: "%escaped%search%term%" with special characters properly escaped
```

### HasModelAttributes Trait

Located at `Mrmarchone\LaravelAutoCrud\Traits\HasModelAttributes`, this trait is automatically used in generated Spatie Data classes:

- **`onlyModelAttributes()`**: Returns an array of non-null fillable attributes from the Data instance, automatically filtering out null values and converting keys to snake_case
- **`syncIfSet()`**: Conditionally syncs a relationship only if the IDs array is set (not null), preventing unnecessary sync operations

**Usage in generated Data classes:**
```php
use Mrmarchone\LaravelAutoCrud\Traits\HasModelAttributes;

class WorkerData extends Data
{
    use HasModelAttributes;
    
    public ?array $shiftIds;
    public ?array $sectionIds;
    
    public function syncRelationships(Worker $worker): void
    {
        $this->syncIfSet($worker, 'shifts', $this->shiftIds);
        $this->syncIfSet($worker, 'sections', $this->sectionIds);
    }
}
```

**Usage in generated services:**
```php
// In store/update methods
$worker = Worker::create($data->onlyModelAttributes()); // Only non-null fillable attributes
$data->syncRelationships($worker); // Sync relationships if provided
```

### ResponseMessages Translation

The `ResponseMessages` enum automatically uses translations based on your application's locale:

**Usage:**
```php
use App\Enums\ResponseMessages;

// Automatically uses translation based on app locale
ResponseMessages::CREATED->message(); 
// Returns: "Data created successfully." (English)
// Returns: "تم إنشاء البيانات بنجاح." (Arabic if locale is 'ar')

// Works in controllers
return response()->json([
    'data' => $model,
    'message' => ResponseMessages::CREATED->message()
], 201);
```

**Customizing translations:**
1. Publish translation files: `php artisan auto-crud:publish-translations`
2. Edit files in `lang/vendor/laravel-auto-crud/{locale}/response_messages.php`
3. Add new languages by creating new locale directories

**Translation file structure:**
```php
// lang/vendor/laravel-auto-crud/en/response_messages.php
return [
    'retrieved' => 'Data retrieved successfully.',
    'created' => 'Data created successfully.',
    'updated' => 'Data updated successfully.',
    'deleted' => 'Data deleted successfully.',
];
```

**Translation priority:**
1. Package translation file (`laravel-auto-crud::response_messages.{key}`)
2. Config value (`laravel_auto_crud.response_messages.{key}`)
3. Enum default value

## Examples

### Example 1: Basic CRUD Generation
```bash
php artisan auto-crud:generate --model=User --type=api
```

### Example 2: Full-Featured CRUD
```bash
php artisan auto-crud:generate \
  --model=Post \
  --service \
  --policy \
  --permissions-seeder \
  --factory \
  --response-messages \
  --pattern=spatie-data \
  --pest
```

### Example 3: Generate with Permissions
```bash
php artisan auto-crud:generate \
  --model=Worker \
  --policy \
  --permissions-seeder
```

This generates:
- Policy class with permission-based authorization
- Permission seeder in `Database/Seeders/Permissions/WorkerPermissionsSeeder.php`
- Updates `app/Enums/PermissionGroup.php` with `WORKERS` case

## License

**Laravel Auto CRUD** was created by **[Mustafa Fares](https://www.linkedin.com/in/mustafa-fares/)** forking from **[mrmarchone/laravel-auto-crud](https://github.com/mrmarchone/laravel-auto-crud)** Package.

Licensed under the MIT License.
