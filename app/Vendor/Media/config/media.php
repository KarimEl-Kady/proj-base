<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    |
    | disk: filesystem disk used to store uploaded media
    | directory: root directory on the disk for all media files
    |
    */

    'disk' => env('MEDIA_DISK', env('FILESYSTEM_DISK', 'local')),
    'directory' => env('MEDIA_DIRECTORY', 'media'),
    'temporary_urls' => env('MEDIA_TEMPORARY_URLS', true),
    'temporary_url_ttl' => (int) env('MEDIA_TEMPORARY_URL_TTL', 5),

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    |
    | max_file_size: maximum upload size in kilobytes
    | allowed_mime_types: mime types accepted by MediaService::store();
    | empty array = accept everything
    |
    */

    'max_file_size' => (int) env('MEDIA_MAX_FILE_SIZE', 10240),

    'allowed_mime_types' => [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'application/pdf',
        'video/mp4',
        'audio/mpeg',
    ],

];
