<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Builders;

class JobBuilder extends BaseBuilder
{
    /**
     * Create a job class for async operations.
     *
     * @param array<string, mixed> $modelData Model information
     * @param string $operation Operation type (e.g., 'create', 'update', 'delete')
     * @param bool $overwrite Whether to overwrite existing files
     * @return string Full namespace path of created job class
     */
    public function createJob(array $modelData, string $operation, bool $overwrite = false): string
    {
        $jobNamespace = 'App\\Jobs';
        if ($modelData['folders']) {
            $jobNamespace .= '\\' . str_replace('/', '\\', $modelData['folders']);
        }

        $className = $modelData['modelName'] . ucfirst($operation) . 'Job';
        
        return $this->fileService->createFromStub(
            $modelData,
            'job',
            'Jobs',
            ucfirst($operation) . 'Job',
            $overwrite,
            function ($modelData) use ($operation, $jobNamespace, $className) {
                $model = $this->getFullModelNamespace($modelData);
                $jobMethod = $this->generateJobMethod($operation, $modelData['modelName']);

                return [
                    '{{ namespace }}' => $jobNamespace,
                    '{{ class }}' => $className,
                    '{{ modelNamespace }}' => $model,
                    '{{ model }}' => $modelData['modelName'],
                    '{{ operation }}' => ucfirst($operation),
                    '{{ jobMethod }}' => $jobMethod,
                ];
            }
        );
    }

    /**
     * Generate job handle method based on operation.
     *
     * @param string $operation Operation type
     * @param string $modelName Model name
     * @return string Generated job method code
     */
    private function generateJobMethod(string $operation, string $modelName): string
    {
        $modelVariable = lcfirst($modelName);
        
        switch ($operation) {
            case 'create':
                return "        // Create {$modelName} logic here\n        // Example:\n        // \${$modelVariable} = {$modelName}::create(\$this->data);";
            
            case 'update':
                return "        // Update {$modelName} logic here\n        // Example:\n        // \${$modelVariable} = {$modelName}::findOrFail(\$this->id);\n        // \${$modelVariable}->update(\$this->data);";
            
            case 'delete':
                return "        // Delete {$modelName} logic here\n        // Example:\n        // \${$modelVariable} = {$modelName}::findOrFail(\$this->id);\n        // \${$modelVariable}->delete();";
            
            default:
                return "        // Handle {$operation} operation for {$modelName}";
        }
    }
}

