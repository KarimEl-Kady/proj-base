<?php

namespace Local\Media\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Local\Media\Contracts\Mediable;
use Local\Media\Contracts\TenantResolver;
use Local\Media\Models\Media;

class MediaService
{
    public function __construct(protected TenantResolver $tenantResolver) {}

    /**
     * Store an uploaded file and attach it to a model.
     *
     * @param  Model&Mediable  $model
     * @param  array<string, mixed>  $customProperties
     */
    public function store(
        UploadedFile $file,
        Model $model,
        string $collection = 'default',
        array $customProperties = [],
    ): Media {
        $this->validate($file, $collection);

        $disk = config('media.disk', 'public');
        $directory = trim(config('media.directory', 'media'), '/');
        $tenantId = $this->tenantResolver->id();

        if ($this->tenantResolver->enabled() && $tenantId === null) {
            throw new InvalidArgumentException('A tenant context is required to store media.');
        }

        if ($tenantId !== null) {
            $directory .= '/tenants/'.rawurlencode((string) $tenantId);
        }

        $extension = $file->extension() ?: 'bin';
        $fileName = Str::uuid().'.'.$extension;
        $path = $file->storeAs("{$directory}/{$collection}", $fileName, $disk);

        $media = new Media([
            'collection' => $collection,
            'tenant_id' => $tenantId,
            'name' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'file_name' => $fileName,
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'disk' => $disk,
            'path' => $path,
            'size' => $file->getSize() ?: 0,
            'custom_properties' => $customProperties,
        ]);

        try {
            $model->media()->save($media);
        } catch (\Throwable $e) {
            Storage::disk($disk)->delete($path);

            throw $e;
        }

        return $media;
    }

    /**
     * Delete a media record (the model event also removes the file).
     */
    public function delete(Media $media): bool
    {
        return (bool) $media->delete();
    }

    protected function validate(UploadedFile $file, string $collection): void
    {
        if (preg_match('/^[A-Za-z0-9_-]{1,64}$/', $collection) !== 1) {
            throw new InvalidArgumentException('Media collection names may contain only letters, numbers, dashes, and underscores.');
        }

        $maxKilobytes = (int) config('media.max_file_size', 10240);

        if ($file->getSize() > $maxKilobytes * 1024) {
            throw new InvalidArgumentException(
                "File exceeds the maximum allowed size of {$maxKilobytes} KB."
            );
        }

        $allowed = config('media.allowed_mime_types', []);

        if ($allowed !== [] && ! in_array($file->getMimeType(), $allowed)) {
            throw new InvalidArgumentException(
                "File type [{$file->getMimeType()}] is not allowed."
            );
        }
    }
}
