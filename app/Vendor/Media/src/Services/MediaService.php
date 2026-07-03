<?php

namespace Local\Media\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Local\Media\Models\Media;

class MediaService
{
    /**
     * Store an uploaded file and attach it to a model.
     *
     * @param  array<string, mixed>  $customProperties
     */
    public function store(
        UploadedFile $file,
        Model $model,
        string $collection = 'default',
        array $customProperties = [],
    ): Media {
        $this->validate($file);

        $disk = config('media.disk', 'public');
        $directory = trim(config('media.directory', 'media'), '/');

        $fileName = Str::uuid().'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs("{$directory}/{$collection}", $fileName, $disk);

        return $model->media()->create([
            'collection' => $collection,
            'name' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'file_name' => $fileName,
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'disk' => $disk,
            'path' => $path,
            'size' => $file->getSize() ?: 0,
            'custom_properties' => $customProperties,
        ]);
    }

    /**
     * Delete a media record (the model event also removes the file).
     */
    public function delete(Media $media): bool
    {
        return (bool) $media->delete();
    }

    protected function validate(UploadedFile $file): void
    {
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
