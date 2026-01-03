<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Builders;

class BulkOperationBuilder extends BaseBuilder
{
    /**
     * Generate bulk operation methods for controllers.
     *
     * @param array<string, mixed> $modelData Model information
     * @param array<string> $operations Operations to generate (e.g., ['create', 'update', 'delete'])
     * @return string Generated bulk operation methods code
     */
    public function generateBulkMethods(array $modelData, array $operations): string
    {
        $methods = [];
        $model = $this->getFullModelNamespace($modelData);
        $modelVariable = lcfirst($modelData['modelName']);
        $modelName = $modelData['modelName'];

        foreach ($operations as $operation) {
            $methods[] = $this->generateBulkMethod($operation, $modelName, $model, $modelVariable);
        }

        return implode("\n\n", $methods);
    }

    /**
     * Generate a single bulk operation method.
     *
     * @param string $operation Operation type
     * @param string $modelName Model name
     * @param string $modelNamespace Full model namespace
     * @param string $modelVariable Model variable name
     * @return string Generated method code
     */
    private function generateBulkMethod(string $operation, string $modelName, string $modelNamespace, string $modelVariable): string
    {
        $methodName = 'bulk' . ucfirst($operation);
        $returnType = $operation === 'delete' 
            ? '\Illuminate\Http\JsonResponse' 
            : '\Illuminate\Http\Resources\Json\AnonymousResourceCollection';
        
        $methodBody = $this->getBulkMethodBody($operation, $modelName, $modelNamespace, $modelVariable);

        return <<<PHP
    /**
     * Bulk {$operation} operation.
     *
     * @param \Illuminate\Http\Request \$request
     * @return {$returnType}
     */
    public function {$methodName}(\Illuminate\Http\Request \$request): {$returnType}
    {
{$methodBody}
    }
PHP;
    }

    /**
     * Get method body based on operation type.
     *
     * @param string $operation Operation type
     * @param string $modelName Model name
     * @param string $modelNamespace Full model namespace
     * @param string $modelVariable Model variable name
     * @return string Method body
     */
    private function getBulkMethodBody(string $operation, string $modelName, string $modelNamespace, string $modelVariable): string
    {
        switch ($operation) {
            case 'create':
                return <<<PHP
        \$validated = \$request->validate([
            'items' => 'required|array|min:1',
            'items.*' => 'required|array',
        ]);

        \$created = [];
        foreach (\$validated['items'] as \$item) {
            \$created[] = {$modelNamespace}::create(\$item);
        }

        return \App\Http\Resources\{$modelName}Resource::collection(collect(\$created));
PHP;

            case 'update':
                return <<<PHP
        \$validated = \$request->validate([
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|exists:{$modelNamespace}::getTable(),id',
            'items.*.data' => 'required|array',
        ]);

        \$updated = [];
        foreach (\$validated['items'] as \$item) {
            \${$modelVariable} = {$modelNamespace}::findOrFail(\$item['id']);
            \${$modelVariable}->update(\$item['data']);
            \$updated[] = \${$modelVariable};
        }

        return \App\Http\Resources\{$modelName}Resource::collection(collect(\$updated));
PHP;

            case 'delete':
                return <<<PHP
        \$validated = \$request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|exists:{$modelNamespace}::getTable(),id',
        ]);

        {$modelNamespace}::whereIn('id', \$validated['ids'])->delete();

        return response()->json([
            'message' => 'Items deleted successfully',
            'deleted_count' => count(\$validated['ids']),
        ]);
PHP;

            default:
                return "        // Bulk {$operation} logic here";
        }
    }
}

