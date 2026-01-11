<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

abstract class BulkService
{
    /**
     * Model class name to work with
     */
    protected string $modelClass;

    /**
     * Unique key for upsert operations (null if no unique key exists)
     */
    protected ?string $uniqueKey = null;

    /**
     * Maximum number of records allowed per bulk operation
     */
    protected int $maxBulkSize = 100;

    /**
     * Store multiple records with validation and business logic.
     *
     * @param  array<array>  $dataArray  Raw array of data from request
     * @return Collection<Model>
     * @throws ValidationException
     * @throws Throwable
     */
    public function store(array $dataArray): Collection
    {
        $this->validateStore($dataArray);

        $dataObjects = $this->transformToDataObjects($dataArray);

        $models = $this->bulkStore($dataObjects);

        $this->afterStore($models);

        return $models;
    }

    /**
     * Update multiple records with validation and business logic.
     *
     * @param  array<array>  $dataArray  Raw array of data from request
     * @return Collection<Model>
     * @throws ValidationException
     * @throws Throwable
     */
    public function update(array $dataArray): Collection
    {
        $ids = collect($dataArray)->pluck('id')->all();
        $existingModels = $this->modelClass::whereIn('id', $ids)->get()->keyBy('id');

        $this->validateModelsExist($ids, $existingModels);

        $this->validateUpdate($dataArray, $existingModels);

        $dataObjects = $this->transformToDataObjects($dataArray);

        $orderedModels = collect($dataArray)->map(fn ($data) => $existingModels->get($data['id']));

        $updatedModels = $this->bulkUpdate($dataObjects, $orderedModels);

        $this->afterUpdate($updatedModels);

        return $updatedModels;
    }

    /**
     * Delete multiple records with validation and business logic.
     *
     * @param  array<int>  $ids  Array of IDs to delete
     * @return int Number of deleted records
     * @throws ValidationException
     * @throws Throwable
     */
    public function delete(array $ids): int
    {
        $models = $this->modelClass::whereIn('id', $ids)->get();

        $this->validateDelete($models, $ids);

        $this->beforeDelete($models);

        $deletedCount = $this->bulkDelete($models);

        $this->afterDelete($models);

        return $deletedCount;
    }

