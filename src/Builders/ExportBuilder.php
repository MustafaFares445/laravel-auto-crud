<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Builders;

use Mrmarchone\LaravelAutoCrud\Services\TableColumnsService;

class ExportBuilder extends BaseBuilder
{
    protected TableColumnsService $tableColumnsService;

    public function __construct($fileService, TableColumnsService $tableColumnsService)
    {
        parent::__construct($fileService);
        $this->tableColumnsService = $tableColumnsService;
    }

    /**
     * Create an export class for a model.
     *
     * @param array<string, mixed> $modelData Model information
     * @param bool $overwrite Whether to overwrite existing files
     * @return string Full namespace path of created export class
     */
    public function createExport(array $modelData, bool $overwrite = false): string
    {
        $exportNamespace = 'App\\Exports';
        if ($modelData['folders']) {
            $exportNamespace .= '\\' . str_replace('/', '\\', $modelData['folders']);
        }

        $className = $modelData['modelName'] . 'Export';
        
        return $this->fileService->createFromStub(
            $modelData,
            'export',
            'Exports',
            'Export',
            $overwrite,
            function ($modelData) use ($exportNamespace, $className) {
                $model = $this->getFullModelNamespace($modelData);
                $modelInstance = new $model;
                $tableName = $modelInstance->getTable();
                $columns = $this->tableColumnsService->getAvailableColumns($tableName, ['created_at', 'updated_at'], $model);
                
                $headers = $this->generateHeaders($columns);
                $mappings = $this->generateMappings($columns);

                return [
                    '{{ namespace }}' => $exportNamespace,
                    '{{ class }}' => $className,
                    '{{ modelNamespace }}' => $model,
                    '{{ model }}' => $modelData['modelName'],
                    '{{ headers }}' => $headers,
                    '{{ mappings }}' => $mappings,
                ];
            }
        );
    }

    /**
     * Generate headers for export.
     *
     * @param array<int, array<string, mixed>> $columns
     * @return string Generated headers code
     */
    private function generateHeaders(array $columns): string
    {
        $headers = [];
        foreach ($columns as $column) {
            $headers[] = "            '" . ucwords(str_replace('_', ' ', $column['name'])) . "'";
        }
        return implode(",\n", $headers);
    }

    /**
     * Generate column mappings for export.
     *
     * @param array<int, array<string, mixed>> $columns
     * @return string Generated mappings code
     */
    private function generateMappings(array $columns): string
    {
        $mappings = [];
        foreach ($columns as $column) {
            $mappings[] = "            \$row->" . $column['name'];
        }
        return implode(",\n", $mappings);
    }
}

