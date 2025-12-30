<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Builders;

use Illuminate\Support\Str;
use Mrmarchone\LaravelAutoCrud\Services\EnumDetector;
use Mrmarchone\LaravelAutoCrud\Services\ModelService;
use Mrmarchone\LaravelAutoCrud\Transformers\EnumTransformer;

class EnumBuilder extends BaseBuilder
{
    public function create(array $modelData, array $values, string $columnName, bool $overwrite = false): string
    {
        $modelClass = ModelService::getFullModelNamespace($modelData);
        $modelName = $modelData['modelName'];
        
        // Check if enum already exists in model casts or migrations
        $existingEnum = EnumDetector::detectExistingEnum($modelClass, $columnName, $modelName);
        
        if ($existingEnum && enum_exists($existingEnum)) {
            // Return existing enum class name - don't generate a new one
            return $existingEnum;
        }
        
        // Generate enum class name: ModelName + ColumnName (PascalCase) + Enum
        $columnNamePascal = Str::studly($columnName);
        $enumClassName = $modelName . $columnNamePascal . 'Enum';
        
        // Create custom modelData with the enum class name
        $enumModelData = $modelData;
        $enumModelData['modelName'] = $enumClassName;
        
        return $this->fileService->createFromStub($enumModelData, 'enum', 'Enums', '', $overwrite, function ($modelData) use ($values) {
            return [
                '{{ data }}' => EnumTransformer::convertDataToString($values),
            ];
        });
    }
}
