# Media

Polymorphic media/attachments package, installed as a **local path package** from `app/Vendor/Media`.

## Install

```bash
composer require local/media:"^1.0"
php artisan migrate
```

The service provider is auto-discovered (`extra.laravel.providers`), migrations load automatically, and the config can be published:

```bash
php artisan vendor:publish --tag=media-config
```

## Usage

Add the trait to any Eloquent model:

```php
use Local\Media\Traits\HasMedia;
use Local\Media\Contracts\Mediable;

class Post extends Model implements Mediable
{
    use HasMedia;
}
```

Then:

```php
// Upload (validates size + mime type per config/media.php)
$post->addMedia($request->file('cover'), collection: 'covers');

// Retrieve
$post->getMedia('covers');          // Collection<Media>
$post->getFirstMediaUrl('covers');  // public URL or null

// Delete (also removes the file from disk)
$post->clearMedia('covers');
```

Or use the service directly:

```php
app(Local\Media\Services\MediaService::class)->store($file, $post, 'gallery', ['alt' => '...']);
```

## Config (`config/media.php`)

| Key | Env | Default |
| --- | --- | --- |
| `disk` | `MEDIA_DISK` | `FILESYSTEM_DISK` (`local`) |
| `directory` | `MEDIA_DIRECTORY` | `media` |
| `temporary_urls` | `MEDIA_TEMPORARY_URLS` | `true` |
| `temporary_url_ttl` (minutes) | `MEDIA_TEMPORARY_URL_TTL` | `5` |
| `max_file_size` (KB) | `MEDIA_MAX_FILE_SIZE` | `10240` |
| `allowed_mime_types` | — | raster images, pdf, mp4, mp3 |

Stored extensions are derived from server-detected MIME data, never the client
filename. SVG is excluded from the public-disk defaults because active SVG
content can execute in a browser; projects that need SVG should sanitize it and
serve it from an isolated origin.

When hosted by this application, `AppServiceProvider` binds the package's
`TenantResolver` seam to Core tenancy. Media rows are then fail-closed and
scoped by tenant, while files are stored below `media/tenants/{tenant-id}/`.
URLs are signed and short-lived by default; direct public URLs require an
explicit `MEDIA_DISK=public` and `MEDIA_TEMPORARY_URLS=false` decision.
Standalone package consumers receive the unscoped `NullTenantResolver` and can
bind their own resolver without importing host application classes.
