<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Builders;

use Illuminate\Support\Str;
use Mrmarchone\LaravelAutoCrud\Services\FileService;
use Mrmarchone\LaravelAutoCrud\Services\HelperService;
use Mrmarchone\LaravelAutoCrud\Traits\ModelHelperTrait;

class BulkControllerBuilder
{
    use ModelHelperTrait;

    protected FileService $fileService;

    public function __construct(FileService $fileService)
    {
        $this->fileService = $fileService;
    }

    public function createAPI(array $modelData, string $resource, array $bulkRequests, array $options = []): string
    {
        $overwrite = $options['overwrite'] ?? false;
        $useResponseMessages = $options['response-messages'] ?? false;
        $controllerFolder = $options['controller-folder'] ?? config('laravel_auto_crud.default_api_controller_folder', 'Http/Controllers/API');
        // Only use service if it's a non-empty string (namespace), not a boolean
        $service = (isset($options['service']) && is_string($options['service']) && !empty($options['service'])) ? $options['service'] : null;
        $pattern = $options['pattern'] ?? 'normal';
        $spatieData = $options['spatieData'] ?? null;

        $originalModelName = $modelData['modelName'];
        $modifiedModelData = $modelData;
        $modifiedModelData['modelName'] = $originalModelName . 'Bulk';

        // Ensure service is a string or null for stub selection
        $serviceForStub = (is_string($service) && !empty($service)) ? $service : null;
        $stubName = $serviceForStub ? 'bulk_api_service.controller' : 'bulk.controller';

        return $this->fileService->createFromStub($modifiedModelData, $stubName, $controllerFolder, 'Controller', $overwrite, function ($modelData) use ($resource, $bulkRequests, $useResponseMessages, $originalModelName, $service, $pattern, $spatieData) {
            $originalModelData = $modelData;
            $originalModelData['modelName'] = $originalModelName;
            $model = $this->getFullModelNamespace($originalModelData);
            $resourceName = explode('\\', $resource);
            $modelVariable = lcfirst($originalModelName);
            $resourceClass = end($resourceName);

            // Ensure service is a string or null, not a boolean
            $serviceForMethods = (is_string($service) && !empty($service)) ? $service : null;
            $bulkMethods = $this->generateBulkMethods($bulkRequests, $originalModelName, $modelVariable, $resourceClass, $useResponseMessages, $pattern === 'spatie-data', false, $serviceForMethods, $spatieData);

            $bulkRequestImports = $this->buildBulkRequestImports($bulkRequests);
            $responseMessagesImport = $useResponseMessages ? "use Mrmarchone\LaravelAutoCrud\Enums\ResponseMessages;\n" : '';
            $resourceImport = $resource ? "use {$resource};\n" : '';
            $serviceImport = '';
            $serviceConstructor = '';
            $dbImport = '';
            $dataImport = '';
            
            if ($serviceForMethods) {
                $serviceNamespace = "App\\Services\\" . $originalModelName . "BulkService";
                $serviceClass = $originalModelName . "BulkService";
                $serviceVariable = lcfirst($serviceClass);
                $serviceImport = "use {$serviceNamespace};\n";
                $serviceConstructor = "public function __construct(protected {$serviceClass} \${$serviceVariable}){}\n\n";
                
                if ($pattern === 'spatie-data' && $spatieData) {
                    $dataImport = "use {$spatieData};\n";
                    $dbImport = "use Illuminate\\Support\\Facades\\DB;\n";
                }
            } elseif ($pattern === 'spatie-data' && $spatieData) {
                // Even without service, we need DB for transaction wrapping
                $dbImport = "use Illuminate\\Support\\Facades\\DB;\n";
            }

            return [
                '{{ bulkRequestImports }}' => $bulkRequestImports,
                '{{ responseMessagesImport }}' => $responseMessagesImport,
                '{{ resourceImport }}' => $resourceImport,
                '{{ serviceImport }}' => $serviceImport,
                '{{ dataImport }}' => $dataImport,
                '{{ dbImport }}' => $dbImport,
                '{{ serviceConstructor }}' => $serviceConstructor,
                '{{ modelNamespace }}' => $model,
                '{{ model }}' => $originalModelName,
                '{{ modelVariable }}' => $modelVariable,
                '{{ modelPlural }}' => HelperService::toSnakeCase(Str::plural($originalModelName)),
                '{{ bulkMethods }}' => $bulkMethods,
            ];
        });
    }

