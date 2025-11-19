<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Services;

use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;

class MediaDetector
{
    /**
     * Detect media fields from a model class
     *
     * @param string $modelClass Full class name of the model
     * @return array Array of media field definitions
     */
    public static function detectMediaFields(string $modelClass): array
    {
        if (!class_exists($modelClass)) {
            return [];
        }

        $reflection = new ReflectionClass($modelClass);
        
        // Check if model uses InteractsWithMedia trait
        if (!self::usesMediaTrait($reflection)) {
            return [];
        }

        // Try to extract media collections from registerMediaCollections method
        $collections = self::extractMediaCollections($modelClass);
        
        return self::mapCollectionsToFields($collections);
    }

    /**
     * Check if model uses InteractsWithMedia trait
     */
    private static function usesMediaTrait(ReflectionClass $reflection): bool
    {
        $traits = $reflection->getTraitNames();
        
        foreach ($traits as $trait) {
            if (str_contains($trait, 'InteractsWithMedia') || 
                str_contains($trait, 'HasMediaConversions') ||
                str_contains($trait, 'HasMedia')) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Extract media collections from the model
     */
    private static function extractMediaCollections(string $modelClass): array
    {
        try {
            $model = new $modelClass();
            $reflection = new ReflectionClass($modelClass);
            
            if (!$reflection->hasMethod('registerMediaCollections')) {
                return [];
            }
            
            $method = $reflection->getMethod('registerMediaCollections');
            $source = $method->getFileName();
            
            if (!$source) {
                return [];
            }
            
            $startLine = $method->getStartLine();
            $endLine = $method->getEndLine();
            $lines = file($source);
            $methodSource = implode('', array_slice($lines, $startLine - 1, $endLine - $startLine + 1));
            
            return self::parseMediaCollections($methodSource);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Parse media collections from method source code
     */
    private static function parseMediaCollections(string $source): array
    {
        $collections = [];
        
        // Match addMediaCollection calls
        preg_match_all('/addMediaCollection\([\'"]([^\'"]+)[\'"]\)/i', $source, $matches);
        
        if (empty($matches[1])) {
            return [];
        }
        
        foreach ($matches[1] as $collectionName) {
            // Check if this collection has singleFile() in the same chain
            $pattern = '/addMediaCollection\([\'"]' . preg_quote($collectionName, '/') . '[\'"]\)(.*?)(?=addMediaCollection|registerMediaConversions|public function|\})/s';
            preg_match($pattern, $source, $chainMatch);
            
            $isSingle = false;
            $mimeTypes = [];
            
            if (!empty($chainMatch[1])) {
                $chain = $chainMatch[1];
                $isSingle = str_contains($chain, 'singleFile()');
                
                // Extract mime types
                if (preg_match('/acceptsMimeTypes\(\[(.*?)\]\)/s', $chain, $mimeMatch)) {
                    preg_match_all('/[\'"]([^\'"]+)[\'"]/', $mimeMatch[1], $types);
                    $mimeTypes = $types[1] ?? [];
                }
            }
            
            $collections[] = [
                'name' => $collectionName,
                'isSingle' => $isSingle,
                'mimeTypes' => $mimeTypes,
            ];
        }
        
        return $collections;
    }

    /**
     * Map collection names to field names
     */
    private static function mapCollectionsToFields(array $collections): array
    {
        $fields = [];
        
        foreach ($collections as $collection) {
            $fieldName = self::collectionToFieldName($collection['name']);
            $type = self::determineMediaType($collection['name'], $collection['mimeTypes']);
            
            $fields[] = [
                'name' => $fieldName,
                'collectionName' => $collection['name'],
                'isSingle' => $collection['isSingle'],
                'type' => $type,
                'validation' => self::getValidationRules($type, $collection['isSingle']),
            ];
        }
        
        return $fields;
    }

    /**
     * Convert collection name to camelCase field name
     */
    private static function collectionToFieldName(string $collectionName): string
    {
        // Convert kebab-case or snake_case to camelCase
        return Str::camel($collectionName);
    }

    /**
     * Determine media type from collection name and mime types
     */
    private static function determineMediaType(string $name, array $mimeTypes): string
    {
        $name = strtolower($name);
        
        // Check mime types first
        if (!empty($mimeTypes)) {
            $firstMime = strtolower($mimeTypes[0]);
            if (str_contains($firstMime, 'image')) {
                return 'image';
            }
            if (str_contains($firstMime, 'video')) {
                return 'video';
            }
            if (str_contains($firstMime, 'audio')) {
                return 'audio';
            }
        }
        
        // Check collection name
        if (str_contains($name, 'video')) {
            return 'video';
        }
        if (str_contains($name, 'audio')) {
            return 'audio';
        }
        
        // Default to image
        return 'image';
    }

    /**
     * Get validation rules for media type
     */
    private static function getValidationRules(string $type, bool $isSingle): string
    {
        $rules = match($type) {
            'video' => 'file|mimes:mp4,mov,avi,wmv|max:51200',
            'audio' => 'file|mimes:mp3,wav,ogg|max:10240',
            default => 'file|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
        };
        
        return 'nullable|' . $rules . ($isSingle ? '' : '|array');
    }

    /**
     * Get PHP type hint for media field
     */
    public static function getTypeHint(bool $isSingle): string
    {
        return $isSingle ? '?UploadedFile' : '?array';
    }
}

