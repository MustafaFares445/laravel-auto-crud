<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Builders;

use Illuminate\Support\Str;
use Mrmarchone\LaravelAutoCrud\Services\HelperService;
use ReflectionClass;

class ControllerBuilder extends BaseBuilder
{
    public function createAPI(array $modelData, string $resource, string $request, array $options = []): string
    {
        $filterBuilder = $options['filterBuilder'] ?? null;
        $filterRequest = $options['filterRequest'] ?? null;
        $overwrite = $options['overwrite'] ?? false;
        $useResponseMessages = $options['response-messages'] ?? false;
        $noPagination = $options['no-pagination'] ?? false;

        $stubName = $useResponseMessages ? 'api_messages.controller' : 'api.controller';

        return $this->fileService->createFromStub($modelData, $stubName, 'Http/Controllers/API', 'Controller', $overwrite, function ($modelData) use ($resource, $request, $filterBuilder, $filterRequest, $useResponseMessages, $noPagination) {
            $model = $this->getFullModelNamespace($modelData);
            $resourceName = explode('\\', $resource);
            $requestName = explode('\\', $request);

            $filterBuilderImport = '';
            $filterRequestImport = '';
            $filterBuilderClass = 'Request';
            $filterRequestClass = 'Request';
            $modelVariable = lcfirst($modelData['modelName']);
            $resourceClass = end($resourceName);
            $additionalMessage = $useResponseMessages ? "\n            ->additional(['message' => __(ResponseMessages::RETRIEVED->value)])" : '';

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

    public function createAPISpatieData(array $modelData, string $spatieData, string $resource, array $options = []): string
    {
        $filterBuilder = $options['filterBuilder'] ?? null;
        $filterRequest = $options['filterRequest'] ?? null;
        $overwrite = $options['overwrite'] ?? false;
        $useResponseMessages = $options['response-messages'] ?? false;
        $noPagination = $options['no-pagination'] ?? false;

        $stubName = $useResponseMessages ? 'api_spatie_data_messages.controller' : 'api_spatie_data.controller';

        return $this->fileService->createFromStub($modelData, $stubName, 'Http/Controllers/API', 'Controller', $overwrite, function ($modelData) use ($spatieData, $resource, $filterBuilder, $filterRequest, $useResponseMessages, $noPagination) {
            $model = $this->getFullModelNamespace($modelData);
            $spatieDataName = explode('\\', $spatieData);
            $resourceName = explode('\\', $resource);

            $filterBuilderImport = '';
            $filterRequestImport = '';
            $filterBuilderClass = 'Request';
            $filterRequestClass = 'Request';
            $modelVariable = lcfirst($modelData['modelName']);
            $resourceClass = end($resourceName);
            $additionalMessage = $useResponseMessages ? "\n            ->additional(['message' => __(ResponseMessages::RETRIEVED->value)])" : '';

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
            ];
        });
    }

    public function createAPIRepository(array $modelData, string $resource, string $request, string $service, array $options = []): string
    {
        $overwrite = $options['overwrite'] ?? false;
        $useResponseMessages = $options['response-messages'] ?? false;

        $stubName = $useResponseMessages ? 'api_repository_messages.controller' : 'api_repository.controller';

        return $this->fileService->createFromStub($modelData, $stubName, 'Http/Controllers/API', 'Controller', $overwrite, function ($modelData) use ($resource, $request, $service) {
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

    public function createAPIServiceSpatieData(array $modelData, string $spatieData, string $request, string $service, string $resource, array $options = []): string
    {
        $filterBuilder = $options['filterBuilder'] ?? null;
        $filterRequest = $options['filterRequest'] ?? null;
        $overwrite = $options['overwrite'] ?? false;
        $useResponseMessages = $options['response-messages'] ?? false;
        $noPagination = $options['no-pagination'] ?? false;

        $stubName = $useResponseMessages ? 'api_service_spatie_data_messages.controller' : 'api_service_spatie_data.controller';

        return $this->fileService->createFromStub($modelData, $stubName, 'Http/Controllers/API', 'Controller', $overwrite, function ($modelData) use ($spatieData, $request, $service, $resource, $filterBuilder, $filterRequest, $useResponseMessages, $noPagination) {
            $model = $this->getFullModelNamespace($modelData);
            $spatieDataName = explode('\\', $spatieData);
            $requestName = explode('\\', $request);
            $serviceName = explode('\\', $service);
            $resourceName = explode('\\', $resource);

            $filterBuilderImport = '';
            $filterRequestImport = '';
            $filterBuilderClass = 'Request';
            $filterRequestClass = 'Request';
            $modelVariable = lcfirst($modelData['modelName']);
            $resourceClass = end($resourceName);
            $additionalMessage = $useResponseMessages ? "\n            ->additional(['message' => __(ResponseMessages::RETRIEVED->value)])" : '';
            $loadRelations = $this->getLoadRelations($model);

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
                '{{ loadRelations }}' => $loadRelations,
            ];
        });
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
        if ($filterBuilder && $filterRequest) {
            $filterBuilderParts = explode('\\', $filterBuilder);
            $filterRequestParts = explode('\\', $filterRequest);
            $filterBuilderImport = "\nuse {$filterBuilder};";
            $filterRequestImport = "\nuse {$filterRequest};";
            $filterBuilderClass = end($filterBuilderParts);
            $filterRequestClass = end($filterRequestParts);

            if ($noPagination) {
                return "        \${$modelVariable}s = {$modelClass}::getQuery()->get();\n\n        return {$resourceClass}::collection(\${$modelVariable}s){$additionalMessage};";
            }

            return "        \${$modelVariable}s = {$modelClass}::getQuery()\n            ->paginate(\$request->get('per_page', 20));\n\n        return {$resourceClass}::collection(\${$modelVariable}s){$additionalMessage};";
        }

        if ($noPagination) {
            return "        return {$resourceClass}::collection({$modelClass}::all()){$additionalMessage};";
        }

        return "        return {$resourceClass}::collection({$modelClass}::latest()->paginate(10)){$additionalMessage};";
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

    private function getLoadRelations(string $modelClass): string
    {
        if ($this->modelHasMedia($modelClass)) {
            return "->load('media')";
        }

        return '';
    }
}
