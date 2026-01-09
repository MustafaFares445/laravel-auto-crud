<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Services;

use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use Mrmarchone\LaravelAutoCrud\Builders\ControllerBuilder;
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
use Mrmarchone\LaravelAutoCrud\Services\BulkEndpointsGenerator;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;

class CRUDGenerator
{
    private ControllerBuilder $controllerBuilder;
    private ResourceBuilder $resourceBuilder;
    private RequestBuilder $requestBuilder;
    private RouteBuilder $routeBuilder;
    private ViewBuilder $viewBuilder;
    private ServiceBuilder $serviceBuilder;
    private SpatieDataBuilder $spatieDataBuilder;
    private SpatieFilterBuilder $spatieFilterBuilder;
    private PolicyBuilder $policyBuilder;
    private PestBuilder $pestBuilder;
    private FactoryBuilder $factoryBuilder;
    private PermissionSeederBuilder $permissionSeederBuilder;
    private PermissionGroupEnumBuilder $permissionGroupEnumBuilder;
    private BulkEndpointsGenerator $bulkEndpointsGenerator;

    public function __construct(
        private FileService $fileService,
        private ModelTraitService $modelTraitService,
        private TableColumnsService $tableColumnsService,
        private ModelService $modelService,
    ) {
        $this->initializeBuilders();
    }

    private function initializeBuilders(): void
    {
        $this->controllerBuilder = new ControllerBuilder($this->fileService);
        $this->resourceBuilder = new ResourceBuilder($this->fileService, $this->modelService, $this->tableColumnsService);
        $this->requestBuilder = new RequestBuilder($this->fileService, $this->tableColumnsService, $this->modelService, new \Mrmarchone\LaravelAutoCrud\Builders\EnumBuilder($this->fileService));
        $this->routeBuilder = new RouteBuilder();
        $this->viewBuilder = new ViewBuilder();
        $this->serviceBuilder = new ServiceBuilder($this->fileService);
        $this->spatieDataBuilder = new SpatieDataBuilder($this->fileService, new \Mrmarchone\LaravelAutoCrud\Builders\EnumBuilder($this->fileService), $this->modelService, $this->tableColumnsService);
        $this->spatieFilterBuilder = new SpatieFilterBuilder($this->fileService, $this->tableColumnsService, $this->modelService);
        $this->policyBuilder = new PolicyBuilder($this->fileService);
        $this->pestBuilder = new PestBuilder($this->fileService, $this->tableColumnsService);
        $this->factoryBuilder = new FactoryBuilder($this->fileService, $this->tableColumnsService);
        $this->permissionSeederBuilder = new PermissionSeederBuilder($this->fileService);
        $this->permissionGroupEnumBuilder = new PermissionGroupEnumBuilder($this->fileService);
        $this->bulkEndpointsGenerator = new BulkEndpointsGenerator($this->fileService, $this->modelService, $this->tableColumnsService);
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
                throw new \InvalidArgumentException('Filter option is only supported with the spatie-data pattern.');
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

        $requests = $bulkRequests = $spatieDataName = $service = null;

        if ($options['pattern'] === 'spatie-data') {
            $spatieDataName = $this->spatieDataBuilder->create($modelData, $options['overwrite']);

            // If using service with spatie-data, also create FormRequest
            if ($options['service'] ?? false) {
                $requests = $this->requestBuilder->createForSpatieData($modelData, $options['overwrite']);
            }
        } else {
            $requests = $this->requestBuilder->create($modelData, $options['overwrite']);
        }

        // Note: Bulk requests are now created by BulkEndpointsGenerator, not here
        // This prevents bulk methods from being added to the main controller
        $bulkEndpoints = $options['bulk'] ?? [];

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

            // Generate unit tests if enabled in config (when pest is selected)
            $generateUnitTests = config('laravel_auto_crud.test_settings.generate_unit_tests', false);
            if ($generateUnitTests) {
                // Generate service unit tests if service is generated
                if ($options['service'] ?? false) {
                    $this->pestBuilder->createServiceUnitTest($modelData, $options['overwrite']);
                }

                // Generate request validation tests if requests are generated
                if (!empty($requests)) {
                    $this->pestBuilder->createRequestValidationTest($modelData, 'store', $options['overwrite']);
                    $this->pestBuilder->createRequestValidationTest($modelData, 'update', $options['overwrite']);
                }

                // Generate policy tests if policy is generated
                if ($options['policy'] ?? false) {
                    $this->pestBuilder->createPolicyTest($modelData, $options['overwrite']);
                }

                // Generate resource tests if resources are generated (always generated for API)
                if (in_array('api', $checkForType, true)) {
                    $this->pestBuilder->createResourceTest($modelData, $options['overwrite']);
                }
            }
        }

