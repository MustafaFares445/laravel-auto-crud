<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Services;

use Illuminate\Support\Facades\File;

class AIModelRulesGenerator
{
    private const STUB_PATH = __DIR__ . '/../Stubs/ai-rules/';

    public function generateForTool(string $tool): string
    {
        $stubFile = match ($tool) {
            'cursor' => 'cursor.rules.stub',
            'junie' => 'junie.rules.stub',
            'github-copilot' => 'github-copilot.rules.stub',
            'codeium' => 'codeium.rules.stub',
            'tabnine' => 'tabnine.rules.stub',
            'generic' => 'generic.md.stub',
            default => 'generic.md.stub',
        };

        $stubPath = self::STUB_PATH . $stubFile;

        if (! file_exists($stubPath)) {
            throw new \RuntimeException("Stub file not found: {$stubFile}");
        }

        $content = File::get($stubPath);

        $replacements = [
            '{{ helpers_documentation }}' => $this->generateHelpersDocumentation(),
            '{{ traits_documentation }}' => $this->generateTraitsDocumentation(),
            '{{ coding_flow }}' => $this->generateCodingFlow(),
            '{{ patterns }}' => $this->generatePatterns(),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $content);
    }

    private function generateHelpersDocumentation(): string
    {
        return <<<'MARKDOWN'
## Helper Classes

The package provides several helper classes that are automatically used in generated code:

### MediaHelper
**Location**: `Mrmarchone\LaravelAutoCrud\Helpers\MediaHelper`

Static helper class for managing media uploads with Spatie Media Library.

**Key Methods**:
- `uploadMedia(UploadedFile|array|null $media, HasMedia|Model $model, string $collection = 'default'): Media|array`
  - Handles single or multiple file uploads
  - Automatically detects array vs single file
  - Returns empty array if null provided
  
- `updateMedia(UploadedFile|array|null $media, HasMedia|Model $model, string $collection = 'default'): Media|array`
  - Clears existing collection and uploads new files
  - If null provided, only clears collection
  
- `deleteCollection(HasMedia $model, string $collection = 'default'): void`
  - Deletes all media in a collection
  
- `deleteMedia(HasMedia $model, Media $media): bool`
  - Deletes specific media item after verifying ownership
  - Returns false if media doesn't belong to model
  
- `deleteManyMedia(HasMedia $model, array|Collection $mediaItems): int`
  - Deletes multiple media items with ownership verification
  - Returns count of successfully deleted items

**Usage in Services**:
```php
use Mrmarchone\LaravelAutoCrud\Helpers\MediaHelper;

// Upload media
MediaHelper::uploadMedia($data->primaryImage, $model, 'primary-image');
MediaHelper::uploadMedia($data->images, $model, 'images');

// Update media (replaces existing)
MediaHelper::updateMedia($data->primaryImage, $model, 'primary-image');

// Delete media
MediaHelper::deleteCollection($model, 'images');
```

### PermissionNameResolver
**Location**: `Mrmarchone\LaravelAutoCrud\Helpers\PermissionNameResolver`

Resolves permission names based on configurable mappings from `config/laravel_auto_crud.php`.

**Key Method**:
- `resolve(string $group, string $action): string`
  - Resolves permission name using mappings
  - Format: "{action} {group}" (e.g., "create users", "edit posts")
  - Uses config mappings (e.g., 'update' -> 'edit')
  - Falls back to converting underscores to spaces

**Usage in Policies**:
```php
use Mrmarchone\LaravelAutoCrud\Helpers\PermissionNameResolver;

$permission = PermissionNameResolver::resolve('users', 'create');
// Returns: "create users"

$permission = PermissionNameResolver::resolve('posts', 'update');
// Returns: "edit posts" (based on config mapping)
```

### SearchTermEscaper
**Location**: `Mrmarchone\LaravelAutoCrud\Helpers\SearchTermEscaper`

Safely escapes search terms for SQL LIKE queries to prevent SQL injection.

**Key Method**:
- `escape(string $term, string $escapeCharacter = '!'): string`
  - Escapes special characters: `%`, `_`, and escape character
  - Wraps term with `%` wildcards for partial matching
  - Returns: "%escaped%term%"

**Usage in Services**:
```php
use Mrmarchone\LaravelAutoCrud\Helpers\SearchTermEscaper;

$escapedTerm = SearchTermEscaper::escape($searchTerm);
// Use in query: ->where('name', 'LIKE', $escapedTerm)
```

### TestDataHelper
**Location**: `Mrmarchone\LaravelAutoCrud\Helpers\TestDataHelper`

Generates test payloads for Pest tests based on column definitions.

**Key Methods**:
- `validPayload(array $columns, bool $isUpdate = false): array`
  - Generates valid payload for store/update operations
  - Handles translatable fields, enums, special column names
  
- `invalidPayload(array $columns, ?string $fieldToInvalidate = null): array`
  - Generates invalid payload for validation testing
  - Can target specific field to invalidate
  
- `edgeCasePayload(array $columns): array`
  - Generates edge case payloads (boundary conditions)
  
- `sqlInjectionAttempt(): string`
- `xssAttempt(): string`
- `emptyPayload(): array`

**Usage in Tests**:
```php
use Mrmarchone\LaravelAutoCrud\Helpers\TestDataHelper;

$payload = TestDataHelper::validPayload($columns);
$invalidPayload = TestDataHelper::invalidPayload($columns, 'email');
```
MARKDOWN;
    }

