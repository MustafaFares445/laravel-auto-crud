<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Builders;

use Illuminate\Support\Str;
use Mrmarchone\LaravelAutoCrud\Services\FileService;
use Mrmarchone\LaravelAutoCrud\Services\HelperService;
use Mrmarchone\LaravelAutoCrud\Services\RelationshipDetector;
use Mrmarchone\LaravelAutoCrud\Traits\ModelHelperTrait;
use ReflectionClass;

class ControllerBuilder
{
    use ModelHelperTrait;

    protected FileService $fileService;

    public function __construct(FileService $fileService)
    {
        $this->fileService = $fileService;
    }
    
    /**
     * Check if Laravel base Controller exists and return appropriate extends clause
     *
     * @return array{import: string, extends: string} Array with import and extends strings
     */
    private function getControllerBaseClass(): array
    {
        $baseControllerClass = 'App\\Http\\Controllers\\Controller';
        
        if (class_exists($baseControllerClass)) {
            return [
                'import' => "use {$baseControllerClass};\n",
                'extends' => ' extends Controller',
            ];
        }
        
        return [
            'import' => '',
            'extends' => '',
        ];
    }
    
    public function createAPI(array $modelData, string $resource, array $requests, array $options = []): string
    {
        $filterBuilder = $options['filterBuilder'] ?? null;
        $filterRequest = $options['filterRequest'] ?? null;
        $overwrite = $options['overwrite'] ?? false;
        $useResponseMessages = $options['response-messages'] ?? false;
        $noPagination = $options['no-pagination'] ?? false;
        $hasSoftDeletes = $options['hasSoftDeletes'] ?? false;
        $controllerFolder = $options['controller-folder'] ?? config('laravel_auto_crud.default_api_controller_folder', 'Http/Controllers/API');

        $stubName = $useResponseMessages ? 'api_messages.controller' : 'api.controller';

        $bulkRequests = $options['bulkRequests'] ?? [];
        return $this->fileService->createFromStub($modelData, $stubName, $controllerFolder, 'Controller', $overwrite, function ($modelData) use ($resource, $requests, $filterBuilder, $filterRequest, $useResponseMessages, $noPagination, $hasSoftDeletes, $bulkRequests) {
            $model = $this->getFullModelNamespace($modelData);
            $resourceName = explode('\\', $resource);
            $storeRequestName = explode('\\', $requests['store']);
            $updateRequestName = explode('\\', $requests['update']);

            $filterBuilderImport = '';
            $filterRequestImport = '';
            $filterBuilderClass = 'Request';
            $filterRequestClass = 'Request';
            $modelVariable = lcfirst($modelData['modelName']);
            $resourceClass = end($resourceName);
            $additionalMessage = $useResponseMessages ? "\n            ->additional(['message' => ResponseMessages::RETRIEVED->message()])" : '';
            $belongsToLoadRelations = $this->getBelongsToLoadRelations($model);
            
            // Get controller base class info
            $controllerBaseClass = $this->getControllerBaseClass();

            $indexMethodBody = $this->generateIndexMethodBody(
                $modelData['modelName'],
                $modelVariable,
                $resourceClass,
                $additionalMessage,
                $filterBuilder,
                $filterRequest,
                $noPagination,
                $filterBuilderImport,
                $filterRequestImport,
                $filterBuilderClass,
                $filterRequestClass
            );

            $scrambleData = $this->generateScrambleAttributes($modelData['modelName'], $modelVariable);
            $softDeleteMethods = $this->generateSoftDeleteMethods($hasSoftDeletes, $modelData['modelName'], $modelVariable, $resourceClass, $useResponseMessages);
            $bulkMethods = $this->generateBulkMethods($bulkRequests, $modelData['modelName'], $modelVariable, $resourceClass, $useResponseMessages, false, false);

            // Build bulk request imports
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

            return [
                '{{ storeRequestNamespace }}' => $requests['store'],
                '{{ updateRequestNamespace }}' => $requests['update'],
                '{{ bulkRequestImports }}' => $bulkRequestImports,
                '{{ resourceNamespace }}' => $resource,
                '{{ modelNamespace }}' => $model,
                '{{ resource }}' => $resourceClass,
                '{{ storeRequest }}' => end($storeRequestName),
                '{{ updateRequest }}' => end($updateRequestName),
                '{{ model }}' => $modelData['modelName'],
                '{{ modelVariable }}' => $modelVariable,
                '{{ modelPlural }}' => HelperService::toSnakeCase(Str::plural($modelData['modelName'])),
                '{{ filterBuilderImport }}' => $filterBuilderImport,
                '{{ filterRequestImport }}' => $filterRequestImport,
                '{{ filterBuilder }}' => $filterBuilderClass,
                '{{ filterRequest }}' => $filterRequestClass,
                '{{ indexMethodBody }}' => $indexMethodBody,
                '{{ belongsToLoadRelations }}' => $belongsToLoadRelations,
                '{{ scrambleUseStatements }}' => $scrambleData['useStatements'],
                '{{ scrambleClassAttribute }}' => $scrambleData['classDocComment'],
                '{{ scrambleIndexAttribute }}' => $scrambleData['indexAttribute'],
                '{{ scrambleStoreAttribute }}' => $scrambleData['storeAttribute'],
                '{{ scrambleShowAttribute }}' => $scrambleData['showAttribute'],
                '{{ scrambleUpdateAttribute }}' => $scrambleData['updateAttribute'],
                '{{ scrambleDestroyAttribute }}' => $scrambleData['destroyAttribute'],
                '{{ softDeleteMethods }}' => $softDeleteMethods,
                '{{ bulkMethods }}' => $bulkMethods,
                '{{ controllerImport }}' => $controllerBaseClass['import'],
                '{{ controllerExtends }}' => $controllerBaseClass['extends'],
            ];
        });
    }

