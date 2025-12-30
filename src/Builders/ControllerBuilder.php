<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Builders;

use Illuminate\Support\Str;
use Mrmarchone\LaravelAutoCrud\Services\HelperService;
use Mrmarchone\LaravelAutoCrud\Services\RelationshipDetector;
use ReflectionClass;

class ControllerBuilder extends BaseBuilder
{
    public function createAPI(array $modelData, string $resource, array $requests, array $options = []): string
    {
        $filterBuilder = $options['filterBuilder'] ?? null;
        $filterRequest = $options['filterRequest'] ?? null;
        $overwrite = $options['overwrite'] ?? false;
        $useResponseMessages = $options['response-messages'] ?? false;
        $noPagination = $options['no-pagination'] ?? false;
        $controllerFolder = $options['controller-folder'] ?? config('laravel_auto_crud.default_api_controller_folder', 'Http/Controllers/API');

        $stubName = $useResponseMessages ? 'api_messages.controller' : 'api.controller';

        return $this->fileService->createFromStub($modelData, $stubName, $controllerFolder, 'Controller', $overwrite, function ($modelData) use ($resource, $requests, $filterBuilder, $filterRequest, $useResponseMessages, $noPagination) {
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

            return [
                '{{ storeRequestNamespace }}' => $requests['store'],
                '{{ updateRequestNamespace }}' => $requests['update'],
                '{{ resourceNamespace }}' => $resource,
                '{{ modelNamespace }}' => $model,
                '{{ resource }}' => $resourceClass,
                '{{ storeRequest }}' => end($storeRequestName),
                '{{ updateRequest }}' => end($updateRequestName),
                '{{ model }}' => $modelData['modelName'],
                '{{ modelVariable }}' => $modelVariable,
                '{{ filterBuilderImport }}' => $filterBuilderImport,
                '{{ filterRequestImport }}' => $filterRequestImport,
                '{{ filterBuilder }}' => $filterBuilderClass,
                '{{ filterRequest }}' => $filterRequestClass,
                '{{ indexMethodBody }}' => $indexMethodBody,
                '{{ belongsToLoadRelations }}' => $belongsToLoadRelations,
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

        return $this->fileService->createFromStub($modelData, $stubName, $controllerFolder, 'Controller', $overwrite, function ($modelData) use ($spatieData, $resource, $filterBuilder, $filterRequest, $useResponseMessages, $noPagination) {
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

            return [
                '{{ spatieDataNamespace }}' => $spatieData,
                '{{ modelNamespace }}' => $model,
                '{{ spatieData }}' => end($spatieDataName),
                '{{ resourceNamespace }}' => $resource,
                '{{ resource }}' => $resourceClass,
                '{{ model }}' => $modelData['modelName'],
                '{{ modelVariable }}' => $modelVariable,
                '{{ filterBuilderImport }}' => $filterBuilderImport,
                '{{ filterRequestImport }}' => $filterRequestImport,
                '{{ filterBuilder }}' => $filterBuilderClass,
                '{{ filterRequest }}' => $filterRequestClass,
                '{{ indexMethodBody }}' => $indexMethodBody,
                '{{ belongsToLoadRelations }}' => $belongsToLoadRelations,
            ];
        });
    }

    public function createAPIRepository(array $modelData, string $resource, array $requests, string $service, array $options = []): string
    {
        $overwrite = $options['overwrite'] ?? false;
        $useResponseMessages = $options['response-messages'] ?? false;
        $controllerFolder = $options['controller-folder'] ?? config('laravel_auto_crud.default_api_controller_folder', 'Http/Controllers/API');

        $stubName = $useResponseMessages ? 'api_repository_messages.controller' : 'api_repository.controller';

        return $this->fileService->createFromStub($modelData, $stubName, $controllerFolder, 'Controller', $overwrite, function ($modelData) use ($resource, $requests, $service) {
            $resourceName = explode('\\', $resource);
            $storeRequestName = explode('\\', $requests['store']);
            $updateRequestName = explode('\\', $requests['update']);
            $serviceName = explode('\\', $service);

            return [
                '{{ storeRequestNamespace }}' => $requests['store'],
                '{{ updateRequestNamespace }}' => $requests['update'],
                '{{ resourceNamespace }}' => $resource,
                '{{ resource }}' => end($resourceName),
                '{{ storeRequest }}' => end($storeRequestName),
                '{{ updateRequest }}' => end($updateRequestName),
                '{{ serviceNamespace }}' => $service,
                '{{ service }}' => end($serviceName),
                '{{ serviceVariable }}' => lcfirst(end($serviceName)),
            ];
        });
    }

    public function createAPIRepositorySpatieData(array $modelData, string $spatieData, string $service, bool $overwrite = false, ?string $controllerFolder = null): string
    {
        $controllerFolder = $controllerFolder ?? config('laravel_auto_crud.default_api_controller_folder', 'Http/Controllers/API');
        return $this->fileService->createFromStub($modelData, 'api_repository_spatie_data.controller', $controllerFolder, 'Controller', $overwrite, function ($modelData) use ($spatieData, $service) {
            $spatieDataName = explode('\\', $spatieData);
            $serviceName = explode('\\', $service);

            return [
                '{{ spatieDataNamespace }}' => $spatieData,
                '{{ spatieData }}' => end($spatieDataName),
                '{{ serviceNamespace }}' => $service,
                '{{ service }}' => end($serviceName),
                '{{ serviceVariable }}' => lcfirst(end($serviceName)),
            ];
        });
    }

    public function createWeb(array $modelData, array $requests, bool $overwrite = false, ?string $controllerFolder = null): string
    {
        $controllerFolder = $controllerFolder ?? config('laravel_auto_crud.default_web_controller_folder', 'Http/Controllers');
        return $this->fileService->createFromStub($modelData, 'web.controller', $controllerFolder, 'Controller', $overwrite, function ($modelData) use ($requests) {
            $model = $this->getFullModelNamespace($modelData);
            $storeRequestName = explode('\\', $requests['store']);
            $updateRequestName = explode('\\', $requests['update']);
            $belongsToLoadRelations = $this->getBelongsToLoadRelations($model);

            return [
                '{{ storeRequestNamespace }}' => $requests['store'],
                '{{ updateRequestNamespace }}' => $requests['update'],
                '{{ modelNamespace }}' => $model,
                '{{ storeRequest }}' => end($storeRequestName),
                '{{ updateRequest }}' => end($updateRequestName),
                '{{ model }}' => $modelData['modelName'],
                '{{ modelVariable }}' => lcfirst($modelData['modelName']),
                '{{ viewPath }}' => HelperService::toSnakeCase(Str::plural($modelData['modelName'])),
                '{{ modelPlural }}' => HelperService::toSnakeCase(Str::plural($modelData['modelName'])),
                '{{ routeName }}' => HelperService::toSnakeCase(Str::plural($modelData['modelName'])),
                '{{ belongsToLoadRelations }}' => $belongsToLoadRelations,
            ];
        });
    }

    public function createWebRepository(array $modelData, array $requests, string $service, bool $overwrite = false, ?string $controllerFolder = null): string
    {
        $controllerFolder = $controllerFolder ?? config('laravel_auto_crud.default_web_controller_folder', 'Http/Controllers');
        return $this->fileService->createFromStub($modelData, 'web_repository.controller', $controllerFolder, 'Controller', $overwrite, function ($modelData) use ($service, $requests) {
            $model = $this->getFullModelNamespace($modelData);
            $serviceName = explode('\\', $service);
            $storeRequestName = explode('\\', $requests['store']);
            $updateRequestName = explode('\\', $requests['update']);

            return [
                '{{ storeRequestNamespace }}' => $requests['store'],
                '{{ updateRequestNamespace }}' => $requests['update'],
                '{{ storeRequest }}' => end($storeRequestName),
                '{{ updateRequest }}' => end($updateRequestName),
                '{{ serviceNamespace }}' => $service,
                '{{ service }}' => end($serviceName),
                '{{ serviceVariable }}' => lcfirst(end($serviceName)),
                '{{ modelNamespace }}' => $model,
                '{{ model }}' => $modelData['modelName'],
                '{{ modelVariable }}' => lcfirst($modelData['modelName']),
                '{{ viewPath }}' => HelperService::toSnakeCase(Str::plural($modelData['modelName'])),
                '{{ modelPlural }}' => HelperService::toSnakeCase(Str::plural($modelData['modelName'])),
                '{{ routeName }}' => HelperService::toSnakeCase(Str::plural($modelData['modelName'])),
            ];
        });
    }

    public function createWebSpatieData(array $modelData, string $spatieData, bool $overwrite = false, ?string $controllerFolder = null): string
    {
        $controllerFolder = $controllerFolder ?? config('laravel_auto_crud.default_web_controller_folder', 'Http/Controllers');
        return $this->fileService->createFromStub($modelData, 'web_spatie_data.controller', $controllerFolder, 'Controller', $overwrite, function ($modelData) use ($spatieData) {
            $model = $this->getFullModelNamespace($modelData);
            $spatieDataName = explode('\\', $spatieData);
            $belongsToLoadRelations = $this->getBelongsToLoadRelations($model);

            return [
                '{{ spatieDataNamespace }}' => $spatieData,
                '{{ modelNamespace }}' => $model,
                '{{ spatieData }}' => end($spatieDataName),
                '{{ model }}' => $modelData['modelName'],
                '{{ modelVariable }}' => lcfirst($modelData['modelName']),
                '{{ viewPath }}' => HelperService::toSnakeCase(Str::plural($modelData['modelName'])),
                '{{ modelPlural }}' => HelperService::toSnakeCase(Str::plural($modelData['modelName'])),
                '{{ routeName }}' => HelperService::toSnakeCase(Str::plural($modelData['modelName'])),
                '{{ belongsToLoadRelations }}' => $belongsToLoadRelations,
            ];
        });
    }

    public function createWebRepositorySpatieData(array $modelData, string $spatieData, string $service, bool $overwrite = false, ?string $controllerFolder = null): string
    {
        $controllerFolder = $controllerFolder ?? config('laravel_auto_crud.default_web_controller_folder', 'Http/Controllers');
        return $this->fileService->createFromStub($modelData, 'web_repository_spatie_data.controller', $controllerFolder, 'Controller', $overwrite, function ($modelData) use ($service, $spatieData) {
            $model = $this->getFullModelNamespace($modelData);
            $serviceName = explode('\\', $service);
            $spatieDataName = explode('\\', $spatieData);

            return [
                '{{ spatieDataNamespace }}' => $spatieData,
                '{{ spatieData }}' => end($spatieDataName),
                '{{ serviceNamespace }}' => $service,
                '{{ service }}' => end($serviceName),
                '{{ serviceVariable }}' => lcfirst(end($serviceName)),
                '{{ modelNamespace }}' => $model,
                '{{ model }}' => $modelData['modelName'],
                '{{ modelVariable }}' => lcfirst($modelData['modelName']),
                '{{ viewPath }}' => HelperService::toSnakeCase(Str::plural($modelData['modelName'])),
                '{{ modelPlural }}' => HelperService::toSnakeCase(Str::plural($modelData['modelName'])),
                '{{ routeName }}' => HelperService::toSnakeCase(Str::plural($modelData['modelName'])),
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
        $controllerFolder = $options['controller-folder'] ?? config('laravel_auto_crud.default_api_controller_folder', 'Http/Controllers/API');

        $stubName = $useResponseMessages ? 'api_service_spatie_data_messages.controller' : 'api_service_spatie_data.controller';

        return $this->fileService->createFromStub($modelData, $stubName, $controllerFolder, 'Controller', $overwrite, function ($modelData) use ($spatieData, $requests, $service, $resource, $filterBuilder, $filterRequest, $useResponseMessages, $noPagination) {
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
                '{{ filterBuilderImport }}' => $filterBuilderImport,
                '{{ filterRequestImport }}' => $filterRequestImport,
                '{{ filterBuilder }}' => $filterBuilderClass,
                '{{ filterRequest }}' => $filterRequestClass,
                '{{ indexMethodBody }}' => $indexMethodBody,
                '{{ loadRelations }}' => $loadRelations,
                '{{ belongsToLoadRelations }}' => $belongsToLoadRelations,
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
     * Generate the index method body for API controllers.
     *
     * @param string $modelClass Model class name
     * @param string $modelVariable Model variable name
     * @param string $resourceClass Resource class name
     * @param string $additionalMessage Additional message for response
     * @param string|null $filterBuilder Filter builder class name
     * @param string|null $filterRequest Filter request class name
     * @param bool $noPagination Whether to use pagination
     * @param string &$filterBuilderImport Output parameter for import statement
     * @param string &$filterRequestImport Output parameter for import statement
     * @param string &$filterBuilderClass Output parameter for class name
     * @param string &$filterRequestClass Output parameter for class name
     * @return string Generated method body
     */
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

            return "        \${$modelVariable}s = {$modelClass}::getQuery()\n            ->paginate(\$request->get('perPage', 20));\n\n        return {$resourceClass}::collection(\${$modelVariable}s){$additionalMessage};";
        }

        if ($noPagination) {
            return "        return {$resourceClass}::collection({$modelClass}::all()){$additionalMessage};";
        }

        $defaultPagination = config('laravel_auto_crud.default_pagination', 10);
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
        
        $relationsList = "'" . implode("', '", $loadRelations) . "'";
        return "->load({$relationsList})";
    }
}