    public function createWeb(array $modelData, array $bulkRequests, array $options = []): string
    {
        $overwrite = $options['overwrite'] ?? false;
        $controllerFolder = $options['controller-folder'] ?? config('laravel_auto_crud.default_web_controller_folder', 'Http/Controllers');
        // Only use service if it's a non-empty string (namespace), not a boolean
        $service = (isset($options['service']) && is_string($options['service']) && !empty($options['service'])) ? $options['service'] : null;
        $pattern = $options['pattern'] ?? 'normal';
        $spatieData = $options['spatieData'] ?? null;

        $originalModelName = $modelData['modelName'];
        $modifiedModelData = $modelData;
        $modifiedModelData['modelName'] = $originalModelName . 'Bulk';

        // Ensure service is a string or null for stub selection
        $serviceForStub = (is_string($service) && !empty($service)) ? $service : null;
        $stubName = $serviceForStub ? 'bulk_web_service.controller' : 'bulk.controller';

        return $this->fileService->createFromStub($modifiedModelData, $stubName, $controllerFolder, 'Controller', $overwrite, function ($modelData) use ($bulkRequests, $originalModelName, $service, $pattern, $spatieData) {
            $originalModelData = $modelData;
            $originalModelData['modelName'] = $originalModelName;
            $model = $this->getFullModelNamespace($originalModelData);
            $modelVariable = lcfirst($originalModelName);

            // Ensure service is a string or null, not a boolean
            $serviceForMethods = (is_string($service) && !empty($service)) ? $service : null;
            $bulkMethods = $this->generateBulkMethods($bulkRequests, $originalModelName, $modelVariable, '', false, $pattern === 'spatie-data', true, $serviceForMethods, $spatieData);

            $bulkRequestImports = $this->buildBulkRequestImports($bulkRequests);
            $serviceImport = '';
            $serviceConstructor = '';
            $dbImport = '';
            $dataImport = '';
            
            if ($serviceForMethods) {
                $serviceNamespace = "App\\Services\\" . $originalModelName . "BulkService";
                $serviceClass = $originalModelName . "BulkService";
                $serviceVariable = lcfirst($serviceClass);
                $serviceImport = "use {$serviceNamespace};\n";
                $serviceConstructor = "public function __construct(protected {$serviceClass} \${$serviceVariable}){}\n\n";
                
                if ($pattern === 'spatie-data' && $spatieData) {
                    $dataImport = "use {$spatieData};\n";
                    $dbImport = "use Illuminate\\Support\\Facades\\DB;\n";
                }
            } elseif ($pattern === 'spatie-data' && $spatieData) {
                $dbImport = "use Illuminate\\Support\\Facades\\DB;\n";
            }

            return [
                '{{ bulkRequestImports }}' => $bulkRequestImports,
                '{{ responseMessagesImport }}' => '',
                '{{ resourceImport }}' => '',
                '{{ serviceImport }}' => $serviceImport,
                '{{ dataImport }}' => $dataImport,
                '{{ dbImport }}' => $dbImport,
                '{{ serviceConstructor }}' => $serviceConstructor,
                '{{ modelNamespace }}' => $model,
                '{{ model }}' => $originalModelName,
                '{{ modelVariable }}' => $modelVariable,
                '{{ modelPlural }}' => HelperService::toSnakeCase(Str::plural($originalModelName)),
                '{{ bulkMethods }}' => $bulkMethods,
            ];
        });
    }

    private function buildBulkRequestImports(array $bulkRequests): string
    {
        $bulkRequestImports = '';
        $bulkRequestNamespaces = [];

        if (isset($bulkRequests['bulkStore'])) {
            $bulkRequestNamespaces[] = $bulkRequests['bulkStore'];
        }
        if (isset($bulkRequests['bulkUpdate'])) {
            $bulkRequestNamespaces[] = $bulkRequests['bulkUpdate'];
        }
        if (isset($bulkRequests['bulkDelete'])) {
            $bulkRequestNamespaces[] = $bulkRequests['bulkDelete'];
        }

        foreach ($bulkRequestNamespaces as $bulkRequestNamespace) {
            $bulkRequestImports .= "use {$bulkRequestNamespace};\n";
        }

        return $bulkRequestImports;
    }