    public function createAPISpatieData(array $modelData, string $spatieData, string $resource, array $options = []): string
    {
        $filterBuilder = $options['filterBuilder'] ?? null;
        $filterRequest = $options['filterRequest'] ?? null;
        $overwrite = $options['overwrite'] ?? false;
        $useResponseMessages = $options['response-messages'] ?? false;
        $noPagination = $options['no-pagination'] ?? false;
        $controllerFolder = $options['controller-folder'] ?? config('laravel_auto_crud.default_api_controller_folder', 'Http/Controllers/API');

        $stubName = $useResponseMessages ? 'api_spatie_data_messages.controller' : 'api_spatie_data.controller';

        $hasSoftDeletes = $options['hasSoftDeletes'] ?? false;
        $bulkRequests = $options['bulkRequests'] ?? [];
        
        return $this->fileService->createFromStub($modelData, $stubName, $controllerFolder, 'Controller', $overwrite, function ($modelData) use ($spatieData, $resource, $filterBuilder, $filterRequest, $useResponseMessages, $noPagination, $hasSoftDeletes, $bulkRequests) {
            $model = $this->getFullModelNamespace($modelData);
            $spatieDataName = explode('\\', $spatieData);
            $resourceName = explode('\\', $resource);

            $filterBuilderImport = '';
            $filterRequestImport = '';
            $filterBuilderClass = 'Request';
            $filterRequestClass = 'Request';
            $modelVariable = lcfirst($modelData['modelName']);
            $resourceClass = end($resourceName);
            $additionalMessage = $useResponseMessages ? "\n            ->additional(['message' => ResponseMessages::RETRIEVED->message()])" : '';
            $belongsToLoadRelations = $this->getBelongsToLoadRelations($model);
            
            // Get controller base class info
            $controllerBaseClass = $this->getControllerBaseClass();

            $indexMethodBody = $this->generateIndexMethodBody(
                $modelData['modelName'],
                $modelVariable,
                $resourceClass,
                $additionalMessage,
                $filterBuilder,
                $filterRequest,
                $noPagination,
                $filterBuilderImport,
                $filterRequestImport,
                $filterBuilderClass,
                $filterRequestClass
            );

            $scrambleData = $this->generateScrambleAttributes($modelData['modelName'], $modelVariable);
            $spatieDataClass = end($spatieDataName);
            $softDeleteMethods = $this->generateSoftDeleteMethods($hasSoftDeletes, $modelData['modelName'], $modelVariable, $spatieDataClass, $useResponseMessages, true);
            $bulkMethods = $this->generateBulkMethods($bulkRequests, $modelData['modelName'], $modelVariable, $resourceClass, $useResponseMessages, true, false);

            // Build bulk request imports
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

            return [
                '{{ spatieDataNamespace }}' => $spatieData,
                '{{ modelNamespace }}' => $model,
                '{{ spatieData }}' => end($spatieDataName),
                '{{ resourceNamespace }}' => $resource,
                '{{ resource }}' => $resourceClass,
                '{{ model }}' => $modelData['modelName'],
                '{{ modelVariable }}' => $modelVariable,
                '{{ modelPlural }}' => HelperService::toSnakeCase(Str::plural($modelData['modelName'])),
                '{{ filterBuilderImport }}' => $filterBuilderImport,
                '{{ filterRequestImport }}' => $filterRequestImport,
                '{{ filterRequestNamespace }}' => $filterRequest ?? 'Illuminate\Http\Request',
                '{{ filterBuilder }}' => $filterBuilderClass,
                '{{ filterRequest }}' => $filterRequestClass,
                '{{ indexMethodBody }}' => $indexMethodBody,
                '{{ belongsToLoadRelations }}' => $belongsToLoadRelations,
                '{{ scrambleUseStatements }}' => $scrambleData['useStatements'],
                '{{ scrambleClassAttribute }}' => $scrambleData['classDocComment'],
                '{{ scrambleIndexAttribute }}' => $scrambleData['indexAttribute'],
                '{{ scrambleStoreAttribute }}' => $scrambleData['storeAttribute'],
                '{{ scrambleShowAttribute }}' => $scrambleData['showAttribute'],
                '{{ scrambleUpdateAttribute }}' => $scrambleData['updateAttribute'],
                '{{ scrambleDestroyAttribute }}' => $scrambleData['destroyAttribute'],
                '{{ softDeleteMethods }}' => $softDeleteMethods,
                '{{ bulkMethods }}' => $bulkMethods,
                '{{ bulkRequestImports }}' => $bulkRequestImports,
                '{{ controllerImport }}' => $controllerBaseClass['import'],
                '{{ controllerExtends }}' => $controllerBaseClass['extends'],
            ];
        });
    }


