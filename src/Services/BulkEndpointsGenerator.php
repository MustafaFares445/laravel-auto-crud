<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Services;

use Mrmarchone\LaravelAutoCrud\Builders\BulkControllerBuilder;
use Mrmarchone\LaravelAutoCrud\Builders\PestBuilder;
use Mrmarchone\LaravelAutoCrud\Builders\RequestBuilder;
use Mrmarchone\LaravelAutoCrud\Builders\ResourceBuilder;
use Mrmarchone\LaravelAutoCrud\Builders\RouteBuilder;

use function Laravel\Prompts\info;

class BulkEndpointsGenerator
{
    private BulkControllerBuilder $bulkControllerBuilder;
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
        $this->resourceBuilder = new ResourceBuilder($this->fileService, $this->modelService, $this->tableColumnsService);
        $this->requestBuilder = new RequestBuilder($this->fileService, $this->tableColumnsService, $this->modelService, new \Mrmarchone\LaravelAutoCrud\Builders\EnumBuilder($this->fileService));
        $this->routeBuilder = new RouteBuilder();
        $this->pestBuilder = new PestBuilder($this->fileService, $this->tableColumnsService);
    }

    public function generate(array $modelData, array $options): void
    {
        $controllerTypes = $options['type'] ?? ['api'];
        $bulkEndpoints = $options['bulk'] ?? [];
        $pattern = $options['pattern'] ?? 'normal';
        $overwrite = $options['overwrite'] ?? false;
        $service = $options['service'] ?? null;

        if (empty($bulkEndpoints)) {
            return;
        }

        $bulkRequests = $this->requestBuilder->createBulkRequests(
            $modelData,
            $bulkEndpoints,
            $pattern,
            $overwrite
        );

        $resourceName = null;
        if (in_array('api', $controllerTypes, true)) {
            $resourceName = $this->resourceBuilder->create($modelData, $overwrite, $pattern, null);
        }

        $controllerOptions = [
            'overwrite' => $overwrite,
            'response-messages' => $options['response-messages'] ?? false,
            'controller-folder' => $options['controller-folder'] ?? null,
            'service' => $service,
            'pattern' => $pattern,
        ];

        if (in_array('api', $controllerTypes, true)) {
            $apiControllerName = $this->bulkControllerBuilder->createAPI(
                $modelData,
                $resourceName,
                $bulkRequests,
                $controllerOptions
            );
            $this->routeBuilder->createBulkRoutes(
                $modelData['modelName'],
                $apiControllerName,
                ['api'],
                $bulkEndpoints
            );
        }

        if (in_array('web', $controllerTypes, true)) {
            $webControllerName = $this->bulkControllerBuilder->createWeb(
                $modelData,
                $bulkRequests,
                $controllerOptions
            );
            $this->routeBuilder->createBulkRoutes(
                $modelData['modelName'],
                $webControllerName,
                ['web'],
                $bulkEndpoints
            );
        }

        if ($options['pest'] ?? false) {
            $this->pestBuilder->createBulkFeatureTest($modelData, $overwrite);
        }

        info('Bulk endpoints generated successfully for ' . $modelData['modelName'] . ' Model');
    }
}
