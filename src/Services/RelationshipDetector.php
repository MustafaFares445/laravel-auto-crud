<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use ReflectionClass;
use ReflectionMethod;

class RelationshipDetector
{
    /**
     * Detect relationships from a model class
     *
     * @param string $modelClass Full class name of the model
     * @return array Arrasty of relationship definitions
     */
    public static function detectRelationships(string $modelClass): array
    {
        if (!class_exists($modelClass)) {
            return [];
        }

        $reflection = new ReflectionClass($modelClass);
        $relationships = [];

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            // Skip magic methods and non-relationship methods
            if (str_starts_with($method->getName(), '__') || $method->getNumberOfParameters() > 0) {
                continue;
            }
            $returnType = $method->getReturnType();

            // Check if method returns a relationship type
            if (!$returnType || !$returnType instanceof \ReflectionNamedType) {
                continue;
            }

            $returnTypeName = $returnType->getName();
            $relationshipType = self::getRelationshipType($returnTypeName);

            if (!$relationshipType) {
                continue;
            }

            try {
                $modelInstance = new $modelClass();
                $relationship = $method->invoke($modelInstance);

                if (!$relationship instanceof \Illuminate\Database\Eloquent\Relations\Relation) {
                    continue;
                }

                $relationshipData = self::extractRelationshipData(
                    $method->getName(),
                    $relationshipType,
                    $relationship,
                    $modelClass
                );

                if ($relationshipData) {
                    $relationships[] = $relationshipData;
                }
            } catch (\Exception $e) {
                // Skip relationships that can't be instantiated
                continue;
            }
        }

        return $relationships;
    }

    /**
     * Get relationship type from return type name
     */
    private static function getRelationshipType(string $returnTypeName): ?string
    {
        $relationshipTypes = [
            BelongsTo::class => 'belongsTo',
            HasOne::class => 'hasOne',
            HasMany::class => 'hasMany',
            BelongsToMany::class => 'belongsToMany',
            HasManyThrough::class => 'hasManyThrough',
            MorphTo::class => 'morphTo',
            MorphOne::class => 'morphOne',
            MorphMany::class => 'morphMany',
            MorphToMany::class => 'morphToMany',
        ];

        foreach ($relationshipTypes as $class => $type) {
            if (is_a($returnTypeName, $class, true)) {
                return $type;
            }
        }

        return null;
    }

    /**
     * Extract relationship data
     */
    private static function extractRelationshipData(
        string $methodName,
        string $relationshipType,
        \Illuminate\Database\Eloquent\Relations\Relation $relationship,
        string $modelClass
    ): ?array {
        $data = [
            'name' => $methodName,
            'type' => $relationshipType,
        ];

        switch ($relationshipType) {
            case 'belongsTo':
                $data['foreign_key'] = $relationship->getForeignKeyName();
                $data['related_model'] = get_class($relationship->getRelated());
                break;

            case 'hasOne':
            case 'hasMany':
                // For inverse relationships, we need the foreign key on the related model
                $data['foreign_key'] = $relationship->getForeignKeyName();
                $data['related_model'] = get_class($relationship->getRelated());
                break;

            case 'belongsToMany':
                // For many-to-many, we might want to handle pivot IDs
                $data['foreign_key'] = $relationship->getForeignPivotKeyName();
                $data['related_key'] = $relationship->getRelatedPivotKeyName();
                $data['related_model'] = get_class($relationship->getRelated());
                break;

            case 'morphTo':
                $data['morphable_id'] = $relationship->getForeignKeyName();
                $data['morphable_type'] = $relationship->getMorphType();
                break;

            case 'morphOne':
            case 'morphMany':
                $data['morphable_id'] = $relationship->getForeignKeyName();
                $data['morphable_type'] = $relationship->getMorphType();
                $data['related_model'] = get_class($relationship->getRelated());
                break;

            case 'morphToMany':
                $data['morphable_id'] = $relationship->getForeignPivotKeyName();
                $data['morphable_type'] = $relationship->getMorphType();
                $data['related_model'] = get_class($relationship->getRelated());
                break;

            case 'hasManyThrough':
                $data['foreign_key'] = $relationship->getForeignKeyName();
                $data['related_model'] = get_class($relationship->getRelated());
                break;

            default:
                return null;
        }

        return $data;
    }

    /**
     * Get relationship ID properties for Data class
     *
     * @param array $relationships Array of relationship definitions
     * @return array Array of property definitions with validation
     */
    public static function getRelationshipProperties(array $relationships): array
    {
        $properties = [];

        foreach ($relationships as $relationship) {
            switch ($relationship['type']) {
                case 'belongsTo':
                    if (isset($relationship['foreign_key'])) {
                        $properties[] = [
                            'property' => 'public ?int $' . $relationship['foreign_key'] . ';',
                            'validation' => '',
                            'related_model' => $relationship['related_model'] ?? null,
                        ];
                    }
                    break;

                case 'hasOne':
                case 'hasMany':
                    // For inverse relationships, add foreign key if it exists on this model
                    if (isset($relationship['foreign_key']) && str_contains($relationship['foreign_key'], '_id')) {
                        $properties[] = [
                            'property' => 'public ?int $' . $relationship['foreign_key'] . ';',
                            'validation' => '',
                            'related_model' => $relationship['related_model'] ?? null,
                        ];
                    }
                    break;

                case 'belongsToMany':
                    // For many-to-many, add array property for IDs
                    $relatedModelName = self::getModelNameFromClass($relationship['related_model'] ?? '');
                    $propertyName = strtolower($relatedModelName) . '_ids';
                    $properties[] = [
                        'property' => 'public ?array $' . $propertyName . ';',
                        'validation' => '#[Json]',
                        'related_model' => $relationship['related_model'] ?? null,
                    ];
                    break;

                case 'morphTo':
                    if (isset($relationship['morphable_id']) && isset($relationship['morphable_type'])) {
                        $properties[] = [
                            'property' => 'public ?int $' . $relationship['morphable_id'] . ';',
                            'validation' => '',
                            'related_model' => null,
                        ];
                        $properties[] = [
                            'property' => 'public ?string $' . $relationship['morphable_type'] . ';',
                            'validation' => '',
                            'related_model' => null,
                        ];
                    }
                    break;

                case 'morphOne':
                case 'morphMany':
                case 'morphToMany':
                    if (isset($relationship['morphable_id']) && isset($relationship['morphable_type'])) {
                        $properties[] = [
                            'property' => 'public ?int $' . $relationship['morphable_id'] . ';',
                            'validation' => '',
                            'related_model' => $relationship['related_model'] ?? null,
                        ];
                        $properties[] = [
                            'property' => 'public ?string $' . $relationship['morphable_type'] . ';',
                            'validation' => '',
                            'related_model' => null,
                        ];
                    }
                    break;
            }
        }

        return $properties;
    }

    /**
     * Get model name from full class name
     */
    private static function getModelNameFromClass(string $class): string
    {
        $parts = explode('\\', $class);
        return end($parts);
    }
}