    public function createWeb(array $modelData, array $requests, array $options = []): string
    {
        $overwrite = $options['overwrite'] ?? false;
        $controllerFolder = $options['controller-folder'] ?? config('laravel_auto_crud.default_web_controller_folder', 'Http/Controllers');
        $bulkRequests = $options['bulkRequests'] ?? [];
        return $this->fileService->createFromStub($modelData, 'web.controller', $controllerFolder, 'Controller', $overwrite, function ($modelData) use ($requests, $bulkRequests) {
            $model = $this->getFullModelNamespace($modelData);
            $storeRequestName = explode('\\', $requests['store']);
            $updateRequestName = explode('\\', $requests['update']);
            $belongsToLoadRelations = $this->getBelongsToLoadRelations($model);
            $modelVariable = lcfirst($modelData['modelName']);
            $bulkMethods = $this->generateBulkMethods($bulkRequests, $modelData['modelName'], $modelVariable, '', false, false, true);
            
            // Get controller base class info
            $controllerBaseClass = $this->getControllerBaseClass();

            // Build bulk request imports
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

            return [
                '{{ storeRequestNamespace }}' => $requests['store'],
                '{{ updateRequestNamespace }}' => $requests['update'],
                '{{ bulkRequestImports }}' => $bulkRequestImports,
                '{{ modelNamespace }}' => $model,
                '{{ storeRequest }}' => end($storeRequestName),
                '{{ updateRequest }}' => end($updateRequestName),
                '{{ model }}' => $modelData['modelName'],
                '{{ modelVariable }}' => $modelVariable,
                '{{ viewPath }}' => HelperService::toSnakeCase(Str::plural($modelData['modelName'])),
                '{{ modelPlural }}' => HelperService::toSnakeCase(Str::plural($modelData['modelName'])),
                '{{ routeName }}' => HelperService::toSnakeCase(Str::plural($modelData['modelName'])),
                '{{ belongsToLoadRelations }}' => $belongsToLoadRelations,
                '{{ bulkMethods }}' => $bulkMethods,
                '{{ controllerImport }}' => $controllerBaseClass['import'],
                '{{ controllerExtends }}' => $controllerBaseClass['extends'],
            ];
        });
    }


