<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Services;

use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use Mrmarchone\LaravelAutoCrud\Builders\ControllerBuilder;
use Mrmarchone\LaravelAutoCrud\Builders\FactoryBuilder;
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
        private ModelTraitService $modelTraitService,
    ) {
        $this->controllerBuilder = new ControllerBuilder;
        $this->resourceBuilder = new ResourceBuilder;
        $this->requestBuilder = new RequestBuilder;
        $this->routeBuilder = new RouteBuilder;
        $this->viewBuilder = new ViewBuilder;
        $this->serviceBuilder = new ServiceBuilder;
        $this->spatieDataBuilder = new SpatieDataBuilder;
        $this->spatieFilterBuilder = new SpatieFilterBuilder;
        $this->policyBuilder = new PolicyBuilder;
        $this->pestBuilder = new PestBuilder;
        $this->factoryBuilder = new FactoryBuilder;
        $this->modelTraitService = new ModelTraitService;
    }

    public function generate($modelData, array $options): void
    {
        $checkForType = $options['type'];

        // Inject traits if needed
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
            if ($options['pattern'] === 'spatie-data') {
                $filterRequest = $this->spatieFilterBuilder->createFilterRequest($modelData, $options['overwrite']);
                $filterBuilder = $this->spatieFilterBuilder->createFilterQueryTrait($modelData, $options['overwrite']);
            } else {
                throw new \InvalidArgumentException('Filter option is only supported with the spatie-data pattern.');
            }
        }

        if (($options['response-messages'] ?? false) && in_array('api', $checkForType, true)) {
            $this->generateResponseMessagesEnum($options['overwrite']);
        }

        if ($options['policy'] ?? false) {
            $this->policyBuilder->create($modelData, $options['overwrite']);
        }

        if ($options['factory'] ?? false) {
            $this->factoryBuilder->create($modelData, $options['overwrite']);
        }

        if ($options['pest'] ?? false) {
            $this->pestBuilder->createFeatureTest($modelData, $options['overwrite'], (bool) ($options['policy'] ?? false));
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

    private function generateResponseMessagesEnum(bool $overwrite = false): void
    {
        $filePath = app_path('Enums/ResponseMessages.php');

        if (file_exists($filePath) && ! $overwrite) {
            $shouldOverwrite = confirm(
                label: 'ResponseMessages enum already exists, do you want to overwrite it? '.$filePath
            );
            if (! $shouldOverwrite) {
                return;
            }
        }

        File::ensureDirectoryExists(dirname($filePath), 0777, true);

        $projectStubPath = base_path('stubs/response_messages.enum.stub');
        $vendorStubPath = base_path('vendor/mustafafares/laravel-auto-crud/src/Stubs/response_messages.enum.stub');
        $stubPath = file_exists($projectStubPath) ? $projectStubPath : $vendorStubPath;

        File::copy($stubPath, $filePath);

        info("Created: $filePath");
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

        $controllerOptions = [
            'filterBuilder' => $filterBuilder,
            'filterRequest' => $filterRequest,
            'overwrite' => $options['overwrite'] ?? false,
            'response-messages' => $options['response-messages'] ?? false,
            'no-pagination' => $options['no-pagination'] ?? false,
        ];

        if ($options['pattern'] === 'spatie-data') {
            // If service with spatie-data, use FormRequest + Data class pattern
            if ($service && $requestName) {
                $controllerName = $this->controllerBuilder->createAPIServiceSpatieData($modelData, $spatieData, $requestName, $service, $resourceName, $controllerOptions);
            } else {
                $controllerName = $this->controllerBuilder->createAPISpatieData($modelData, $spatieData, $resourceName, $controllerOptions);
            }
        } elseif ($options['pattern'] === 'normal') {
            $controllerName = $this->controllerBuilder->createAPI($modelData, $resourceName, $requestName, $controllerOptions);
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
