<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Builders;

class ScrambleBuilder extends BaseBuilder
{
    /**
     * Check if Scramble is installed.
     *
     * @return bool
     */
    public function isInstalled(): bool
    {
        return class_exists(\Dedoc\Scramble\ScrambleServiceProvider::class);
    }

    /**
     * Generate OpenAPI operation attributes for a controller method.
     *
     * @param string $method HTTP method (GET, POST, PUT, DELETE)
     * @param string $operation Operation name (index, store, show, update, destroy)
     * @param array<string, mixed> $modelData Model information
     * @return string Generated PHP attributes code
     */
    public function generateOperationAttributes(string $method, string $operation, array $modelData): string
    {
        $summary = $this->getOperationSummary($operation, $modelData['modelName']);
        $description = $this->getOperationDescription($operation, $modelData['modelName']);
        $tags = [ucfirst($modelData['modelName'])];

        $attributes = [];
        
        if (class_exists(\Dedoc\Scramble\Support\OperationExtensions\OperationExtension::class)) {
            // Scramble v0.13+ uses Operation extensions
            $attributes[] = "#[\\Dedoc\\Scramble\\Http\\Annotations\\Operation(tags: " . $this->arrayToString($tags) . ")]";
        } else {
            // Fallback for older versions
            $attributes[] = "/**\n     * @operationId {$operation}{$modelData['modelName']}\n     * @summary {$summary}\n     * @description {$description}\n     * @tags " . implode(', ', $tags) . "\n     */";
        }

        return implode("\n", $attributes);
    }

    /**
     * Generate response attributes for a controller method.
     *
     * @param string $operation Operation name
     * @param array<string, mixed> $modelData Model information
     * @param bool $isCollection Whether the response is a collection
     * @return string Generated response attributes code
     */
    public function generateResponseAttributes(string $operation, array $modelData, bool $isCollection = false): string
    {
        $modelName = $modelData['modelName'];
        $resourceClass = "App\\Http\\Resources\\{$modelName}Resource";
        
        if ($isCollection) {
            return "#[\\Dedoc\\Scramble\\Http\\Annotations\\Response(description: 'Success', content: \\Dedoc\\Scramble\\Http\\Annotations\\JsonContent(type: 'array', items: new \\Dedoc\\Scramble\\Http\\Annotations\\Items(type: 'object', ref: '#/components/schemas/{$modelName}Resource')))]";
        }
        
        return "#[\\Dedoc\\Scramble\\Http\\Annotations\\Response(description: 'Success', content: new \\Dedoc\\Scramble\\Http\\Annotations\\JsonContent(ref: '#/components/schemas/{$modelName}Resource'))]";
    }

    /**
     * Get operation summary.
     *
     * @param string $operation
     * @param string $modelName
     * @return string
     */
    private function getOperationSummary(string $operation, string $modelName): string
    {
        return match ($operation) {
            'index' => "List all {$modelName} records",
            'store' => "Create a new {$modelName}",
            'show' => "Get a specific {$modelName}",
            'update' => "Update a {$modelName}",
            'destroy' => "Delete a {$modelName}",
            default => "{$operation} {$modelName}",
        };
    }

    /**
     * Get operation description.
     *
     * @param string $operation
     * @param string $modelName
     * @return string
     */
    private function getOperationDescription(string $operation, string $modelName): string
    {
        return match ($operation) {
            'index' => "Retrieve a paginated list of {$modelName} records",
            'store' => "Create a new {$modelName} record with the provided data",
            'show' => "Retrieve detailed information about a specific {$modelName} record",
            'update' => "Update an existing {$modelName} record",
            'destroy' => "Permanently delete a {$modelName} record",
            default => "{$operation} operation for {$modelName}",
        };
    }

    /**
     * Convert array to PHP string representation.
     *
     * @param array<string> $array
     * @return string
     */
    private function arrayToString(array $array): string
    {
        $items = array_map(fn($item) => "'{$item}'", $array);
        return '[' . implode(', ', $items) . ']';
    }
}

