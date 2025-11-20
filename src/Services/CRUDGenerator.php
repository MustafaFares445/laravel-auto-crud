<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Services;

use InvalidArgumentException;
use Mrmarchone\LaravelAutoCrud\Builders\ControllerBuilder;
use Mrmarchone\LaravelAutoCrud\Builders\FilterBuilderBuilder;
use Mrmarchone\LaravelAutoCrud\Builders\RepositoryBuilder;
use Mrmarchone\LaravelAutoCrud\Builders\RequestBuilder;
use Mrmarchone\LaravelAutoCrud\Builders\ResourceBuilder;
use Mrmarchone\LaravelAutoCrud\Builders\RouteBuilder;
use Mrmarchone\LaravelAutoCrud\Builders\ServiceBuilder;
use Mrmarchone\LaravelAutoCrud\Builders\SpatieDataBuilder;
use Mrmarchone\LaravelAutoCrud\Builders\ViewBuilder;

use function Laravel\Prompts\info;

class CRUDGenerator
{
    public function __construct(private ControllerBuilder $controllerBuilder,
                                private ResourceBuilder $resourceBuilder,
                                private RequestBuilder $requestBuilder,
                                private RouteBuilder $routeBuilder,
                                private ViewBuilder $viewBuilder,
                                private ServiceBuilder $serviceBuilder,
                                private SpatieDataBuilder $spatieDataBuilder,
                                private FilterBuilderBuilder $filterBuilderBuilder)
    {
        $this->controllerBuilder = new ControllerBuilder;
        $this->resourceBuilder = new ResourceBuilder;
        $this->requestBuilder = new RequestBuilder;
        $this->routeBuilder = new RouteBuilder;
        $this->viewBuilder = new ViewBuilder;
        $this->serviceBuilder = new ServiceBuilder;
        $this->spatieDataBuilder = new SpatieDataBuilder;
        $this->filterBuilderBuilder = new FilterBuilderBuilder;
    }

    public function generate($modelData, array $options): void
    {
        $checkForType = $options['type'];

        $requestName = $spatieDataName = $service = null;

        if ($options['pattern'] === 'spatie-data') {
            $spatieDataName = $this->spatieDataBuilder->create($modelData, $options['overwrite']);

            // If using service with spatie-data, also create FormRequest
            if ($options['service'] ?? false) {
                $requestName = $this->requestBuilder->createForSpatieData($modelData, $options['overwrite']);
            }
        } else {
            $requestName = $this->requestBuilder->create($modelData, $options['overwrite']);
        }

        if ($options['service'] ?? false) {
            $service = $this->serviceBuilder->createServiceOnly($modelData, $options['overwrite']);
        }

        $filterBuilder = $filterRequest = null;
        if ($options['filter'] ?? false) {
            $filterBuilder = $this->filterBuilderBuilder->create($modelData, $options['overwrite']);
            $filterRequest = $this->requestBuilder->createFilterRequest($modelData, $options['overwrite']);
        }

        $data = [
            'requestName' => $requestName ?? '',
            'service' => $service ?? '',
            'spatieData' => $spatieDataName ?? '',
            'filterBuilder' => $filterBuilder ?? '',
            'filterRequest' => $filterRequest ?? '',
        ];

        $controllerName = $this->generateController($checkForType, $modelData, $data, $options);
        $this->routeBuilder->create($modelData['modelName'], $controllerName, $checkForType);

        info('Auto CRUD files generated successfully for '.$modelData['modelName'].' Model');
    }

    private function generateController(array $types, array $modelData, array $data, array $options): string
    {
        $controllerName = null;

        if (in_array('api', $types, true)) {
            $controllerName = $this->generateAPIController($modelData, $data['requestName'], $data['service'], $options, $data['spatieData'], $data['filterBuilder'] ?? null, $data['filterRequest'] ?? null);
        }

        if (in_array('web', $types, true)) {
            $controllerName = $this->generateWebController($modelData, $data['requestName'], $data['service'], $options, $data['spatieData']);
        }

        if (! $controllerName) {
            throw new InvalidArgumentException('Unsupported controller type');
        }

        return $controllerName;
    }

    private function generateAPIController(array $modelData, string $requestName, string $service, array $options, ?string $spatieData = null, ?string $filterBuilder = null, ?string $filterRequest = null): string
    {
        $controllerName = null;

        // Always generate Resource for API controllers
        $resourceName = $this->resourceBuilder->create($modelData, $options['overwrite'], $options['pattern'] ?? 'normal', $spatieData);

        if ($options['pattern'] === 'spatie-data') {
            // If service with spatie-data, use FormRequest + Data class pattern
            if ($service && $requestName) {
                $controllerName = $this->controllerBuilder->createAPIServiceSpatieData($modelData, $spatieData, $requestName, $service, $resourceName, $filterBuilder, $filterRequest, $options['overwrite']);
            } else {
                $controllerName = $this->controllerBuilder->createAPISpatieData($modelData, $spatieData, $resourceName, $filterBuilder, $filterRequest, $options['overwrite']);
            }
        } elseif ($options['pattern'] === 'normal') {
            $controllerName = $this->controllerBuilder->createAPI($modelData, $resourceName, $requestName, $filterBuilder, $filterRequest, $options['overwrite']);
        }

        if (! $controllerName) {
            throw new InvalidArgumentException('Unsupported controller type');
        }

        return $controllerName;
    }

    private function generateWebController(array $modelData, string $requestName, string $service, array $options, string $spatieData = ''): string
    {
        $controllerName = null;

        if ($options['pattern'] === 'spatie-data') {
            $controllerName = $this->controllerBuilder->createWebSpatieData($modelData, $spatieData, $options['overwrite']);
        } elseif ($options['pattern'] === 'normal') {
            $controllerName = $this->controllerBuilder->createWeb($modelData, $requestName, $options['overwrite']);
        }

        $this->viewBuilder->create($modelData, $options['overwrite']);

        if (! $controllerName) {
            throw new InvalidArgumentException('Unsupported controller type');
        }

        return $controllerName;
    }
}

