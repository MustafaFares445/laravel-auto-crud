<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Builders;

class ObserverBuilder extends BaseBuilder
{
    /**
     * Create model observer class.
     *
     * @param array<string, mixed> $modelData Model information
     * @param array<string> $events Events to observe
     * @param bool $overwrite Whether to overwrite existing files
     * @return string Full namespace path of created observer class
     */
    public function createObserver(array $modelData, array $events, bool $overwrite = false): string
    {
        $observerNamespace = 'App\\Observers';
        if ($modelData['folders']) {
            $observerNamespace .= '\\' . str_replace('/', '\\', $modelData['folders']);
        }

        $className = $modelData['modelName'] . 'Observer';
        
        return $this->fileService->createFromStub(
            $modelData,
            'observer',
            'Observers',
            'Observer',
            $overwrite,
            function ($modelData) use ($events, $observerNamespace, $className) {
                $model = $this->getFullModelNamespace($modelData);
                $observerMethods = $this->generateObserverMethods($events, $modelData['modelName']);

                return [
                    '{{ namespace }}' => $observerNamespace,
                    '{{ class }}' => $className,
                    '{{ modelNamespace }}' => $model,
                    '{{ model }}' => $modelData['modelName'],
                    '{{ observerMethods }}' => $observerMethods,
                ];
            }
        );
    }

    /**
     * Generate observer method stubs.
     *
     * @param array<string> $events Event names
     * @param string $modelName Model name
     * @return string Generated observer methods code
     */
    private function generateObserverMethods(array $events, string $modelName): string
    {
        $methods = [];

        $eventMethodMap = [
            'retrieved' => 'retrieved',
            'creating' => 'creating',
            'created' => 'created',
            'updating' => 'updating',
            'updated' => 'updated',
            'saving' => 'saving',
            'saved' => 'saved',
            'deleting' => 'deleting',
            'deleted' => 'deleted',
            'restoring' => 'restoring',
            'restored' => 'restored',
            'replicating' => 'replicating',
        ];

        foreach ($events as $event) {
            $methodName = $eventMethodMap[$event] ?? $event;
            $methods[] = "    /**\n     * Handle the {$modelName} {$event} event.\n     *\n     * @param \\App\\Models\\{$modelName} \$model\n     * @return void\n     */\n    public function {$methodName}(\\App\\Models\\{$modelName} \$model): void\n    {\n        // Handle {$event} event\n    }";
        }

        return implode("\n\n", $methods);
    }
}

