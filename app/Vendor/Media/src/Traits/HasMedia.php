<?php

namespace Local\Media\Traits;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\UploadedFile;
use Local\Media\Models\Media;
use Local\Media\Services\MediaService;

trait HasMedia
{
    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable');
    }

    /**
     * @param  array<string, mixed>  $customProperties
     */
    public function addMedia(
        UploadedFile $file,
        string $collection = 'default',
        array $customProperties = [],
    ): Media {
        return app(MediaService::class)->store($file, $this, $collection, $customProperties);
    }

    public function getMedia(string $collection = 'default'): Collection
    {
        return $this->media()->where('collection', $collection)->get();
    }

    public function getFirstMedia(string $collection = 'default'): ?Media
    {
        return $this->media()->where('collection', $collection)->first();
    }

    public function getFirstMediaUrl(string $collection = 'default'): ?string
    {
        return $this->getFirstMedia($collection)?->url();
    }

    public function clearMedia(string $collection = 'default'): void
    {
        $this->getMedia($collection)->each->delete();
    }
}
