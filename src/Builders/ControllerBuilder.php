<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Builders;

use Illuminate\Support\Str;
use Mrmarchone\LaravelAutoCrud\Services\HelperService;

class ControllerBuilder extends BaseBuilder
{
    public function createAPI(array $modelData, string $resource, string $request, ?string $filterBuilder = null, ?string $filterRequest = null, bool $overwrite = false): string
    {
        return $this->fileService->createFromStub($modelData, 'api.controller', 'Http/Controllers/API', 'Controller', $overwrite, function ($modelData) use ($resource, $request, $filterBuilder, $filterRequest) {
            $model = $this->getFullModelNamespace($modelData);
            $resourceName = explode('\\', $resource);
            $requestName = explode('\\', $request);

            $filterBuilderImport = '';
            $filterRequestImport = '';
            $filterBuilderClass = 'Request';
            $filterRequestClass = 'Request';
            $indexMethodBody = '';
            $modelVariable = lcfirst($modelData['modelName']);
            $resourceClass = end($resourceName);

            if ($filterBuilder && $filterRequest) {
                $filterBuilderParts = explode('\\', $filterBuilder);
                $filterRequestParts = explode('\\', $filterRequest);
                $filterBuilderImport = "\nuse {$filterBuilder};";
                $filterRequestImport = "\nuse {$filterRequest};";
                $filterBuilderClass = end($filterBuilderParts);
                $filterRequestClass = end($filterRequestParts);
                $indexMethodBody = "        \${$modelVariable}s = {$filterBuilderClass}::make()\n            ->applyFilters(\$request)\n            ->paginate(\$request->input('perPage'));\n\n        return {$resourceClass}::collection(\${$modelVariable}s);";
            } else {
                $modelClass = $modelData['modelName'];
                $indexMethodBody = "        return {$resourceClass}::collection({$modelClass}::latest()->paginate(10));";
            }

            return [
                '{{ requestNamespace }}' => $request,
                '{{ resourceNamespace }}' => $resource,
                '{{ modelNamespace }}' => $model,
                '{{ resource }}' => $resourceClass,
                '{{ request }}' => end($requestName),
                '{{ model }}' => $modelData['modelName'],
                '{{ modelVariable }}' => $modelVariable,
                '{{ filterBuilderImport }}' => $filterBuilderImport,
                '{{ filterRequestImport }}' => $filterRequestImport,
                '{{ filterBuilder }}' => $filterBuilderClass,
                '{{ filterRequest }}' => $filterRequestClass,
                '{{ indexMethodBody }}' => $indexMethodBody,
            ];
        });
    }