    public function createWebSpatieData(array $modelData, string $spatieData, array $options = []): string
    {
        $overwrite = $options['overwrite'] ?? false;
        $controllerFolder = $options['controller-folder'] ?? config('laravel_auto_crud.default_web_controller_folder', 'Http/Controllers');
        $bulkRequests = $options['bulkRequests'] ?? [];
        return $this->fileService->createFromStub($modelData, 'web_spatie_data.controller', $controllerFolder, 'Controller', $overwrite, function ($modelData) use ($spatieData, $bulkRequests) {
            $model = $this->getFullModelNamespace($modelData);
            $spatieDataName = explode('\\', $spatieData);
            $belongsToLoadRelations = $this->getBelongsToLoadRelations($model);
            $modelVariable = lcfirst($modelData['modelName']);
            $bulkMethods = $this->generateBulkMethods($bulkRequests, $modelData['modelName'], $modelVariable, '', false, true, true);
            
            // Get controller base class info
            $controllerBaseClass = $this->getControllerBaseClass();

            // Build bulk request imports
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

            return [
                '{{ spatieDataNamespace }}' => $spatieData,
                '{{ modelNamespace }}' => $model,
                '{{ spatieData }}' => end($spatieDataName),
                '{{ model }}' => $modelData['modelName'],
                '{{ modelVariable }}' => $modelVariable,
                '{{ viewPath }}' => HelperService::toSnakeCase(Str::plural($modelData['modelName'])),
                '{{ modelPlural }}' => HelperService::toSnakeCase(Str::plural($modelData['modelName'])),
                '{{ routeName }}' => HelperService::toSnakeCase(Str::plural($modelData['modelName'])),
                '{{ belongsToLoadRelations }}' => $belongsToLoadRelations,
                '{{ bulkMethods }}' => $bulkMethods,
                '{{ bulkRequestImports }}' => $bulkRequestImports,
                '{{ controllerImport }}' => $controllerBaseClass['import'],
                '{{ controllerExtends }}' => $controllerBaseClass['extends'],
            ];
        });
    }


