<?php

namespace Local\Media\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property string $collection
 * @property string $name
 * @property string $file_name
 * @property string $mime_type
 * @property string $disk
 * @property string $path
 * @property int $size
 * @property array $custom_properties
 */
class Media extends Model
{
    protected $table = 'media';

    protected $guarded = ['id', 'uuid'];

    protected function casts(): array
    {
        return [
            'custom_properties' => 'array',
            'size' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Media $media) {
            $media->uuid ??= (string) Str::uuid();
        });

        // Remove the underlying file when the record is deleted.
        static::deleted(function (Media $media) {
            Storage::disk($media->disk)->delete($media->path);
        });
    }

    public function mediable(): MorphTo
    {
        return $this->morphTo();
    }

    public function url(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    public function contents(): ?string
    {
        return Storage::disk($this->disk)->get($this->path);
    }

    public function humanReadableSize(): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $size = (float) $this->size;
        $step = 0;

        while ($size >= 1024 && $step < count($units) - 1) {
            $size /= 1024;
            $step++;
        }

        return round($size, 2).' '.$units[$step];
    }
}