    /**
     * Bulk create records.
     *
     * @param  array<object>|Collection<object>  $dataObjects
     * @return Collection<Model>
     * @throws Throwable
     */
    protected function bulkStore(array|Collection $dataObjects): Collection
    {
        $data = $dataObjects instanceof Collection ? $dataObjects : collect($dataObjects);

        return DB::transaction(function () use ($data) {
            $models = collect();

            if ($this->uniqueKey === null) {
                foreach ($data as $dataObject) {
                    $model = $this->modelClass::create($dataObject->onlyModelAttributes());
                    $models->push($model);
                }
            } else {
                $insertData = $data->map(fn ($dataObject) => array_merge(
                    $dataObject->onlyModelAttributes(),
                    [
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                ))->all();

                $this->modelClass::insert($insertData);

                $uniqueValues = $data->pluck($this->uniqueKey)->all();
                $retrievedModels = $this->modelClass::whereIn($this->uniqueKey, $uniqueValues)
                    ->get()
                    ->keyBy($this->uniqueKey);

                $models = $data->map(fn ($dataObject) =>
                    $retrievedModels->get($dataObject->{$this->uniqueKey})
                )->filter();
            }

            return $models;
        });
    }

    /**
     * Bulk update records.
     *
     * @param  array<object>|Collection<object>  $dataObjects
     * @param  array<Model>|Collection<Model>  $models
     * @return Collection<Model>
     * @throws Throwable
     */
    protected function bulkUpdate(array|Collection $dataObjects, array|Collection $models): Collection
    {
        $data = $dataObjects instanceof Collection ? $dataObjects : collect($dataObjects);
        $modelsCollection = $models instanceof Collection ? $models : collect($models);

        if ($data->count() !== $modelsCollection->count()) {
            throw new \InvalidArgumentException('The number of models and data objects must match');
        }

        return DB::transaction(function () use ($data, $modelsCollection) {
            $upsertData = $data->map(fn ($dataObject, $index) => array_merge(
                $dataObject->onlyModelAttributes(),
                ['id' => $modelsCollection->get($index)->id],
                ['updated_at' => now()]
            ))->all();

            $this->modelClass::upsert(
                $upsertData,
                ['id'],
                array_keys($upsertData[0])
            );

            $modelsCollection->each->refresh();

            return $modelsCollection;
        });
    }

    /**
     * Bulk delete records.
     *
     * @param  array<Model>|Collection<Model>  $models
     * @return int Number of deleted records
     * @throws Throwable
     */
    protected function bulkDelete(array|Collection $models): int
    {
        $modelsCollection = $models instanceof Collection ? $models : collect($models);
        $ids = $modelsCollection->pluck('id')->all();

        return DB::transaction(fn () => $this->modelClass::whereIn('id', $ids)->delete());
    }

    /**
     * Transform raw data array to data objects.
     * Override this method in child classes to customize transformation.
     *
     * @param  array<array>  $dataArray
     * @return Collection<object>
     */
    protected function transformToDataObjects(array $dataArray): Collection
    {
        return collect($dataArray);
    }

    /**
     * Validate bulk store business rules.
     * Override this method in child classes to add custom validation.
     *
     * @param  array<array>  $dataArray
     * @return void
     * @throws ValidationException
     */
    protected function validateStore(array $dataArray): void
    {
        if (count($dataArray) > $this->maxBulkSize) {
            throw ValidationException::withMessages([
                'data' => ["Cannot create more than {$this->maxBulkSize} records at once"],
            ]);
        }

        if ($this->uniqueKey) {
            $uniqueValues = collect($dataArray)->pluck($this->uniqueKey);
            $duplicates = $uniqueValues->duplicates();

            if ($duplicates->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'data' => ["Duplicate {$this->uniqueKey} found in batch: {$duplicates->implode(', ')}"],
                ]);
            }

            $existing = $this->modelClass::whereIn($this->uniqueKey, $uniqueValues->all())
                ->pluck($this->uniqueKey);

            if ($existing->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'data' => ["{$this->uniqueKey} already exist: {$existing->implode(', ')}"],
                ]);
            }
        }
    }

    /**
     * Validate bulk update business rules.
     * Override this method in child classes to add custom validation.
     *
     * @param  array<array>  $dataArray
     * @param  Collection<Model>  $existingModels
     * @return void
     * @throws ValidationException
     */
    protected function validateUpdate(array $dataArray, Collection $existingModels): void
    {
        if ($this->uniqueKey) {
            $uniqueValuesToUpdate = collect($dataArray)
                ->filter(fn ($data) => isset($data[$this->uniqueKey]))
                ->pluck($this->uniqueKey, 'id');

            $duplicates = $uniqueValuesToUpdate->duplicates();

            if ($duplicates->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'data' => ["Duplicate {$this->uniqueKey} found in batch: {$duplicates->implode(', ')}"],
                ]);
            }

            foreach ($uniqueValuesToUpdate as $id => $uniqueValue) {
                $conflict = $this->modelClass::where($this->uniqueKey, $uniqueValue)
                    ->where('id', '!=', $id)
                    ->exists();

                if ($conflict) {
                    throw ValidationException::withMessages([
                        'data' => ["{$this->uniqueKey} '{$uniqueValue}' is already taken by another record"],
                    ]);
                }
            }
        }
    }

    /**
     * Validate bulk delete business rules.
     * Override this method in child classes to add custom validation.
     *
     * @param  Collection<Model>  $models
     * @param  array<int>  $ids
     * @return void
     * @throws ValidationException
     */
    protected function validateDelete(Collection $models, array $ids): void
    {
        if ($models->count() !== count($ids)) {
            $foundIds = $models->pluck('id')->all();
            $missingIds = array_diff($ids, $foundIds);

            throw ValidationException::withMessages([
                'ids' => ["Records not found with IDs: " . implode(', ', $missingIds)],
            ]);
        }
    }

    /**
     * Validate that all model IDs exist.
     *
     * @param  array<int>  $ids
     * @param  Collection<Model>  $existingModels
     * @return void
     * @throws ValidationException
     */
    protected function validateModelsExist(array $ids, Collection $existingModels): void
    {
        if ($existingModels->count() !== count($ids)) {
            $foundIds = $existingModels->pluck('id')->all();
            $missingIds = array_diff($ids, $foundIds);

            throw ValidationException::withMessages([
                'data' => ["Records not found with IDs: " . implode(', ', $missingIds)],
            ]);
        }
    }

    /**
     * Business logic to execute after bulk store.
     * Override this method in child classes to add custom logic.
     *
     * @param  Collection<Model>  $models
     * @return void
     */
    protected function afterStore(Collection $models): void
    {
    }

    /**
     * Business logic to execute after bulk update.
     * Override this method in child classes to add custom logic.
     *
     * @param  Collection<Model>  $models
     * @return void
     */
    protected function afterUpdate(Collection $models): void
    {
    }

    /**
     * Business logic to execute before bulk delete.
     * Override this method in child classes to add custom logic.
     *
     * @param  Collection<Model>  $models
     * @return void
     */
    protected function beforeDelete(Collection $models): void
    {
    }

    /**
     * Business logic to execute after bulk delete.
     * Override this method in child classes to add custom logic.
     *
     * @param  Collection<Model>  $models
     * @return void
     */
    protected function afterDelete(Collection $models): void
    {
    }
}