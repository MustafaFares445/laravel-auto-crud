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
- Data pattern selection (Normal/Spatie Data)
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

## Features

### 1. Automatic Media Detection
The package automatically detects if your model uses media traits (`InteractsWithMedia`, `HasMediaConversions`, `HasMedia`) and generates appropriate code for handling media uploads.

### 2. Type Safety with Spatie Data
When using `--pattern=spatie-data`, the package generates type-safe Data Transfer Objects (DTOs) using Spatie Laravel Data.

### 3. Transaction Safety
All store/update operations in Service classes are wrapped in `DB::transaction()` for data integrity.

### 4. Dynamic Permission System
The package generates policies with dynamic permission resolution. Permission names are resolved using configurable mappings.

### 5. Standardized Response Messages
With `--response-messages`, the package generates a `ResponseMessages` enum that provides consistent API response messages.

### 6. Pest Test Generation
The `--pest` option generates comprehensive Pest feature tests:
- **Location**: Generated in `tests/Feature/{ModelName}/EndpointsTest.php`.
- **Coverage**: Full CRUD lifecycle, including filtering, sorting, and searching.
- **Authorization**: Automatic policy testing when policies are present.

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
11. **ResponseMessages Enum** (`app/Enums/ResponseMessages.php`)

### Test Files
12. **Feature Test** (`tests/Feature/ModelName/EndpointsTest.php`)

## Helper Files

The package includes several helper classes that are automatically used in generated code to provide common functionality:

### MediaHelper

Located at `Mrmarchone\LaravelAutoCrud\Helpers\MediaHelper`, this helper provides static methods for handling media uploads with Spatie Media Library:

- **`uploadMedia()`**: Handles single or multiple file uploads to a media collection
- **`updateMedia()`**: Updates media by clearing existing collection and uploading new files
- **`deleteCollection()`**: Deletes all media in a collection
- **`deleteMedia()`**: Deletes a specific media item after verifying ownership
- **`deleteManyMedia()`**: Deletes multiple media items with ownership verification

**Usage in generated controllers:**
```php
use Mrmarchone\LaravelAutoCrud\Helpers\MediaHelper;

// Upload media
MediaHelper::uploadMedia($request->file('image'), $model, 'images');

// Update media
MediaHelper::updateMedia($request->file('image'), $model, 'images');
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
  --factory \
  --response-messages \
  --pattern=spatie-data \
  --pest
```

## License

**Laravel Auto CRUD** was created by **[Mustafa Fares](https://www.linkedin.com/in/mustafa-fares/)** forking from **[mrmarchone/laravel-auto-crud](https://github.com/mrmarchone/laravel-auto-crud)** Package.

Licensed under the MIT License.
