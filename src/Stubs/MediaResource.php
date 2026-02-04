<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/** @mixin Media */
final class MediaResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'fileName' => $this->file_name,
            'collection' => $this->collection_name,
            'url' => $this->getFullUrl(),
            'thumbnailUrl' => $this->hasGeneratedConversion('thumb')
                ? $this->getFullUrl('thumb')
                : $this->getFullUrl(),
            'size' => $this->human_readable_size,
            'extension' => $this->extension,
            'type' => $this->getTypeFromExtension(),
            'duration' => $this->getCustomProperty('duration'),
            'caption' => $this->getCustomProperty('caption') ?? $this->name,
            'createdAt' => $this->created_at->toDateTimeString()
        ];
    }
}
