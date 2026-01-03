<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Builders;

use Mrmarchone\LaravelAutoCrud\Services\ActivityLogService;

class ActivityLogBuilder extends BaseBuilder
{
    /**
     * Generate activity log configuration method for controllers.
     *
     * @param array<string, mixed> $modelData Model information
     * @return string Generated activity log method code
     */
    public function generateActivityLogMethod(array $modelData): string
    {
        $model = $this->getFullModelNamespace($modelData);
        $modelVariable = lcfirst($modelData['modelName']);

        return <<<PHP
    /**
     * Get activity log for a specific {$modelData['modelName']}.
     *
     * @param {$model} \${$modelVariable}
     * @return \Illuminate\Http\JsonResponse
     */
    public function activityLog({$model} \${$modelVariable}): \Illuminate\Http\JsonResponse
    {
        \$activities = \${$modelVariable}->activities()->latest()->paginate(20);

        return response()->json([
            'data' => \$activities->items(),
            'meta' => [
                'current_page' => \$activities->currentPage(),
                'last_page' => \$activities->lastPage(),
                'per_page' => \$activities->perPage(),
                'total' => \$activities->total(),
            ],
        ]);
    }
PHP;
    }

    /**
     * Check if activity log is available.
     *
     * @return bool
     */
    public function isAvailable(): bool
    {
        return ActivityLogService::isInstalled();
    }
}

