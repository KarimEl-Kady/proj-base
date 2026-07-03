# Media

Polymorphic media/attachments package, installed as a **local path package** from `app/Vendor/Media`.

## Install

```bash
composer require local/media:"*"
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

class Post extends Model
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
| `disk` | `MEDIA_DISK` | `public` |
| `directory` | `MEDIA_DIRECTORY` | `media` |
| `max_file_size` (KB) | `MEDIA_MAX_FILE_SIZE` | `10240` |
| `allowed_mime_types` | — | images, pdf, mp4, mp3 |