    public function createAPIServiceSpatieData(array $modelData, string $spatieData, array $requests, string $service, string $resource, array $options = []): string
    {
        $filterBuilder = $options['filterBuilder'] ?? null;
        $filterRequest = $options['filterRequest'] ?? null;
        $overwrite = $options['overwrite'] ?? false;
        $useResponseMessages = $options['response-messages'] ?? false;
        $noPagination = $options['no-pagination'] ?? false;
        $hasSoftDeletes = $options['hasSoftDeletes'] ?? false;
        $controllerFolder = $options['controller-folder'] ?? config('laravel_auto_crud.default_api_controller_folder', 'Http/Controllers/API');

        $stubName = $useResponseMessages ? 'api_service_spatie_data_messages.controller' : 'api_service_spatie_data.controller';
        $bulkRequests = $options['bulkRequests'] ?? [];

        return $this->fileService->createFromStub($modelData, $stubName, $controllerFolder, 'Controller', $overwrite, function ($modelData) use ($spatieData, $requests, $service, $resource, $filterBuilder, $filterRequest, $useResponseMessages, $noPagination, $hasSoftDeletes, $bulkRequests) {
            $model = $this->getFullModelNamespace($modelData);
            $spatieDataName = explode('\\', $spatieData);
            $storeRequestName = explode('\\', $requests['store']);
            $updateRequestName = explode('\\', $requests['update']);
            $serviceName = explode('\\', $service);
            $resourceName = explode('\\', $resource);

            $filterBuilderImport = '';
            $filterRequestImport = '';
            $filterBuilderClass = 'Request';
            $filterRequestClass = 'Request';
            $modelVariable = lcfirst($modelData['modelName']);
            $resourceClass = end($resourceName);
            $additionalMessage = $useResponseMessages ? "\n            ->additional(['message' => ResponseMessages::RETRIEVED->message()])" : '';
            $loadRelations = $this->getLoadRelations($model);
            $belongsToLoadRelations = $this->getBelongsToLoadRelations($model);
            
            // Get controller base class info
            $controllerBaseClass = $this->getControllerBaseClass();

            $indexMethodBody = $this->generateIndexMethodBody(
                $modelData['modelName'],
                $modelVariable,
                $resourceClass,
                $additionalMessage,
                $filterBuilder,
                $filterRequest,
                $noPagination,
                $filterBuilderImport,
                $filterRequestImport,
                $filterBuilderClass,
                $filterRequestClass
            );

            $scrambleData = $this->generateScrambleAttributes($modelData['modelName'], $modelVariable);
            $softDeleteMethods = $this->generateSoftDeleteMethods($hasSoftDeletes, $modelData['modelName'], $modelVariable, $resourceClass, $useResponseMessages);
            $bulkMethods = $this->generateBulkMethods($bulkRequests, $modelData['modelName'], $modelVariable, $resourceClass, $useResponseMessages, true, false);

            // Build bulk request imports
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

            return [
                '{{ spatieDataNamespace }}' => $spatieData,
                '{{ spatieData }}' => end($spatieDataName),
                '{{ storeRequestNamespace }}' => $requests['store'],
                '{{ updateRequestNamespace }}' => $requests['update'],
                '{{ storeRequest }}' => end($storeRequestName),
                '{{ updateRequest }}' => end($updateRequestName),
                '{{ serviceNamespace }}' => $service,
                '{{ service }}' => end($serviceName),
                '{{ serviceVariable }}' => lcfirst(end($serviceName)),
                '{{ resourceNamespace }}' => $resource,
                '{{ resource }}' => $resourceClass,
                '{{ modelNamespace }}' => $model,
                '{{ model }}' => $modelData['modelName'],
                '{{ modelVariable }}' => $modelVariable,
                '{{ modelPlural }}' => HelperService::toSnakeCase(Str::plural($modelData['modelName'])),
                '{{ filterBuilderImport }}' => $filterBuilderImport,
                '{{ filterRequestImport }}' => $filterRequestImport,
                '{{ filterRequestNamespace }}' => $filterRequest ?? 'Illuminate\Http\Request',
                '{{ filterBuilder }}' => $filterBuilderClass,
                '{{ filterRequest }}' => $filterRequestClass,
                '{{ indexMethodBody }}' => $indexMethodBody,
                '{{ softDeleteMethods }}' => $softDeleteMethods,
                '{{ bulkMethods }}' => $bulkMethods,
                '{{ bulkRequestImports }}' => $bulkRequestImports,
                '{{ loadRelations }}' => $loadRelations,
                '{{ belongsToLoadRelations }}' => $belongsToLoadRelations,
                '{{ scrambleUseStatements }}' => $scrambleData['useStatements'],
                '{{ scrambleClassAttribute }}' => $scrambleData['classDocComment'],
                '{{ scrambleIndexAttribute }}' => $scrambleData['indexAttribute'],
                '{{ scrambleStoreAttribute }}' => $scrambleData['storeAttribute'],
                '{{ scrambleShowAttribute }}' => $scrambleData['showAttribute'],
                '{{ scrambleUpdateAttribute }}' => $scrambleData['updateAttribute'],
                '{{ scrambleDestroyAttribute }}' => $scrambleData['destroyAttribute'],
                '{{ controllerImport }}' => $controllerBaseClass['import'],
                '{{ controllerExtends }}' => $controllerBaseClass['extends'],
            ];
        });
    }

