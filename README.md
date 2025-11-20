
## Auto-CRUD Generation

This project includes an auto-CRUD generation system that can automatically create controllers, services, requests, resources, and filter builders for your models.

### Basic Usage

```bash
php artisan auto-crud:generate --model=User
```

### Available Options

- `--service` or `-S` - Generate with service layer (no repository pattern)
- `--filter` or `-F` - Generate filter builder and filter request for advanced filtering
- `--pattern=spatie-data` or `-P=spatie-data` - Use Spatie Laravel Data pattern (requires FormRequest + Data class)
- `--type=api` or `-T=api` - Generate API controller (default: api)
- `--type=web` or `-T=web` - Generate web controller
- `--type=both` or `-T=both` - Generate both API and web controllers
- `--overwrite` or `-O` - Overwrite existing files
- `--model=ModelName` or `-M=ModelName` - Specify model(s) to generate CRUD for
- `--model-path=path` or `-MP=path` - Set custom models path

### Examples

**Generate CRUD with service layer and Spatie Data pattern:**
```bash
php artisan auto-crud:generate --service --pattern=spatie-data --model=User
```

**Generate CRUD with filter builder:**
```bash
php artisan auto-crud:generate --service --pattern=spatie-data --filter --model=User
```

**Generate with all options:**
```bash
php artisan auto-crud:generate --service --pattern=spatie-data --filter --model=User --overwrite
```

### Generated Files

When using `--service --pattern=spatie-data --filter`, the command generates:

1. **UserService** (`app/Services/UserService.php`)
    - Contains `store()` and `update()` methods only
    - Uses `DB::transaction()` for data safety
    - Handles media uploads automatically if model uses `HasMediaConversions` trait
    - Accepts `UserData` objects instead of arrays

2. **UserRequest** (`app/Http/Requests/UserRequest.php`)
    - FormRequest for validation
    - Properties in camelCase
    - Includes media validation rules automatically

3. **UserFilterRequest** (`app/Http/Requests/UserFilterRequest.php`) - *when using `--filter`*
    - Validation for `search` and `perPage` parameters

4. **UserFilterBuilder** (`app/FilterBuilders/UserFilterBuilder.php`) - *when using `--filter`*
    - Extends `BaseFilterBuilder`
    - Includes `textSearch()` method if model has searchable properties
    - Used in controller's `index()` method

5. **UserData** (`app/Data/UserData.php`)
    - Spatie Laravel Data class
    - Includes `HasModelAttributes` trait
    - Automatically includes media properties if model uses media trait

6. **UserResource** (`app/Http/Resources/UserResource.php`)
    - API Resource for responses
    - Includes `@mixin` PHPDoc tag
    - Always includes `id`, `createdAt`, `updatedAt`
    - Automatically handles media collections
    - Properties in camelCase

7. **UserController** (`app/Http/Controllers/API/UserController.php`)
    - No base controller extension
    - No try-catch blocks (Laravel handles exceptions)
    - Uses Resources for responses
    - Index method uses FilterBuilder when `--filter` is enabled
    - Loads media relations automatically
    - Destroy returns `response()->noContent()` (204 status)

### Features

- **Automatic Media Detection**: Detects media fields from model traits and generates appropriate code
- **Type Safety**: Uses Data classes with proper type hints
- **Transaction Safety**: All store/update operations wrapped in DB transactions
- **CamelCase Properties**: All request and resource properties use camelCase
- **Media Support**: Automatically handles single and multiple file uploads
- **Filter Support**: Advanced filtering with search and pagination

## License
**Laravel Starter Kit** was created by **[Mustafa Fares](https://www.linkedin.com/in/mustafa-fares/)** Forking from **[mrmarchone/laravel-auto-crud](https://github.com/mrmarchone/laravel-auto-crud)** Package.
