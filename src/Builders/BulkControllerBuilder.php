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

        $originalModelName = $modelData['modelName'];
        $modifiedModelData = $modelData;
        $modifiedModelData['modelName'] = 'Bulk' . $originalModelName;

        return $this->fileService->createFromStub($modifiedModelData, 'bulk.controller', $controllerFolder, 'Controller', $overwrite, function ($modelData) use ($resource, $bulkRequests, $useResponseMessages, $originalModelName) {
            $originalModelData = $modelData;
            $originalModelData['modelName'] = $originalModelName;
            $model = $this->getFullModelNamespace($originalModelData);
            $resourceName = explode('\\', $resource);
            $modelVariable = lcfirst($originalModelName);
            $resourceClass = end($resourceName);

            $bulkMethods = $this->generateBulkMethods($bulkRequests, $originalModelName, $modelVariable, $resourceClass, $useResponseMessages, false, false);

            $bulkRequestImports = $this->buildBulkRequestImports($bulkRequests);
            $responseMessagesImport = $useResponseMessages ? "use Mrmarchone\LaravelAutoCrud\Enums\ResponseMessages;\n" : '';
            $resourceImport = $resource ? "use {$resource};\n" : '';

            return [
                '{{ bulkRequestImports }}' => $bulkRequestImports,
                '{{ responseMessagesImport }}' => $responseMessagesImport,
                '{{ resourceImport }}' => $resourceImport,
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

        $originalModelName = $modelData['modelName'];
        $modifiedModelData = $modelData;
        $modifiedModelData['modelName'] = 'Bulk' . $originalModelName;

        return $this->fileService->createFromStub($modifiedModelData, 'bulk.controller', $controllerFolder, 'Controller', $overwrite, function ($modelData) use ($bulkRequests, $originalModelName) {
            $originalModelData = $modelData;
            $originalModelData['modelName'] = $originalModelName;
            $model = $this->getFullModelNamespace($originalModelData);
            $modelVariable = lcfirst($originalModelName);

            $bulkMethods = $this->generateBulkMethods($bulkRequests, $originalModelName, $modelVariable, '', false, false, true);

            $bulkRequestImports = $this->buildBulkRequestImports($bulkRequests);

            return [
                '{{ bulkRequestImports }}' => $bulkRequestImports,
                '{{ responseMessagesImport }}' => '',
                '{{ resourceImport }}' => '',
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

    private function generateBulkMethods(array $bulkRequests, string $modelName, string $modelVariable, string $resourceClass, bool $useResponseMessages = false, bool $isSpatieData = false, bool $isWeb = false): string
    {
        if (empty($bulkRequests)) {
            return '';
        }

        $model = $this->getFullModelNamespace(['modelName' => $modelName]);
        $methods = '';

        $routeName = HelperService::toSnakeCase(Str::plural($modelName));

        if (isset($bulkRequests['bulkStore'])) {
            $bulkStoreRequest = explode('\\', $bulkRequests['bulkStore']);
            $bulkStoreRequestClass = end($bulkStoreRequest);

            if ($isWeb) {
                $methods .= "\n    /**\n     * Create multiple {$modelVariable}s.\n     *\n     * @param {$bulkStoreRequestClass} \$request\n     * @return \\Illuminate\\Http\\RedirectResponse\n     */\n    public function bulkStore({$bulkStoreRequestClass} \$request): \\Illuminate\\Http\\RedirectResponse\n    {\n        try {\n            \$items = \$request->validated()['items'];\n            \n            foreach (\$items as \$item) {\n                {$model}::create(\$item);\n            }\n            \n            return redirect()->route('{$routeName}.index')->with('success', 'Bulk created successfully');\n        } catch (\\Exception \$exception) {\n            report(\$exception);\n            return redirect()->back()->with('error', 'There is an error.')->withInput();\n        }\n    }";
            } else {
                $createMessage = $useResponseMessages
                    ? "->additional(['message' => ResponseMessages::CREATED->message()])"
                    : '';
                $returnType = $resourceClass ? "\\Illuminate\\Http\\Resources\\Json\\AnonymousResourceCollection|\\Illuminate\\Http\\JsonResponse" : "\\Illuminate\\Http\\JsonResponse";
                $messageText = $useResponseMessages ? 'ResponseMessages::CREATED->message()' : "'Created successfully'";
                $returnStatement = $resourceClass ? "return {$resourceClass}::collection(collect(\${$modelVariable}s)){$createMessage};" : "return response()->json(['data' => \${$modelVariable}s, 'message' => {$messageText}], \\Symfony\\Component\\HttpFoundation\\Response::HTTP_CREATED);";

                $methods .= "\n    /**\n     * Create multiple {$modelVariable}s.\n     *\n     * @param {$bulkStoreRequestClass} \$request\n     * @return {$returnType}\n     */\n    public function bulkStore({$bulkStoreRequestClass} \$request): {$returnType}\n    {\n        try {\n            \$items = \$request->validated()['items'];\n            \${$modelVariable}s = [];\n            \n            foreach (\$items as \$item) {\n                \${$modelVariable}s[] = {$model}::create(\$item);\n            }\n            \n            {$returnStatement}\n        } catch (\\Exception \$exception) {\n            report(\$exception);\n            return response()->json(['error' => 'There is an error.'], \\Symfony\\Component\\HttpFoundation\\Response::HTTP_INTERNAL_SERVER_ERROR);\n        }\n    }";
            }
        }

        if (isset($bulkRequests['bulkUpdate'])) {
            $bulkUpdateRequest = explode('\\', $bulkRequests['bulkUpdate']);
            $bulkUpdateRequestClass = end($bulkUpdateRequest);

            if ($isWeb) {
                $methods .= "\n\n    /**\n     * Update multiple {$modelVariable}s.\n     *\n     * @param {$bulkUpdateRequestClass} \$request\n     * @return \\Illuminate\\Http\\RedirectResponse\n     */\n    public function bulkUpdate({$bulkUpdateRequestClass} \$request): \\Illuminate\\Http\\RedirectResponse\n    {\n        try {\n            \$items = \$request->validated()['items'];\n            \n            foreach (\$items as \$item) {\n                \$id = \$item['id'];\n                unset(\$item['id']);\n                \${$modelVariable} = {$model}::findOrFail(\$id);\n                \${$modelVariable}->update(\$item);\n            }\n            \n            return redirect()->route('{$routeName}.index')->with('success', 'Bulk updated successfully');\n        } catch (\\Exception \$exception) {\n            report(\$exception);\n            return redirect()->back()->with('error', 'There is an error.')->withInput();\n        }\n    }";
            } else {
                $updateMessage = $useResponseMessages
                    ? "->additional(['message' => ResponseMessages::UPDATED->message()])"
                    : '';
                $returnType = $resourceClass ? "\\Illuminate\\Http\\Resources\\Json\\AnonymousResourceCollection|\\Illuminate\\Http\\JsonResponse" : "\\Illuminate\\Http\\JsonResponse";
                $messageText = $useResponseMessages ? 'ResponseMessages::UPDATED->message()' : "'Updated successfully'";
                $returnStatement = $resourceClass ? "return {$resourceClass}::collection(collect(\${$modelVariable}s)){$updateMessage};" : "return response()->json(['data' => \${$modelVariable}s, 'message' => {$messageText}], \\Symfony\\Component\\HttpFoundation\\Response::HTTP_OK);";

                $methods .= "\n\n    /**\n     * Update multiple {$modelVariable}s.\n     *\n     * @param {$bulkUpdateRequestClass} \$request\n     * @return {$returnType}\n     */\n    public function bulkUpdate({$bulkUpdateRequestClass} \$request): {$returnType}\n    {\n        try {\n            \$items = \$request->validated()['items'];\n            \${$modelVariable}s = [];\n            \n            foreach (\$items as \$item) {\n                \$id = \$item['id'];\n                unset(\$item['id']);\n                \${$modelVariable} = {$model}::findOrFail(\$id);\n                \${$modelVariable}->update(\$item);\n                \${$modelVariable}s[] = \${$modelVariable}->fresh();\n            }\n            \n            {$returnStatement}\n        } catch (\\Exception \$exception) {\n            report(\$exception);\n            return response()->json(['error' => 'There is an error.'], \\Symfony\\Component\\HttpFoundation\\Response::HTTP_INTERNAL_SERVER_ERROR);\n        }\n    }";
            }
        }

        if (isset($bulkRequests['bulkDelete'])) {
            $bulkDeleteRequest = explode('\\', $bulkRequests['bulkDelete']);
            $bulkDeleteRequestClass = end($bulkDeleteRequest);

            $deleteMessage = $useResponseMessages
                ? "ResponseMessages::DELETED->message()"
                : "'Deleted successfully'";

            if ($isWeb) {
                $methods .= "\n\n    /**\n     * Delete multiple {$modelVariable}s.\n     *\n     * @param {$bulkDeleteRequestClass} \$request\n     * @return \\Illuminate\\Http\\RedirectResponse\n     */\n    public function bulkDestroy({$bulkDeleteRequestClass} \$request): \\Illuminate\\Http\\RedirectResponse\n    {\n        try {\n            \$ids = \$request->validated()['ids'];\n            {$model}::whereIn('id', \$ids)->delete();\n            \n            return redirect()->route('{$routeName}.index')->with('success', 'Bulk deleted successfully');\n        } catch (\\Exception \$exception) {\n            report(\$exception);\n            return redirect()->back()->with('error', 'There is an error.');\n        }\n    }";
            } else {
                $methods .= "\n\n    /**\n     * Delete multiple {$modelVariable}s.\n     *\n     * @param {$bulkDeleteRequestClass} \$request\n     * @return \\Illuminate\\Http\\JsonResponse\n     */\n    public function bulkDestroy({$bulkDeleteRequestClass} \$request): \\Illuminate\\Http\\JsonResponse\n    {\n        try {\n            \$ids = \$request->validated()['ids'];\n            {$model}::whereIn('id', \$ids)->delete();\n            \n            return response()->json(['message' => {$deleteMessage}, 'deleted_count' => count(\$ids)], \\Symfony\\Component\\HttpFoundation\\Response::HTTP_OK);\n        } catch (\\Exception \$exception) {\n            report(\$exception);\n            return response()->json(['error' => 'There is an error.'], \\Symfony\\Component\\HttpFoundation\\Response::HTTP_INTERNAL_SERVER_ERROR);\n        }\n    }";
            }
        }

        return $methods;
    }
}