    private function generateTraitsDocumentation(): string
    {
        return <<<'MARKDOWN'
## Traits

The package provides several traits that are used in generated code:

### AuthorizesByPermissionGroup
**Location**: `Mrmarchone\LaravelAutoCrud\Traits\AuthorizesByPermissionGroup`

Trait for Policy classes to authorize actions using permission groups.

**Key Methods**:
- `authorizeAction($user, string $action): bool`
  - Authorizes action using PermissionNameResolver
  - Resolves permission group from class name (e.g., ProductPolicy -> products)
  
- `resolvePermissionGroup(): string`
  - Extracts model name from Policy class name
  - Converts to plural snake_case (e.g., ProductPolicy -> products)

**Usage in Policies**:
```php
use Mrmarchone\LaravelAutoCrud\Traits\AuthorizesByPermissionGroup;

class ProductPolicy
{
    use AuthorizesByPermissionGroup;
    
    public function create($user): bool
    {
        return $this->authorizeAction($user, 'create');
    }
}
```

### HasMediaConversions
**Location**: `Mrmarchone\LaravelAutoCrud\Traits\HasMediaConversions`

Trait for models using Spatie Media Library with predefined collections and conversions.

**Key Methods**:
- `registerMediaCollections(): void`
  - Registers 'primary-image' (single file) and 'images' collections
  - Accepts image MIME types only
  
- `registerMediaConversions(?Media $media = null): void`
  - Registers 'thumb' conversion (webp, 80% quality, non-queued)

**Usage in Models**:
```php
use Mrmarchone\LaravelAutoCrud\Traits\HasMediaConversions;

class Product extends Model implements HasMedia
{
    use HasMediaConversions;
}
```

### HasModelAttributes
**Location**: `Mrmarchone\LaravelAutoCrud\Traits\HasModelAttributes`

Trait for Spatie Data classes to filter fillable attributes and sync relationships.

**Key Methods**:
- `onlyModelAttributes(): array`
  - Returns non-null fillable attributes from Data instance
  - Converts keys to snake_case
  - Filters out null values
  - Only includes attributes in model's fillable array
  
- `syncIfSet(Model $model, string $relation, ?array $ids): void`
  - Conditionally syncs relationship only if IDs array is set (not null)
  - Prevents unnecessary sync operations

**Usage in Data Classes**:
```php
use Mrmarchone\LaravelAutoCrud\Traits\HasModelAttributes;

class ProductData extends Data
{
    use HasModelAttributes;
    
    public ?array $categoryIds;
    
    public function syncRelationships(Product $product): void
    {
        $this->syncIfSet($product, 'categories', $this->categoryIds);
    }
}
```

**Usage in Services**:
```php
$product = Product::create($data->onlyModelAttributes());
$data->syncRelationships($product);
```

### MessageTrait
**Location**: `Mrmarchone\LaravelAutoCrud\Traits\MessageTrait`

Trait for Controllers to provide standardized JSON responses.

**Key Methods**:
- `successMessage(string $message, array $data = [], int $status = 200): JsonResponse`
  - Returns standardized success JSON response
  
- `errorMessage(string $message, int $status = 400, array $errors = []): JsonResponse`
  - Returns standardized error JSON response

**Usage in Controllers**:
```php
use Mrmarchone\LaravelAutoCrud\Traits\MessageTrait;

class ProductController extends Controller
{
    use MessageTrait;
    
    public function store(ProductStoreRequest $request)
    {
        // ...
        return $this->successMessage('Product created', $product->toArray(), 201);
    }
}
```

### ModelHelperTrait
**Location**: `Mrmarchone\LaravelAutoCrud\Traits\ModelHelperTrait`

Trait for Builders to get model information.

**Key Methods**:
- `getFullModelNamespace(array $modelData): string`
  - Returns full namespace path for model
  
- `getHiddenProperties(string $modelClass): array`
  - Gets hidden properties from model's $hidden array
  - Uses reflection to read from file if instantiation fails

**Usage in Builders**:
```php
use Mrmarchone\LaravelAutoCrud\Traits\ModelHelperTrait;

class ResourceBuilder
{
    use ModelHelperTrait;
    
    protected function buildResource(array $modelData): string
    {
        $modelClass = $this->getFullModelNamespace($modelData);
        $hidden = $this->getHiddenProperties($modelClass);
    }
}
```

### TableColumnsTrait
**Location**: `Mrmarchone\LaravelAutoCrud\Traits\TableColumnsTrait`

Trait for Builders to work with table columns.

**Key Methods**:
- `getAvailableColumns(array $modelData): array`
  - Gets available columns from table (excluding timestamps)
  
- `isSearchableColumnType(string $type): bool`
  - Checks if column type is searchable (char, text, string, json, uuid, etc.)

**Usage in Builders**:
```php
use Mrmarchone\LaravelAutoCrud\Traits\TableColumnsTrait;

class RequestBuilder
{
    use TableColumnsTrait;
    
    protected function buildValidationRules(array $modelData): array
    {
        $columns = $this->getAvailableColumns($modelData);
        // Build rules from columns
    }
}
```
MARKDOWN;
    }

