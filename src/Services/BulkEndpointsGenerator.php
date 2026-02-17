<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Services;

use Mrmarchone\LaravelAutoCrud\Builders\BulkControllerBuilder;
use Mrmarchone\LaravelAutoCrud\Builders\PestBuilder;
use Mrmarchone\LaravelAutoCrud\Builders\RequestBuilder;
use Mrmarchone\LaravelAutoCrud\Builders\ResourceBuilder;
use Mrmarchone\LaravelAutoCrud\Builders\RouteBuilder;
use Mrmarchone\LaravelAutoCrud\Builders\ServiceBuilder;

use function Laravel\Prompts\info;

class BulkEndpointsGenerator
{
    private BulkControllerBuilder $bulkControllerBuilder;
    private ServiceBuilder $serviceBuilder;
    private ResourceBuilder $resourceBuilder;
    private RequestBuilder $requestBuilder;
    private RouteBuilder $routeBuilder;
    private PestBuilder $pestBuilder;

    public function __construct(
        private FileService $fileService,
        private ModelService $modelService,
        private TableColumnsService $tableColumnsService,
    ) {
        $this->initializeBuilders();
    }

    private function initializeBuilders(): void
    {
        $this->bulkControllerBuilder = new BulkControllerBuilder($this->fileService);
        $this->serviceBuilder = new ServiceBuilder($this->fileService);
        $this->resourceBuilder = new ResourceBuilder($this->fileService, $this->modelService, $this->tableColumnsService);
        $this->requestBuilder = new RequestBuilder($this->fileService, $this->tableColumnsService, $this->modelService, new \Mrmarchone\LaravelAutoCrud\Builders\EnumBuilder($this->fileService));
        $this->routeBuilder = new RouteBuilder();
        $this->pestBuilder = new PestBuilder($this->fileService, $this->tableColumnsService);
    }

    public function generate(array $modelData, array $options, ?string $module = null): void
    {
        $controllerTypes = $options['type'] ?? ['api'];
        $bulkEndpoints = $options['bulk'] ?? [];
        $pattern = $options['pattern'] ?? 'normal';
        $overwrite = $options['overwrite'] ?? false;
        // Only use service if it's a non-empty string (namespace), not a boolean
        $service = (isset($options['service']) && is_string($options['service']) && !empty($options['service'])) ? $options['service'] : null;
        $spatieData = $options['spatieData'] ?? null;

        if (empty($bulkEndpoints)) {
            return;
        }

        $bulkRequests = $this->requestBuilder->createBulkRequests(
            $modelData,
            $bulkEndpoints,
            $pattern,
            $overwrite,
            $module
        );

        $resourceName = null;
        if (in_array('api', $controllerTypes, true)) {
            $resourceName = $this->resourceBuilder->create($modelData, $overwrite, $pattern, null, $module);
        }

        $controllerOptions = [
            'overwrite' => $overwrite,
            'response-messages' => $options['response-messages'] ?? false,
            'controller-folder' => $options['controller-folder'] ?? null,
            'service' => $service,
            'pattern' => $pattern,
            'spatieData' => $spatieData,
        ];

        if ($service) {
            $uniqueKey = $options['uniqueKey'] ?? null;
            $this->serviceBuilder->createBulkService($modelData, $overwrite, $uniqueKey, $pattern, $spatieData, $module);
        }

        if (in_array('api', $controllerTypes, true)) {
            $apiControllerName = $this->bulkControllerBuilder->createAPI(
                $modelData,
                $resourceName,
                $bulkRequests,
                $controllerOptions,
                $module
            );
            $this->routeBuilder->createBulkRoutes(
                $modelData['modelName'],
                $apiControllerName,
                ['api'],
                $bulkEndpoints,
                $options['controller-folder'] ?? null,
                $module
            );
        }

        if (in_array('web', $controllerTypes, true)) {
            $webControllerName = $this->bulkControllerBuilder->createWeb(
                $modelData,
                $bulkRequests,
                $controllerOptions,
                $module
            );
            $this->routeBuilder->createBulkRoutes(
                $modelData['modelName'],
                $webControllerName,
                ['web'],
                $bulkEndpoints,
                $options['controller-folder'] ?? null,
                $module
            );
        }

        if ($options['pest'] ?? false) {
            $this->pestBuilder->createBulkFeatureTest($modelData, $overwrite);
            
            if ($service) {
                $uniqueKey = $options['uniqueKey'] ?? null;
                $this->pestBuilder->createBulkServiceUnitTest($modelData, $overwrite, $uniqueKey);
            }
        }

        info('Bulk endpoints generated successfully for ' . $modelData['modelName'] . ' Model');
    }
}