    private function generateBulkMethods(array $bulkRequests, string $modelName, string $modelVariable, string $resourceClass, bool $useResponseMessages = false, bool $isSpatieData = false, bool $isWeb = false, ?string $service = null, ?string $spatieDataClass = null): string
    {
        if (empty($bulkRequests)) {
            return '';
        }

        $model = $this->getFullModelNamespace(['modelName' => $modelName]);
        $methods = '';

        $routeName = HelperService::toSnakeCase(Str::plural($modelName));
        $serviceVariable = $service ? lcfirst($modelName . 'BulkService') : null;
        $spatieDataNameParts = $spatieDataClass ? explode('\\', $spatieDataClass) : [];
        $spatieDataName = !empty($spatieDataNameParts) ? end($spatieDataNameParts) : null;

        if (isset($bulkRequests['bulkStore'])) {
            $bulkStoreRequest = explode('\\', $bulkRequests['bulkStore']);
            $bulkStoreRequestClass = end($bulkStoreRequest);

            if ($isWeb) {
                if ($service) {
                    $methods .= "\n    public function store({$bulkStoreRequestClass} \$request): \\Illuminate\\Http\\RedirectResponse\n    {\n        \$this->{$serviceVariable}->store(\$request->validated('items'));\n        \n        return redirect()->route('{$routeName}.index')->with('success', 'Bulk created successfully');\n    }";
                } else {
                    $methods .= "\n    public function store({$bulkStoreRequestClass} \$request): \\Illuminate\\Http\\RedirectResponse\n    {\n        \$items = \$request->validated('items');\n        \n        DB::transaction(static function () use (\$items) {\n            {$model}::insert(\$items);\n        });\n        \n        return redirect()->route('{$routeName}.index')->with('success', 'Bulk created successfully');\n    }";
                }
            } else {
                $createMessage = $useResponseMessages
                    ? "->additional(['message' => ResponseMessages::CREATED->message()])"
                    : '';
                $returnType = $resourceClass ? "\\Illuminate\\Http\\Resources\\Json\\AnonymousResourceCollection" : "\\Illuminate\\Http\\JsonResponse";
                $messageText = $useResponseMessages ? 'ResponseMessages::CREATED->message()' : "'Created successfully'";
                
                if ($service) {
                    $returnStatement = $resourceClass ? "return {$resourceClass}::collection(\${$modelVariable}s){$createMessage};" : "return response()->json(['data' => \${$modelVariable}s->toArray(), 'message' => {$messageText}], \\Symfony\\Component\\HttpFoundation\\Response::HTTP_CREATED);";
                    $methods .= "\n    public function store({$bulkStoreRequestClass} \$request): {$returnType}\n    {\n        \${$modelVariable}s = \$this->{$serviceVariable}->store(\$request->validated('items'));\n        \n        {$returnStatement}\n    }";
                } else {
                    $returnStatement = $resourceClass ? "return {$resourceClass}::collection({$model}::latest()->take(count(\$items))->get()){$createMessage};" : "return response()->json(['data' => {$model}::latest()->take(count(\$items))->get(), 'message' => {$messageText}], \\Symfony\\Component\\HttpFoundation\\Response::HTTP_CREATED);";
                    $methods .= "\n    public function store({$bulkStoreRequestClass} \$request): {$returnType}\n    {\n        \$items = \$request->validated('items');\n        \n        DB::transaction(static function () use (\$items) {\n            {$model}::insert(\$items);\n        });\n        \n        {$returnStatement}\n    }";
                }
            }
        }

        if (isset($bulkRequests['bulkUpdate'])) {
            $bulkUpdateRequest = explode('\\', $bulkRequests['bulkUpdate']);
            $bulkUpdateRequestClass = end($bulkUpdateRequest);

            if ($isWeb) {
                if ($service) {
                    $methods .= "\n\n    public function update({$bulkUpdateRequestClass} \$request): \\Illuminate\\Http\\RedirectResponse\n    {\n        \$this->{$serviceVariable}->update(\$request->validated('items'));\n        \n        return redirect()->route('{$routeName}.index')->with('success', 'Bulk updated successfully');\n    }";
                } else {
                    $methods .= "\n\n    public function update({$bulkUpdateRequestClass} \$request): \\Illuminate\\Http\\RedirectResponse\n    {\n        \$items = \$request->validated('items');\n        \$ids = array_column(\$items, 'id');\n        \${$modelVariable}s = {$model}::whereIn('id', \$ids)->get()->keyBy('id');\n        \n        DB::transaction(static function () use (\$items, \${$modelVariable}s) {\n            foreach (\$items as \$item) {\n                \$id = \$item['id'];\n                unset(\$item['id']);\n                \${$modelVariable} = \${$modelVariable}s->get(\$id);\n                \${$modelVariable}->update(\$item);\n            }\n        });\n        \n        return redirect()->route('{$routeName}.index')->with('success', 'Bulk updated successfully');\n    }";
                }
            } else {
                $updateMessage = $useResponseMessages
                    ? "->additional(['message' => ResponseMessages::UPDATED->message()])"
                    : '';
                $returnType = $resourceClass ? "\\Illuminate\\Http\\Resources\\Json\\AnonymousResourceCollection" : "\\Illuminate\\Http\\JsonResponse";
                $messageText = $useResponseMessages ? 'ResponseMessages::UPDATED->message()' : "'Updated successfully'";
                
                if ($service) {
                    $returnStatement = $resourceClass ? "return {$resourceClass}::collection(\${$modelVariable}s){$updateMessage};" : "return response()->json(['data' => \${$modelVariable}s->toArray(), 'message' => {$messageText}], \\Symfony\\Component\\HttpFoundation\\Response::HTTP_OK);";
                    $methods .= "\n\n    public function update({$bulkUpdateRequestClass} \$request): {$returnType}\n    {\n        \${$modelVariable}s = \$this->{$serviceVariable}->update(\$request->validated('items'));\n        \n        {$returnStatement}\n    }";
                } else {
                    $returnStatement = $resourceClass ? "return {$resourceClass}::collection({$model}::whereIn('id', \$ids)->get()){$updateMessage};" : "return response()->json(['data' => {$model}::whereIn('id', \$ids)->get(), 'message' => {$messageText}], \\Symfony\\Component\\HttpFoundation\\Response::HTTP_OK);";
                    $methods .= "\n\n    public function update({$bulkUpdateRequestClass} \$request): {$returnType}\n    {\n        \$items = \$request->validated('items');\n        \$ids = array_column(\$items, 'id');\n        \${$modelVariable}s = {$model}::whereIn('id', \$ids)->get()->keyBy('id');\n        \n        DB::transaction(static function () use (\$items, \${$modelVariable}s) {\n            foreach (\$items as \$item) {\n                \$id = \$item['id'];\n                unset(\$item['id']);\n                \${$modelVariable} = \${$modelVariable}s->get(\$id);\n                \${$modelVariable}->update(\$item);\n            }\n        });\n        \n        {$returnStatement}\n    }";
                }
            }
        }

        if (isset($bulkRequests['bulkDelete'])) {
            $bulkDeleteRequest = explode('\\', $bulkRequests['bulkDelete']);
            $bulkDeleteRequestClass = end($bulkDeleteRequest);

            $deleteMessage = $useResponseMessages
                ? "ResponseMessages::DELETED->message()"
                : "'Deleted successfully'";

            if ($isWeb) {
                if ($service) {
                    $methods .= "\n\n    public function destroy({$bulkDeleteRequestClass} \$request): \\Illuminate\\Http\\RedirectResponse\n    {\n        \$this->{$serviceVariable}->delete(\$request->validated('ids'));\n        \n        return redirect()->route('{$routeName}.index')->with('success', 'Bulk deleted successfully');\n    }";
                } else {
                    $methods .= "\n\n    public function destroy({$bulkDeleteRequestClass} \$request): \\Illuminate\\Http\\RedirectResponse\n    {\n        \$ids = \$request->validated('ids');\n        \n        DB::transaction(static function () use (\$ids) {\n            {$model}::whereIn('id', \$ids)->delete();\n        });\n        \n        return redirect()->route('{$routeName}.index')->with('success', 'Bulk deleted successfully');\n    }";
                }
            } else {
                if ($service) {
                    $methods .= "\n\n    public function destroy({$bulkDeleteRequestClass} \$request): \\Illuminate\\Http\\JsonResponse\n    {\n        \$count = \$this->{$serviceVariable}->delete(\$request->validated('ids'));\n        \n        return response()->json(['message' => {$deleteMessage}, 'deleted_count' => \$count], \\Symfony\\Component\\HttpFoundation\\Response::HTTP_OK);\n    }";
                } else {
                    $methods .= "\n\n    public function destroy({$bulkDeleteRequestClass} \$request): \\Illuminate\\Http\\JsonResponse\n    {\n        \$ids = \$request->validated('ids');\n        \$count = count(\$ids);\n        \n        DB::transaction(static function () use (\$ids) {\n            {$model}::whereIn('id', \$ids)->delete();\n        });\n        \n        return response()->json(['message' => {$deleteMessage}, 'deleted_count' => \$count], \\Symfony\\Component\\HttpFoundation\\Response::HTTP_OK);\n    }";
                }
            }
        }

        return $methods;
    }
}
