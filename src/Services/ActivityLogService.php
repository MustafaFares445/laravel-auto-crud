<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Services;

class ActivityLogService
{
    /**
     * Check if Spatie Activity Log is installed.
     *
     * @return bool
     */
    public static function isInstalled(): bool
    {
        return class_exists(\Spatie\Activitylog\Traits\LogsActivity::class)
            || class_exists(\Spatie\Activitylog\Contracts\Activity::class);
    }

    /**
     * Generate activity log configuration for a model.
     *
     * @param array<string, mixed> $modelData Model information
     * @return array<string, mixed> Activity log configuration
     */
    public static function generateConfig(array $modelData): array
    {
        return [
            'logOnly' => [], // All attributes by default
            'logOnlyDirty' => true,
            'logUnguarded' => false,
            'submitEmptyLogs' => false,
            'useLogName' => strtolower($modelData['modelName']),
        ];
    }

    /**
     * Get activity log trait name.
     *
     * @return string
     */
    public static function getTraitName(): string
    {
        return 'Spatie\Activitylog\Traits\LogsActivity';
    }

    /**
     * Generate activity log method for model stub.
     *
     * @param array<string, mixed> $modelData Model information
     * @return string Generated method code
     */
    public static function generateLogAttributesMethod(array $modelData): string
    {
        return <<<'PHP'
    /**
     * Get the attributes that should be logged.
     *
     * @return array
     */
    public function getLogAttributesToLog(): array
    {
        return $this->fillable ?? [];
    }
PHP;
    }
}