    public function createAPISpatieData(array $modelData, string $spatieData, string $resource, ?string $filterBuilder = null, ?string $filterRequest = null, bool $overwrite = false): string
    {
        return $this->fileService->createFromStub($modelData, 'api_spatie_data.controller', 'Http/Controllers/API', 'Controller', $overwrite, function ($modelData) use ($spatieData, $resource, $filterBuilder, $filterRequest) {
            $model = $this->getFullModelNamespace($modelData);
            $spatieDataName = explode('\\', $spatieData);
            $resourceName = explode('\\', $resource);

            $filterBuilderImport = '';
            $filterRequestImport = '';
            $filterBuilderClass = 'Request';
            $filterRequestClass = 'Request';
            $indexMethodBody = '';
            $modelVariable = lcfirst($modelData['modelName']);
            $resourceClass = end($resourceName);

            if ($filterBuilder && $filterRequest) {
                $filterBuilderParts = explode('\\', $filterBuilder);
                $filterRequestParts = explode('\\', $filterRequest);
                $filterBuilderImport = "\nuse {$filterBuilder};";
                $filterRequestImport = "\nuse {$filterRequest};";
                $filterBuilderClass = end($filterBuilderParts);
                $filterRequestClass = end($filterRequestParts);
                $indexMethodBody = "        \${$modelVariable}s = {$filterBuilderClass}::make()\n            ->applyFilters(\$request)\n            ->paginate(\$request->input('perPage'));\n\n        return {$resourceClass}::collection(\${$modelVariable}s);";
            } else {
                $modelClass = $modelData['modelName'];
                $indexMethodBody = "        return {$resourceClass}::collection({$modelClass}::latest()->paginate(10));";
            }

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
            ];
        });
    }

    public function createAPIRepository(array $modelData, string $resource, string $request, string $service, bool $overwrite = false): string
    {
        return $this->fileService->createFromStub($modelData, 'api_repository.controller', 'Http/Controllers/API', 'Controller', $overwrite, function ($modelData) use ($resource, $request, $service) {
            $resourceName = explode('\\', $resource);
            $requestName = explode('\\', $request);
            $serviceName = explode('\\', $service);

            return [
                '{{ requestNamespace }}' => $request,
                '{{ resourceNamespace }}' => $resource,
                '{{ resource }}' => end($resourceName),
                '{{ request }}' => end($requestName),
                '{{ serviceNamespace }}' => $service,
                '{{ service }}' => end($serviceName),
                '{{ serviceVariable }}' => lcfirst(end($serviceName)),
            ];
        });
    }

    public function createAPIRepositorySpatieData(array $modelData, string $spatieData, string $service, bool $overwrite = false): string
    {
        return $this->fileService->createFromStub($modelData, 'api_repository_spatie_data.controller', 'Http/Controllers/API', 'Controller', $overwrite, function ($modelData) use ($spatieData, $service) {
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

    public function createWeb(array $modelData, string $request, bool $overwrite = false): string
    {
        return $this->fileService->createFromStub($modelData, 'web.controller', 'Http/Controllers', 'Controller', $overwrite, function ($modelData) use ($request) {
            $model = $this->getFullModelNamespace($modelData);
            $requestName = explode('\\', $request);

            return [
                '{{ requestNamespace }}' => $request,
                '{{ modelNamespace }}' => $model,
                '{{ request }}' => end($requestName),
                '{{ model }}' => $modelData['modelName'],
                '{{ modelVariable }}' => lcfirst($modelData['modelName']),
                '{{ viewPath }}' => HelperService::toSnakeCase(Str::plural($modelData['modelName'])),
                '{{ modelPlural }}' => HelperService::toSnakeCase(Str::plural($modelData['modelName'])),
                '{{ routeName }}' => HelperService::toSnakeCase(Str::plural($modelData['modelName'])),
            ];
        });
    }

    public function createWebRepository(array $modelData, string $request, string $service, bool $overwrite = false): string
    {
        return $this->fileService->createFromStub($modelData, 'web_repository.controller', 'Http/Controllers', 'Controller', $overwrite, function ($modelData) use ($service, $request) {
            $model = $this->getFullModelNamespace($modelData);
            $serviceName = explode('\\', $service);
            $requestName = explode('\\', $request);

            return [
                '{{ requestNamespace }}' => $request,
                '{{ request }}' => end($requestName),
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

    public function createWebSpatieData(array $modelData, string $spatieData, bool $overwrite = false): string
    {
        return $this->fileService->createFromStub($modelData, 'web_spatie_data.controller', 'Http/Controllers', 'Controller', $overwrite, function ($modelData) use ($spatieData) {
            $model = $this->getFullModelNamespace($modelData);
            $spatieDataName = explode('\\', $spatieData);

            return [
                '{{ spatieDataNamespace }}' => $spatieData,
                '{{ modelNamespace }}' => $model,
                '{{ spatieData }}' => end($spatieDataName),
                '{{ model }}' => $modelData['modelName'],
                '{{ modelVariable }}' => lcfirst($modelData['modelName']),
                '{{ viewPath }}' => HelperService::toSnakeCase(Str::plural($modelData['modelName'])),
                '{{ modelPlural }}' => HelperService::toSnakeCase(Str::plural($modelData['modelName'])),
                '{{ routeName }}' => HelperService::toSnakeCase(Str::plural($modelData['modelName'])),
            ];
        });
    }

    public function createWebRepositorySpatieData(array $modelData, string $spatieData, string $service, bool $overwrite = false): string
    {
        return $this->fileService->createFromStub($modelData, 'web_repository_spatie_data.controller', 'Http/Controllers', 'Controller', $overwrite, function ($modelData) use ($service, $spatieData) {
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

    public function createAPIServiceSpatieData(array $modelData, string $spatieData, string $request, string $service, string $resource, ?string $filterBuilder = null, ?string $filterRequest = null, bool $overwrite = false): string
    {
        return $this->fileService->createFromStub($modelData, 'api_service_spatie_data.controller', 'Http/Controllers/API', 'Controller', $overwrite, function ($modelData) use ($spatieData, $request, $service, $resource, $filterBuilder, $filterRequest) {
            $model = $this->getFullModelNamespace($modelData);
            $spatieDataName = explode('\\', $spatieData);
            $requestName = explode('\\', $request);
            $serviceName = explode('\\', $service);
            $resourceName = explode('\\', $resource);

            $filterBuilderImport = '';
            $filterRequestImport = '';
            $filterBuilderClass = 'Request';
            $filterRequestClass = 'Request';
            $indexMethodBody = '';
            $modelVariable = lcfirst($modelData['modelName']);
            $resourceClass = end($resourceName);

            if ($filterBuilder && $filterRequest) {
                $filterBuilderParts = explode('\\', $filterBuilder);
                $filterRequestParts = explode('\\', $filterRequest);
                $filterBuilderImport = "\nuse {$filterBuilder};";
                $filterRequestImport = "\nuse {$filterRequest};";
                $filterBuilderClass = end($filterBuilderParts);
                $filterRequestClass = end($filterRequestParts);
                $indexMethodBody = "        \${$modelVariable}s = {$filterBuilderClass}::make()\n            ->applyFilters(\$request)\n            ->paginate(\$request->input('perPage'));\n\n        return {$resourceClass}::collection(\${$modelVariable}s);";
            } else {
                $modelClass = $modelData['modelName'];
                $indexMethodBody = "        return {$resourceClass}::collection({$modelClass}::latest()->paginate(10));";
            }

            return [
                '{{ spatieDataNamespace }}' => $spatieData,
                '{{ spatieData }}' => end($spatieDataName),
                '{{ requestNamespace }}' => $request,
                '{{ request }}' => end($requestName),
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
            ];
        });
    }
}

