<?php

namespace Local\Media\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Local\Media\Contracts\TenantResolver;
use RuntimeException;

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
 * @property int|string|null $tenant_id
 * @property array $custom_properties
 */
class Media extends Model
{
    protected $table = 'media';

    protected $guarded = ['id', 'uuid', 'tenant_id'];

    protected function casts(): array
    {
        return [
            'custom_properties' => 'array',
            'size' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder): void {
            $resolver = app(TenantResolver::class);

            if (! $resolver->enabled()) {
                return;
            }

            $tenantId = $resolver->id();

            if ($tenantId === null) {
                throw new RuntimeException('A tenant context is required to query media.');
            }

            $builder->where($builder->qualifyColumn('tenant_id'), $tenantId);
        });

        static::creating(function (Media $media) {
            $resolver = app(TenantResolver::class);
            $tenantId = $resolver->id();

            if ($resolver->enabled() && $tenantId === null) {
                throw new RuntimeException('A tenant context is required to create media.');
            }

            $media->tenant_id = $tenantId;
            $media->uuid ??= (string) Str::uuid();
        });

        // Remove the underlying file when the record is deleted.
        static::deleted(function (Media $media) {
            Storage::disk($media->disk)->delete($media->path);
        });

        static::deleting(fn (Media $media) => $media->ensureTenantAccess());
    }

    public function mediable(): MorphTo
    {
        return $this->morphTo();
    }

    public function url(): string
    {
        $this->ensureTenantAccess();
        $disk = Storage::disk($this->disk);

        if (config('media.temporary_urls', true)) {
            return $disk->temporaryUrl(
                $this->path,
                now()->addMinutes(max(1, (int) config('media.temporary_url_ttl', 5))),
            );
        }

        return $disk->url($this->path);
    }

    public function contents(): ?string
    {
        $this->ensureTenantAccess();

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

    protected function ensureTenantAccess(): void
    {
        $resolver = app(TenantResolver::class);

        if ($resolver->enabled() && (string) $resolver->id() !== (string) $this->tenant_id) {
            throw new RuntimeException('Media belongs to a different tenant.');
        }
    }
}
