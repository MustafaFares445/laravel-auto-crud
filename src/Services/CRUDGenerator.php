<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Services;

use Illuminate\Support\Facades\File;
use Mrmarchone\LaravelAutoCrud\Builders\ControllerBuilder;
use Mrmarchone\LaravelAutoCrud\Exceptions\GenerationException;
use Mrmarchone\LaravelAutoCrud\Exceptions\InvalidConfigurationException;
use Mrmarchone\LaravelAutoCrud\Builders\FactoryBuilder;
use Mrmarchone\LaravelAutoCrud\Builders\PermissionGroupEnumBuilder;
use Mrmarchone\LaravelAutoCrud\Builders\PermissionSeederBuilder;
use Mrmarchone\LaravelAutoCrud\Builders\PestBuilder;
use Mrmarchone\LaravelAutoCrud\Builders\PolicyBuilder;
use Mrmarchone\LaravelAutoCrud\Builders\SpatieFilterBuilder;
use Mrmarchone\LaravelAutoCrud\Builders\RequestBuilder;
use Mrmarchone\LaravelAutoCrud\Builders\ResourceBuilder;
use Mrmarchone\LaravelAutoCrud\Builders\RouteBuilder;
use Mrmarchone\LaravelAutoCrud\Builders\ServiceBuilder;
use Mrmarchone\LaravelAutoCrud\Builders\SpatieDataBuilder;
use Mrmarchone\LaravelAutoCrud\Builders\ViewBuilder;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;

class CRUDGenerator
{
    public function __construct(
        private ControllerBuilder $controllerBuilder,
        private ResourceBuilder $resourceBuilder,
        private RequestBuilder $requestBuilder,
        private RouteBuilder $routeBuilder,
        private ViewBuilder $viewBuilder,
        private ServiceBuilder $serviceBuilder,
        private SpatieDataBuilder $spatieDataBuilder,
        private SpatieFilterBuilder $spatieFilterBuilder,
        private PolicyBuilder $policyBuilder,
        private PestBuilder $pestBuilder,
        private FactoryBuilder $factoryBuilder,
        private PermissionSeederBuilder $permissionSeederBuilder,
        private PermissionGroupEnumBuilder $permissionGroupEnumBuilder,
        private ModelTraitService $modelTraitService,
    ) {
        // Dependencies are injected via Laravel's service container
    }

    /**
     * Generate CRUD files for the given model.
     *
     * @param array<string, mixed> $modelData Model information including 'modelName', 'namespace', 'folders', 'path'
     * @param array<string, mixed> $options Generation options including 'type', 'pattern', 'overwrite', etc.
     * @return void
     */
    public function generate(array $modelData, array $options): void
    {
        $checkForType = $options['type'];

        // Create filter trait first if needed (before injecting traits)
        $filterBuilder = $filterRequest = null;
        if ($options['filter'] ?? false) {
            if ($options['pattern'] === 'spatie-data') {
                $filterRequest = $this->spatieFilterBuilder->createFilterRequest($modelData, $options['overwrite']);
                $filterBuilder = $this->spatieFilterBuilder->createFilterQueryTrait($modelData, $options['overwrite']);
            } else {
                throw new InvalidConfigurationException(
                    'filter',
                    $options['pattern'] ?? null,
                    'Filter option is only supported with the spatie-data pattern'
                );
            }
        }

        // Inject traits if needed (after filter trait is created)
        $traits = [];
        if ($options['factory'] ?? false) {
            $traits[] = 'Illuminate\Database\Eloquent\Factories\HasFactory';
        }
        if ($options['filter'] ?? false) {
            $traits[] = 'App\Traits\FilterQueries\\' . $modelData['modelName'] . 'FilterQuery';
        }

        if (!empty($traits)) {
            $modelPath = base_path($modelData['path'] ?? 'app/Models/' . $modelData['modelName'] . '.php');
            if (File::exists($modelPath)) {
                $this->modelTraitService->injectTraits($modelPath, $traits);
            }
        }

        $requests = $spatieDataName = $service = null;

        if ($options['pattern'] === 'spatie-data') {
            $spatieDataName = $this->spatieDataBuilder->create($modelData, $options['overwrite']);

            // If using service with spatie-data, also create FormRequest
            if ($options['service'] ?? false) {
                $requests = $this->requestBuilder->createForSpatieData($modelData, $options['overwrite']);
            }
        } else {
            $requests = $this->requestBuilder->create($modelData, $options['overwrite']);
        }

        if ($options['service'] ?? false) {
            $service = $this->serviceBuilder->createServiceOnly($modelData, $options['overwrite']);
        }

        if ($options['policy'] ?? false) {
            $this->policyBuilder->create($modelData, $options['overwrite']);
        }

        if ($options['factory'] ?? false) {
            $this->factoryBuilder->create($modelData, $options['overwrite']);
        }

        if ($options['permissions-seeder'] ?? false) {
            $this->permissionSeederBuilder->create($modelData, $options['overwrite']);
            $this->permissionGroupEnumBuilder->updateEnum($modelData, $options['overwrite']);
        }

        if ($options['pest'] ?? false) {
            $this->pestBuilder->createFeatureTest($modelData, $options['overwrite'], (bool) ($options['policy'] ?? false));
            
            // Generate filter tests if filters are enabled
            if (($options['filter'] ?? false) && $options['pattern'] === 'spatie-data') {
                $this->pestBuilder->createFilterTest($modelData, $options['overwrite']);
            }
        }

        $data = [
            'requests' => $requests ?? [],
            'service' => $service ?? '',
            'spatieData' => $spatieDataName ?? '',
            'filterBuilder' => $filterBuilder ?? '',
            'filterRequest' => $filterRequest ?? '',
        ];

        $hasSoftDeletes = SoftDeleteDetector::hasSoftDeletes(ModelService::getFullModelNamespace($modelData));
        $data['hasSoftDeletes'] = $hasSoftDeletes;

        $controllerName = $this->generateController($checkForType, $modelData, $data, $options);
        $withAuth = (bool) ($options['sanctum'] ?? false);
        $apiVersion = $options['api-version'] ?? null;
        $this->routeBuilder->create($modelData['modelName'], $controllerName, $checkForType, $withAuth, $hasSoftDeletes, $apiVersion);

        info('Auto CRUD files generated successfully for '.$modelData['modelName'].' Model');
    }

