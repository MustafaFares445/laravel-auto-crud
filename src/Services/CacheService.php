<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Services;

use Illuminate\Support\Facades\Cache;

class CacheService
{
    /**
     * Generate cache key for a model.
     *
     * @param string $modelName Model name
     * @param int|string|null $id Model ID
     * @return string Cache key
     */
    public static function generateCacheKey(string $modelName, int|string|null $id = null): string
    {
        $baseKey = strtolower($modelName);
        
        if ($id !== null) {
            return "{$baseKey}:{$id}";
        }
        
        return "{$baseKey}:list";
    }

    /**
     * Generate cache tags for a model.
     *
     * @param string $modelName Model name
     * @return array<string> Cache tags
     */
    public static function generateCacheTags(string $modelName): array
    {
        return [strtolower($modelName), strtolower($modelName) . '_list'];
    }

    /**
     * Get cache TTL from config.
     *
     * @return int Cache TTL in seconds
     */
    public static function getCacheTtl(): int
    {
        return (int) config('laravel_auto_crud.cache.ttl', 3600);
    }

    /**
     * Clear cache for a model.
     *
     * @param string $modelName Model name
     * @param int|string|null $id Model ID (optional, clears specific item)
     * @return void
     */
    public static function clearModelCache(string $modelName, int|string|null $id = null): void
    {
        $tags = self::generateCacheTags($modelName);
        
        if (Cache::getStore() instanceof \Illuminate\Cache\TaggedCache) {
            Cache::tags($tags)->flush();
        } else {
            // Fallback for cache stores that don't support tags
            $pattern = strtolower($modelName) . ($id !== null ? ":{$id}" : ':');
            Cache::flush(); // Note: This is a fallback - consider using cache tags in production
        }
    }
}