    private function generateCodingFlow(): string
    {
        return <<<'MARKDOWN'
## Coding Flow and Architecture

### Generation Process Flow

1. **Command Entry Point**
   - `GenerateAutoCrudCommand` or `GenerateBulkEndpointsCommand` receives user input
   - Validates model existence and database table
   - Collects generation options (type, pattern, features, etc.)

2. **CRUDGenerator Service**
   - Orchestrates the entire generation process
   - Initializes all Builder classes
   - Coordinates generation order:
     - Filter traits (if filters enabled)
     - Requests (Store, Update, Bulk)
     - Resources (for API)
     - Services
     - Policies
     - Factories
     - Controllers (API/Web)
     - Routes
     - Tests (Pest)

3. **Builder Classes**
   Each Builder is responsible for generating a specific component:
   - `ControllerBuilder`: Generates API/Web controllers
   - `RequestBuilder`: Generates Form Request classes
   - `ResourceBuilder`: Generates API Resources
   - `ServiceBuilder`: Generates Service classes
   - `PolicyBuilder`: Generates Policy classes
   - `RouteBuilder`: Generates route definitions
   - `SpatieDataBuilder`: Generates Spatie Data classes
   - `SpatieFilterBuilder`: Generates filter builders
   - `PestBuilder`: Generates Pest tests
   - `FactoryBuilder`: Generates Model factories

4. **FileService**
   - Manages file creation from stub templates
   - Handles namespace resolution
   - Manages file overwrite logic
   - Sanitizes paths to prevent security issues

5. **Supporting Services**
   - `ModelService`: Model discovery and information extraction
   - `TableColumnsService`: Database column information
   - `ModelTraitService`: Trait injection into models
   - `MediaDetector`: Detects media usage in models
   - `RelationshipDetector`: Detects model relationships
   - `SoftDeleteDetector`: Detects soft deletes
   - `EnumDetector`: Detects enum usage

### Service Layer Pattern

Generated Services follow this pattern:

```php
class ProductService
{
    public function store(ProductStoreRequest $request): Product
    {
        return DB::transaction(function () use ($request) {
            $product = Product::create($request->validated());
            
            // Handle media if model uses HasMedia
            if ($product instanceof HasMedia) {
                MediaHelper::uploadMedia($request->file('image'), $product, 'primary-image');
            }
            
            // Sync relationships if using Spatie Data
            // $data->syncRelationships($product);
            
            return $product;
        });
    }
}
```

**Key Principles**:
- All database operations wrapped in transactions
- Media handling via MediaHelper
- Relationship syncing via Data classes
- Use of `onlyModelAttributes()` to filter fillable attributes

### Request Validation Pattern

Generated Form Requests follow this pattern:

```php
class ProductStoreRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'categoryId' => 'required|exists:categories,id',
        ];
    }
}
```

**Key Principles**:
- One-line validation rules for readability
- `required` for non-nullable fields in store requests
- `sometimes` for all fields in update requests
- Proper handling of `Rule::unique()` and `Rule::enum()`

### Resource Transformation Pattern

Generated API Resources follow this pattern:

```php
class ProductResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            // Excludes hidden properties automatically
            // Includes media URLs if HasMedia
            // Includes relationships if loaded
        ];
    }
}
```

**Key Principles**:
- Excludes hidden properties automatically
- Includes media URLs if model uses HasMedia
- Supports translatable fields based on Accept-Language header
- Includes relationships when eager loaded
MARKDOWN;
    }