        $data = [
            'requests' => $requests ?? [],
            'bulkRequests' => [], // Empty - bulk methods go to separate controller
            'service' => $service ?? '',
            'spatieData' => $spatieDataName ?? '',
            'filterBuilder' => $filterBuilder ?? '',
            'filterRequest' => $filterRequest ?? '',
        ];

        $controllerName = $this->generateController($checkForType, $modelData, $data, $options);
        
        $modelClass = ModelService::getFullModelNamespace($modelData);
        $hasSoftDeletes = SoftDeleteDetector::hasSoftDeletes($modelClass);
        
        // Generate routes for main controller (without bulk routes)
        $this->routeBuilder->create($modelData['modelName'], $controllerName, $checkForType, $hasSoftDeletes, []);

        // Generate separate bulk controller if bulk endpoints are selected
        if (!empty($bulkEndpoints)) {
            $this->bulkEndpointsGenerator->generate($modelData, $options);
        }

        info('Auto CRUD files generated successfully for '.$modelData['modelName'].' Model');
    }

    private function generateController(array $types, array $modelData, array $data, array $options): string
    {
        $controllerName = null;

        if (in_array('api', $types, true)) {
            $controllerName = $this->generateAPIController($modelData, $data['requests'], $data['service'], $options, $data['spatieData'], $data['filterBuilder'] ?? null, $data['filterRequest'] ?? null, []);
        }

        if (in_array('web', $types, true)) {
            $controllerName = $this->generateWebController($modelData, $data['requests'], $data['service'], $options, $data['spatieData'], []);
        }

        if (! $controllerName) {
            throw new InvalidArgumentException('Unsupported controller type');
        }

        return $controllerName;
    }

    private function generateAPIController(array $modelData, array $requests, string $service, array $options, ?string $spatieData = null, ?string $filterBuilder = null, ?string $filterRequest = null, array $bulkRequests = []): string
    {
        $controllerName = null;

        // Always generate Resource for API controllers
        $resourceName = $this->resourceBuilder->create($modelData, $options['overwrite'], $options['pattern'] ?? 'normal', $spatieData);

        $modelClass = ModelService::getFullModelNamespace($modelData);
        $hasSoftDeletes = SoftDeleteDetector::hasSoftDeletes($modelClass);
        
        $controllerOptions = [
            'filterBuilder' => $filterBuilder,
            'filterRequest' => $filterRequest,
            'overwrite' => $options['overwrite'] ?? false,
            'response-messages' => $options['response-messages'] ?? false,
            'no-pagination' => $options['no-pagination'] ?? false,
            'controller-folder' => $options['controller-folder'] ?? null,
            'hasSoftDeletes' => $hasSoftDeletes,
            'bulkRequests' => $bulkRequests,
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
            throw new InvalidArgumentException('Unsupported controller type');
        }

        return $controllerName;
    }

    private function generateWebController(array $modelData, array $requests, string $service, array $options, string $spatieData = '', array $bulkRequests = []): string
    {
        $controllerName = null;
        $controllerFolder = $options['controller-folder'] ?? null;

        $controllerOptions = [
            'overwrite' => $options['overwrite'] ?? false,
            'controller-folder' => $controllerFolder,
            'bulkRequests' => $bulkRequests,
        ];

        if ($options['pattern'] === 'spatie-data') {
            $controllerName = $this->controllerBuilder->createWebSpatieData($modelData, $spatieData, $controllerOptions);
        } elseif ($options['pattern'] === 'normal') {
            $controllerName = $this->controllerBuilder->createWeb($modelData, $requests, $controllerOptions);
        }

        $this->viewBuilder->create($modelData, $options['overwrite']);

        if (! $controllerName) {
            throw new InvalidArgumentException('Unsupported controller type');
        }

        return $controllerName;
    }
}
