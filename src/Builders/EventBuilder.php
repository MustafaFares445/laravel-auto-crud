<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Builders;

class EventBuilder extends BaseBuilder
{
    /**
     * Create model events class.
     *
     * @param array<string, mixed> $modelData Model information
     * @param array<string> $events Events to generate (e.g., ['created', 'updated', 'deleted'])
     * @param bool $overwrite Whether to overwrite existing files
     * @return string Full namespace path of created event class
     */
    public function createEvents(array $modelData, array $events, bool $overwrite = false): string
    {
        $eventNamespace = 'App\\Events';
        if ($modelData['folders']) {
            $eventNamespace .= '\\' . str_replace('/', '\\', $modelData['folders']);
        }

        $className = $modelData['modelName'] . 'Events';
        
        return $this->fileService->createFromStub(
            $modelData,
            'events',
            'Events',
            'Events',
            $overwrite,
            function ($modelData) use ($events, $eventNamespace, $className) {
                $model = $this->getFullModelNamespace($modelData);
                $eventMethods = $this->generateEventMethods($events, $modelData['modelName']);

                return [
                    '{{ namespace }}' => $eventNamespace,
                    '{{ class }}' => $className,
                    '{{ modelNamespace }}' => $model,
                    '{{ model }}' => $modelData['modelName'],
                    '{{ eventMethods }}' => $eventMethods,
                ];
            }
        );
    }

    /**
     * Generate event method stubs.
     *
     * @param array<string> $events Event names
     * @param string $modelName Model name
     * @return string Generated event methods code
     */
    private function generateEventMethods(array $events, string $modelName): string
    {
        $methods = [];

        foreach ($events as $event) {
            $methodName = 'on' . ucfirst($event);
            $methods[] = "    /**\n     * Handle the {$modelName} {$event} event.\n     *\n     * @param \\App\\Models\\{$modelName} \$model\n     * @return void\n     */\n    public function {$methodName}(\\App\\Models\\{$modelName} \$model): void\n    {\n        // Handle {$event} event\n    }";
        }

        return implode("\n\n", $methods);
    }
}