    /**
     * Extract and prepare filter-related imports and class names.
     *
     * @param string|null $filterBuilder Filter builder class name
     * @param string|null $filterRequest Filter request class name
     * @param string &$filterBuilderImport Output parameter for import statement
     * @param string &$filterRequestImport Output parameter for import statement
     * @param string &$filterBuilderClass Output parameter for class name
     * @param string &$filterRequestClass Output parameter for class name
     * @return void
     */
    private function prepareFilterImports(
        ?string $filterBuilder,
        ?string $filterRequest,
        string &$filterBuilderImport,
        string &$filterRequestImport,
        string &$filterBuilderClass,
        string &$filterRequestClass
    ): void {
        $filterBuilderImport = '';
        $filterRequestImport = '';
        $filterBuilderClass = 'Request';
        $filterRequestClass = 'Request';

        if ($filterBuilder && $filterRequest) {
            $filterBuilderParts = explode('\\', $filterBuilder);
            $filterRequestParts = explode('\\', $filterRequest);
            $filterBuilderImport = "\nuse {$filterBuilder};";
            $filterRequestImport = "\nuse {$filterRequest};";
            $filterBuilderClass = end($filterBuilderParts);
            $filterRequestClass = end($filterRequestParts);
        }
    }

    /**
     * Generate Scramble PHPDoc comments for controller class.
     *
     * @param string $modelName Model name
     * @param string $modelVariable Model variable name (camelCase)
     * @return array<string, string> Array with classDocComment (PHPDoc for class)
     */
    private function generateScrambleAttributes(string $modelName, string $modelVariable): array
    {
        $modelPlural = Str::plural($modelName);
        $classDocComment = "    /**\n     * {$modelName} API Controller\n     *\n     * Handles CRUD operations for {$modelPlural}.\n     */";

        return [
            'useStatements' => '',
            'classAttribute' => '',
            'classDocComment' => $classDocComment,
            'indexAttribute' => '',
            'storeAttribute' => '',
            'showAttribute' => '',
            'updateAttribute' => '',
            'destroyAttribute' => '',
        ];
    }

    /**
     * Generate soft delete methods (restore and forceDelete) for controllers.
     *
     * @param bool $hasSoftDeletes Whether the model uses soft deletes
     * @param string $modelName Model name
     * @param string $modelVariable Model variable name (camelCase)
     * @param string $resourceClass Resource class name
     * @param bool $useResponseMessages Whether to use ResponseMessages enum
     * @return string Generated methods code
     */
    private function generateSoftDeleteMethods(bool $hasSoftDeletes, string $modelName, string $modelVariable, string $returnClass, bool $useResponseMessages = false, bool $isSpatieData = false): string
    {
        if (!$hasSoftDeletes) {
            return '';
        }

        $model = $this->getFullModelNamespace(['modelName' => $modelName]);
        
        // Determine return type and return statement based on pattern
        if ($isSpatieData) {
            $restoreReturn = "{$returnClass}::from(\${$modelVariable})";
            $restoreMessage = $useResponseMessages 
                ? "->additional(['message' => ResponseMessages::UPDATED->message()])"
                : '';
        } else {
            $restoreReturn = "new {$returnClass}(\${$modelVariable})";
            $restoreMessage = $useResponseMessages 
                ? "->additional(['message' => ResponseMessages::UPDATED->message()])"
                : '';
        }
        
        $forceDeleteMessage = $useResponseMessages
            ? "ResponseMessages::DELETED->message()"
            : "'Permanently deleted successfully'";
        
        return "\n\n    /**\n     * Restore a soft-deleted {$modelVariable}.\n     *\n     * @param int \$id\n     * @return {$returnClass}|\\Illuminate\\Http\\JsonResponse\n     */\n    public function restore(int \$id): {$returnClass}|\\Illuminate\\Http\\JsonResponse\n    {\n        try {\n            \${$modelVariable} = {$model}::withTrashed()->findOrFail(\$id);\n            \${$modelVariable}->restore();\n            " . ($isSpatieData ? "return {$restoreReturn}{$restoreMessage};" : "return ({$restoreReturn}){$restoreMessage};") . "\n        } catch (\\Exception \$exception) {\n            report(\$exception);\n            return response()->json(['error' => 'There is an error.'], \\Symfony\\Component\\HttpFoundation\\Response::HTTP_INTERNAL_SERVER_ERROR);\n        }\n    }\n\n    /**\n     * Permanently delete a {$modelVariable}.\n     *\n     * @param int \$id\n     * @return \\Illuminate\\Http\\JsonResponse\n     */\n    public function forceDelete(int \$id): \\Illuminate\\Http\\JsonResponse\n    {\n        try {\n            \${$modelVariable} = {$model}::withTrashed()->findOrFail(\$id);\n            \${$modelVariable}->forceDelete();\n            return response()->json(['message' => {$forceDeleteMessage}], \\Symfony\\Component\\HttpFoundation\\Response::HTTP_OK);\n        } catch (\\Exception \$exception) {\n            report(\$exception);\n            return response()->json(['error' => 'There is an error.'], \\Symfony\\Component\\HttpFoundation\\Response::HTTP_INTERNAL_SERVER_ERROR);\n        }\n    }";
    }

