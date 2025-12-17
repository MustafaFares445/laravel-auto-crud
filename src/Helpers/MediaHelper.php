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

final class MediaHelper
{
    /**
     * Handle media upload for single or multiple files.
     *
     * @param  UploadedFile|array<UploadedFile>  $media  The media file(s) to upload
     * @param  HasMedia|Model  $model  The model to associate the media with
     * @param  string  $collection  The media collection name (default: 'default')
     * @return Media|array<Media> Returns the created media object(s)
     *
     * @throws FileDoesNotExist
     * @throws FileIsTooBig
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
     * @param  UploadedFile|array<UploadedFile>|null  $media  The media file(s) to upload
     * @param  HasMedia|Model  $model  The model to associate the media with
     * @param  string  $collection  The media collection name (default: 'default')
     * @return Media|array<Media> Returns the created media object(s), empty array if no media provided
     *
     * @throws FileDoesNotExist
     * @throws FileIsTooBig
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
     * @param  HasMedia  $model  The model containing the media collection
     * @param  string  $collection  The media collection name (default: 'default')
     */
    public static function deleteCollection(HasMedia $model, string $collection = 'default'): void
    {
        $model->clearMediaCollection($collection);
    }

    /**
     * Delete a specific media item after verifying it belongs to the model.
     *
     * @param  HasMedia  $model  The model to verify ownership against
     * @param  Media  $media  The media item to delete
     * @return bool Returns true if deletion was successful, false if media doesn't belong to model
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
     * @param  HasMedia  $model  The model to verify ownership against
     * @param  array<Media>|Collection<Media>  $mediaItems  The media items to delete
     * @return int Number of successfully deleted media items
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
     * Handle media upload for a single file.
     *
     * @param  UploadedFile  $media  The media file to upload
     * @param  HasMedia  $model  The model to associate the media with
     * @param  string  $collection  The media collection name (default: 'default')
     *
     * @throws FileDoesNotExist
     * @throws FileIsTooBig
     */
    private static function upload(UploadedFile $media, HasMedia $model, string $collection = 'default'): Media
    {
        return $model->addMedia($media)->toMediaCollection($collection);
    }

    /**
     * Handle media upload for multiple files.
     *
     * @param  array<UploadedFile>  $media  The media files to upload
     * @param  HasMedia  $model  The model to associate the media with
     * @param  string  $collection  The media collection name (default: 'default')
     * @return array<Media> Returns the created media objects
     *
     * @throws FileDoesNotExist
     * @throws FileIsTooBig
     */
    private static function uploadMany(array $media, HasMedia $model, string $collection = 'default'): array
    {
        return array_map(
            static fn (UploadedFile $file): Media => $model->addMedia($file)->toMediaCollection($collection),
            $media
        );
    }
}