    private function generatePatterns(): string
    {
        return <<<'MARKDOWN'
## Design Patterns and Best Practices

### Builder Pattern
Builders are used to construct complex objects (files) step by step. Each Builder:
- Takes model data and options as input
- Generates content from stub templates
- Uses FileService to write files
- Returns generated class namespaces

### Dependency Injection
- Services are injected via constructor
- Builders receive dependencies (FileService, ModelService, etc.) in constructor
- Commands receive Services via constructor injection

### Transaction Safety
All store/update operations in Services are wrapped in `DB::transaction()`:
```php
return DB::transaction(function () use ($data) {
    $model = Model::create($data->onlyModelAttributes());
    $data->syncRelationships($model);
    return $model;
});
```

### Type Safety with Spatie Data
When using `--pattern=spatie-data`:
- All properties are nullable by default (supports optional values in updates)
- Type hints ensure type safety
- `onlyModelAttributes()` filters fillable attributes
- `syncRelationships()` handles relationship syncing

### Permission System
- Policies use `AuthorizesByPermissionGroup` trait
- Permission names resolved via `PermissionNameResolver`
- Format: "{action} {group}" (e.g., "create users")
- Configurable mappings in `config/laravel_auto_crud.php`

### Media Handling
- Models implement `HasMedia` interface
- Use `HasMediaConversions` trait for predefined collections
- Media operations via `MediaHelper` static methods
- Automatic detection of media usage in models

### Hidden Properties Support
- Automatically detects `$hidden` array in models
- Excludes hidden properties from:
  - API Resources
  - Form Requests
  - Factories
  - Spatie Data classes
  - Pest tests
  - Filter requests

### Code Generation Consistency
When generating code, maintain consistency by:
- Using helper classes instead of inline logic
- Following established patterns (Service layer, Builder pattern)
- Using traits for reusable functionality
- Wrapping operations in transactions
- Using type-safe Data classes when possible
- Following Laravel conventions and best practices
MARKDOWN;
    }
}