    /**
     * Generate bulk methods (bulkStore, bulkUpdate, bulkDestroy) based on selected bulk endpoints.
     *
     * @param array $bulkRequests Array of bulk request classes with keys 'bulkStore', 'bulkUpdate', 'bulkDelete'
     * @param string $modelName Model name
     * @param string $modelVariable Model variable name (camelCase)
     * @param string $resourceClass Resource class name
     * @param bool $useResponseMessages Whether to use response messages
     * @param bool $isSpatieData Whether using Spatie Data pattern
     * @param bool $isWeb Whether this is a web controller
     * @return string Generated bulk methods code
     */
    private function generateBulkMethods(array $bulkRequests, string $modelName, string $modelVariable, string $resourceClass, bool $useResponseMessages = false, bool $isSpatieData = false, bool $isWeb = false): string
    {
        if (empty($bulkRequests)) {
            return '';
        }

        $model = $this->getFullModelNamespace(['modelName' => $modelName]);
        $methods = '';

        $routeName = HelperService::toSnakeCase(Str::plural($modelName));
        
        // Bulk Store
        if (isset($bulkRequests['bulkStore'])) {
            $bulkStoreRequest = explode('\\', $bulkRequests['bulkStore']);
            $bulkStoreRequestClass = end($bulkStoreRequest);
            
            if ($isWeb) {
                $methods .= "\n\n    /**\n     * Create multiple {$modelVariable}s.\n     *\n     * @param {$bulkStoreRequestClass} \$request\n     * @return \\Illuminate\\Http\\RedirectResponse\n     */\n    public function bulkStore({$bulkStoreRequestClass} \$request): \\Illuminate\\Http\\RedirectResponse\n    {\n        try {\n            \$items = \$request->validated()['items'];\n            \n            foreach (\$items as \$item) {\n                {$model}::create(\$item);\n            }\n            \n            return redirect()->route('{$routeName}.index')->with('success', 'Bulk created successfully');\n        } catch (\\Exception \$exception) {\n            report(\$exception);\n            return redirect()->back()->with('error', 'There is an error.')->withInput();\n        }\n    }";
            } else {
                $createMessage = $useResponseMessages
                    ? "->additional(['message' => ResponseMessages::CREATED->message()])"
                    : '';
                $returnType = $resourceClass ? "\\Illuminate\\Http\\Resources\\Json\\AnonymousResourceCollection|\\Illuminate\\Http\\JsonResponse" : "\\Illuminate\\Http\\JsonResponse";
                $messageText = $useResponseMessages ? 'ResponseMessages::CREATED->message()' : "'Created successfully'";
                $returnStatement = $resourceClass ? "return {$resourceClass}::collection(collect(\${$modelVariable}s)){$createMessage};" : "return response()->json(['data' => \${$modelVariable}s, 'message' => {$messageText}], \\Symfony\\Component\\HttpFoundation\\Response::HTTP_CREATED);";
                
                $methods .= "\n\n    /**\n     * Create multiple {$modelVariable}s.\n     *\n     * @param {$bulkStoreRequestClass} \$request\n     * @return {$returnType}\n     */\n    public function bulkStore({$bulkStoreRequestClass} \$request): {$returnType}\n    {\n        try {\n            \$items = \$request->validated()['items'];\n            \${$modelVariable}s = [];\n            \n            foreach (\$items as \$item) {\n                \${$modelVariable}s[] = {$model}::create(\$item);\n            }\n            \n            {$returnStatement}\n        } catch (\\Exception \$exception) {\n            report(\$exception);\n            return response()->json(['error' => 'There is an error.'], \\Symfony\\Component\\HttpFoundation\\Response::HTTP_INTERNAL_SERVER_ERROR);\n        }\n    }";
            }
        }

        // Bulk Update
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

        // Bulk Destroy
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

    private function generateIndexMethodBody(
        string $modelClass,
        string $modelVariable,
        string $resourceClass,
        string $additionalMessage,
        ?string $filterBuilder,
        ?string $filterRequest,
        bool $noPagination,
        string &$filterBuilderImport,
        string &$filterRequestImport,
        string &$filterBuilderClass,
        string &$filterRequestClass
    ): string {
        $this->prepareFilterImports($filterBuilder, $filterRequest, $filterBuilderImport, $filterRequestImport, $filterBuilderClass, $filterRequestClass);

        if ($filterBuilder && $filterRequest) {
            if ($noPagination) {
                return "        \${$modelVariable}s = {$modelClass}::getQuery()->get();\n\n        return {$resourceClass}::collection(\${$modelVariable}s){$additionalMessage};";
            }

            return "        \${$modelVariable}s = {$modelClass}::getQuery()\n            ->paginate(\$request->input('per_page', \$request->input('perPage', 20)));\n\n        return {$resourceClass}::collection(\${$modelVariable}s){$additionalMessage};";
        }

        if ($noPagination) {
            return "        return {$resourceClass}::collection({$modelClass}::all()){$additionalMessage};";
        }

        $defaultPagination = config('laravel_auto_crud.default_pagination', 20);
        return "        return {$resourceClass}::collection({$modelClass}::latest()->paginate({$defaultPagination})){$additionalMessage};";
    }

    private function modelHasMedia(string $modelClass): bool
    {
        try {
            if (! class_exists($modelClass)) {
                return false;
            }

            $reflection = new ReflectionClass($modelClass);
            $traits = $reflection->getTraitNames();

            foreach ($traits as $trait) {
                if (str_contains($trait, 'InteractsWithMedia') ||
                    str_contains($trait, 'HasMediaConversions') ||
                    str_contains($trait, 'HasMedia')) {
                    return true;
                }
            }

            return false;
        } catch (\Throwable) {
            return false;
        }
    }

    private function getLoadRelations(string $modelClass, bool $includeBelongsTo = true): string
    {
        $loadRelations = [];
        
        // Always load media if model has media
        if ($this->modelHasMedia($modelClass)) {
            $loadRelations[] = 'media';
        }
        
        // Load relationships
        $relationships = RelationshipDetector::detectRelationships($modelClass);
        $eagerLoadRelations = RelationshipDetector::getEagerLoadRelationships($relationships, $includeBelongsTo);
        
        $loadRelations = array_merge($loadRelations, $eagerLoadRelations);
        
        // Remove duplicates
        $loadRelations = array_unique($loadRelations);
        
        if (empty($loadRelations)) {
            return '';
        }
        
        $relationsList = "'" . implode("', '", $loadRelations) . "'";
        return "->load({$relationsList})";
    }

    /**
     * Get belongsTo relationships eager load string for show endpoint
     */
    private function getBelongsToLoadRelations(string $modelClass): string
    {
        $relationships = RelationshipDetector::detectRelationships($modelClass);
        $belongsToRelations = RelationshipDetector::getBelongsToRelationships($relationships);
        
        if (empty($belongsToRelations)) {
            // Still check for media
            if ($this->modelHasMedia($modelClass)) {
                return "->load('media')";
            }
            return '';
        }
        
        $loadRelations = [];
        
        // Always load media if model has media
        if ($this->modelHasMedia($modelClass)) {
            $loadRelations[] = 'media';
        }
        
        // Add belongsTo relationships
        foreach ($belongsToRelations as $relationship) {
            $loadRelations[] = $relationship['name'];
        }
        
        // Remove duplicates
        $loadRelations = array_unique($loadRelations);
        
        $relationsList = "'" . implode("', '", $loadRelations) . "'";
        return "->load({$relationsList})";
    }
}
