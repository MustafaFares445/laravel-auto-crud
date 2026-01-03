<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class PresetService
{
    private string $presetsPath;

    public function __construct()
    {
        $this->presetsPath = config('laravel_auto_crud.presets_path', storage_path('app/auto-crud-presets'));
    }

    /**
     * Save a preset configuration.
     *
     * @param string $name Preset name
     * @param array<string, mixed> $options Configuration options
     * @return bool
     */
    public function savePreset(string $name, array $options): bool
    {
        $presetFile = $this->getPresetPath($name);

        File::ensureDirectoryExists(dirname($presetFile), 0755, true);

        $presetData = [
            'name' => $name,
            'options' => $options,
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ];

        return File::put($presetFile, json_encode($presetData, JSON_PRETTY_PRINT)) !== false;
    }

    /**
     * Load a preset configuration.
     *
     * @param string $name Preset name
     * @return array<string, mixed>|null Preset options or null if not found
     */
    public function loadPreset(string $name): ?array
    {
        $presetFile = $this->getPresetPath($name);

        if (!File::exists($presetFile)) {
            return null;
        }

        $content = File::get($presetFile);
        $presetData = json_decode($content, true);

        return $presetData['options'] ?? null;
    }

    /**
     * List all available presets.
     *
     * @return array<string, array<string, mixed>>
     */
    public function listPresets(): array
    {
        $presets = [];

        if (!File::exists($this->presetsPath)) {
            return $presets;
        }

        $files = File::files($this->presetsPath);

        foreach ($files as $file) {
            if ($file->getExtension() === 'json') {
                $content = File::get($file->getPathname());
                $presetData = json_decode($content, true);

                if ($presetData) {
                    $presets[$presetData['name']] = [
                        'name' => $presetData['name'],
                        'created_at' => $presetData['created_at'] ?? null,
                        'updated_at' => $presetData['updated_at'] ?? null,
                        'options' => $presetData['options'] ?? [],
                    ];
                }
            }
        }

        return $presets;
    }

    /**
     * Delete a preset.
     *
     * @param string $name Preset name
     * @return bool
     */
    public function deletePreset(string $name): bool
    {
        $presetFile = $this->getPresetPath($name);

        if (!File::exists($presetFile)) {
            return false;
        }

        return File::delete($presetFile);
    }

    /**
     * Check if a preset exists.
     *
     * @param string $name Preset name
     * @return bool
     */
    public function presetExists(string $name): bool
    {
        return File::exists($this->getPresetPath($name));
    }

    /**
     * Get the file path for a preset.
     *
     * @param string $name Preset name
     * @return string
     */
    private function getPresetPath(string $name): string
    {
        // Sanitize preset name to prevent directory traversal
        $name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $name);
        return $this->presetsPath . '/' . $name . '.json';
    }

    /**
     * Get preset metadata.
     *
     * @param string $name Preset name
     * @return array<string, mixed>|null
     */
    public function getPresetMetadata(string $name): ?array
    {
        $presetFile = $this->getPresetPath($name);

        if (!File::exists($presetFile)) {
            return null;
        }

        $content = File::get($presetFile);
        $presetData = json_decode($content, true);

        if (!$presetData) {
            return null;
        }

        return [
            'name' => $presetData['name'] ?? $name,
            'created_at' => $presetData['created_at'] ?? null,
            'updated_at' => $presetData['updated_at'] ?? null,
            'options' => $presetData['options'] ?? [],
        ];
    }
}

