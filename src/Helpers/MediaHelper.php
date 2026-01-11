<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Helpers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileDoesNotExist;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Helper class for managing media uploads with Spatie Media Library.
 *
 * Provides static methods for handling single and multiple file uploads,
 * updates, and deletions with proper ownership verification.
 */
final class MediaHelper
{
    /**
     * Handle media upload for single or multiple files.
     *
     * Automatically detects whether a single file or array of files is provided
     * and handles the upload accordingly. Returns empty array if null is provided.
     *
     * @param  UploadedFile|array<UploadedFile>|null  $media  The media file(s) to upload. Can be a single file, array of files, or null.
     * @param  HasMedia|Model  $model  The model instance that implements HasMedia interface to associate the media with
     * @param  string  $collection  The media collection name (default: 'default')
     * @return Media|array<Media> Returns a single Media object for single file upload, array of Media objects for multiple files, or empty array if null
     *
     * @throws FileDoesNotExist When the file does not exist
     * @throws FileIsTooBig When the file exceeds the maximum allowed size
     *
     * @example
     * // Single file upload
     * $media = MediaHelper::uploadMedia($request->file('image'), $user, 'avatars');
     *
     * // Multiple files upload
     * $media = MediaHelper::uploadMedia($request->file('images'), $post, 'gallery');
     */
    public static function uploadMedia(UploadedFile|array|null $media, HasMedia|Model $model, string $collection = 'default'): Media|array
    {
        if ($media === null) {
            return [];
        }

        if (is_array($media)) {
            if (empty($media)) {
                return [];
            }

            return self::uploadMany($media, $model, $collection);
        }

        return self::upload($media, $model, $collection);
    }

    /**
     * Update media by clearing existing collection and uploading new files.
     *
     * This method first clears all existing media in the specified collection,
     * then uploads the new media file(s). If null is provided, it only clears
     * the collection without uploading new files.
     *
     * @param  UploadedFile|array<UploadedFile>|null  $media  The media file(s) to upload. Can be a single file, array of files, or null to only clear collection.
     * @param  HasMedia|Model  $model  The model instance that implements HasMedia interface to associate the media with
     * @param  string  $collection  The media collection name (default: 'default')
     * @return Media|array<Media> Returns the created media object(s), or empty array if no media provided or collection was cleared
     *
     * @throws FileDoesNotExist When the file does not exist
     * @throws FileIsTooBig When the file exceeds the maximum allowed size
     *
     * @example
     * // Replace all images in collection
     * $media = MediaHelper::updateMedia($request->file('image'), $post, 'images');
     *
     * // Clear collection without uploading new files
     * MediaHelper::updateMedia(null, $post, 'images');
     */
    public static function updateMedia(UploadedFile|array|null $media, HasMedia|Model $model, string $collection = 'default'): Media|array
    {
        $model->clearMediaCollection($collection);

        if ($media === null) {
            return [];
        }

        if (is_array($media)) {
            if (empty($media)) {
                return [];
            }

            return self::uploadMany($media, $model, $collection);
        }

        return self::upload($media, $model, $collection);
    }

    /**
     * Delete all media in a collection.
     *
     * Removes all media files from the specified collection for the given model.
     * This is a convenience method that wraps Spatie Media Library's clearMediaCollection.
     *
     * @param  HasMedia  $model  The model instance that implements HasMedia interface containing the media collection
     * @param  string  $collection  The media collection name to clear (default: 'default')
     *
     * @example
     * // Clear all images in the 'gallery' collection
     * MediaHelper::deleteCollection($post, 'gallery');
     */
    public static function deleteCollection(HasMedia $model, string $collection = 'default'): void
    {
        $model->clearMediaCollection($collection);
    }

    /**
     * Delete a specific media item after verifying it belongs to the model.
     *
     * Before deletion, this method verifies that the media item actually belongs
     * to the specified model to prevent unauthorized deletions. Returns false
     * if the media doesn't belong to the model or if deletion fails.
     *
     * @param  HasMedia  $model  The model instance to verify ownership against
     * @param  Media  $media  The media item to delete
     * @return bool Returns true if deletion was successful, false if media doesn't belong to model or deletion fails
     *
     * @example
     * // Safely delete a media item
     * $deleted = MediaHelper::deleteMedia($user, $mediaItem);
     * if ($deleted) {
     *     // Media was successfully deleted
     * }
     */
    public static function deleteMedia(HasMedia $model, Media $media): bool
    {
        if (! $media->model || ! $media->model->is($model)) {
            return false;
        }

        return $media->delete();
    }