    private function generateController(array $types, array $modelData, array $data, array $options): string
    {
        $controllerName = null;

        if (in_array('api', $types, true)) {
            $controllerName = $this->generateAPIController($modelData, $data['requests'], $data['service'], $options, $data['spatieData'], $data['filterBuilder'] ?? null, $data['filterRequest'] ?? null);
        }

        if (in_array('web', $types, true)) {
            $controllerName = $this->generateWebController($modelData, $data['requests'], $data['service'], $options, $data['spatieData']);
        }

        if (! $controllerName) {
            throw new GenerationException(
                'controller generation',
                $modelData['modelName'] ?? 'unknown',
                'Unsupported controller type'
            );
        }

        return $controllerName;
    }

    private function generateAPIController(array $modelData, array $requests, string $service, array $options, ?string $spatieData = null, ?string $filterBuilder = null, ?string $filterRequest = null): string
    {
        $controllerName = null;

        // Always generate Resource for API controllers
        $resourceName = $this->resourceBuilder->create($modelData, $options['overwrite'], $options['pattern'] ?? 'normal', $spatieData);

        $controllerOptions = [
            'filterBuilder' => $filterBuilder,
            'filterRequest' => $filterRequest,
            'overwrite' => $options['overwrite'] ?? false,
            'response-messages' => $options['response-messages'] ?? false,
            'no-pagination' => $options['no-pagination'] ?? false,
            'controller-folder' => $options['controller-folder'] ?? null,
            'hasSoftDeletes' => $data['hasSoftDeletes'] ?? false,
        ];

        if ($options['pattern'] === 'spatie-data') {
            // If service with spatie-data, use FormRequest + Data class pattern
            if ($service && !empty($requests)) {
                $controllerName = $this->controllerBuilder->createAPIServiceSpatieData($modelData, $spatieData, $requests, $service, $resourceName, $controllerOptions);
            } else {
                $controllerName = $this->controllerBuilder->createAPISpatieData($modelData, $spatieData, $resourceName, $controllerOptions);
            }
        } elseif ($options['pattern'] === 'normal') {
            $controllerName = $this->controllerBuilder->createAPI($modelData, $resourceName, $requests, $controllerOptions);
        }

        if (! $controllerName) {
            throw new GenerationException(
                'controller generation',
                $modelData['modelName'] ?? 'unknown',
                'Unsupported controller type'
            );
        }

        return $controllerName;
    }

    private function generateWebController(array $modelData, array $requests, string $service, array $options, string $spatieData = ''): string
    {
        $controllerName = null;
        $controllerFolder = $options['controller-folder'] ?? null;

        if ($options['pattern'] === 'spatie-data') {
            $controllerName = $this->controllerBuilder->createWebSpatieData($modelData, $spatieData, $options['overwrite'], $controllerFolder);
        } elseif ($options['pattern'] === 'normal') {
            $controllerName = $this->controllerBuilder->createWeb($modelData, $requests, $options['overwrite'], $controllerFolder);
        }

        $this->viewBuilder->create($modelData, $options['overwrite']);

        if (! $controllerName) {
            throw new GenerationException(
                'controller generation',
                $modelData['modelName'] ?? 'unknown',
                'Unsupported controller type'
            );
        }

        return $controllerName;
    }
}