    /**
     * Delete multiple media items after verifying they belong to the model.
     *
     * Iterates through the provided media items and deletes each one after
     * verifying ownership. Only successfully deleted items are counted.
     * Accepts both arrays and Laravel Collections.
     *
     * @param  HasMedia  $model  The model instance to verify ownership against
     * @param  array<Media>|Collection<Media>  $mediaItems  The media items to delete (can be array or Collection)
     * @return int Number of successfully deleted media items (0 if none were deleted)
     *
     * @example
     * // Delete multiple media items
     * $deletedCount = MediaHelper::deleteManyMedia($post, $mediaItems);
     * // Returns: 3 (if 3 items were successfully deleted)
     */
    public static function deleteManyMedia(HasMedia $model, array|Collection $mediaItems): int
    {
        $deleted = 0;
        $items = $mediaItems instanceof Collection ? $mediaItems->all() : $mediaItems;

        foreach ($items as $media) {
            if (self::deleteMedia($model, $media)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * Attach media to models with optimized database operations.
     *
     * File uploads are still O(n) - this is unavoidable with Spatie.
     * This method optimizes database queries only.
     *
     * @param  Collection<Model>|array<Model>  $models
     * @param  Collection<object>|array<object>  $dataObjects
     * @param  array<string, string>  $mediaMap  ['propertyName' => 'collectionName']
     * @param  bool  $isUpdate  Clear existing media before upload
     * @return void
     */
    public static function attachBulkMedia(
        Collection|array $models,
        Collection|array $dataObjects,
        array $mediaMap,
        bool $isUpdate = false
    ): void {
        $modelsCollection = $models instanceof Collection ? $models : collect($models);
        $dataCollection = $dataObjects instanceof Collection ? $dataObjects : collect($dataObjects);

        if ($modelsCollection->isEmpty() || empty($mediaMap)) {
            return;
        }

        $modelClass = $modelsCollection->first()::class;
        $modelIds = $modelsCollection->pluck('id')->all();

        // Optimize: Batch delete all media collections at once
        if ($isUpdate) {
            $collectionNames = array_values($mediaMap);
            
            Media::whereIn('model_id', $modelIds)
                ->where('model_type', $modelClass)
                ->whereIn('collection_name', $collectionNames)
                ->delete();
        }

        // File uploads - O(n) complexity is unavoidable
        $modelsCollection->each(function ($model, $index) use ($dataCollection, $mediaMap) {
            $dataObject = $dataCollection->get($index);

            if ($dataObject) {
                foreach ($mediaMap as $property => $collection) {
                    if (isset($dataObject->$property)) {
                        self::uploadMedia($dataObject->$property, $model, $collection);
                    }
                }
            }
        });
    }


    /**
     * Handle media upload for a single file.
     *
     * Internal method used by uploadMedia() to handle single file uploads.
     * Uses Spatie Media Library's addMedia() and toMediaCollection() methods.
     *
     * @param  UploadedFile  $media  The media file to upload
     * @param  HasMedia  $model  The model instance that implements HasMedia interface to associate the media with
     * @param  string  $collection  The media collection name (default: 'default')
     * @return Media Returns the created Media object
     *
     * @throws FileDoesNotExist When the file does not exist
     * @throws FileIsTooBig When the file exceeds the maximum allowed size
     */
    private static function upload(UploadedFile $media, HasMedia $model, string $collection = 'default'): Media
    {
        return $model->addMedia($media)->toMediaCollection($collection);
    }

    /**
     * Handle media upload for multiple files.
     *
     * Internal method used by uploadMedia() to handle multiple file uploads.
     * Maps over the array of files and uploads each one to the specified collection.
     *
     * @param  array<UploadedFile>  $media  The array of media files to upload
     * @param  HasMedia  $model  The model instance that implements HasMedia interface to associate the media with
     * @param  string  $collection  The media collection name (default: 'default')
     * @return array<Media> Returns an array of created Media objects
     *
     * @throws FileDoesNotExist When any file does not exist
     * @throws FileIsTooBig When any file exceeds the maximum allowed size
     */
    private static function uploadMany(array $media, HasMedia $model, string $collection = 'default'): array
    {
        return array_map(
            static fn (UploadedFile $file): Media => $model->addMedia($file)->toMediaCollection($collection),
            $media
        );
    }
}
