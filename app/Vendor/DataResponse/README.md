# DataResponse

Standardized JSON response envelope, installed as a **local path package** from `app/Vendor/DataResponse`.

## Install

```bash
composer require local/data-response:"*"
```

The service provider is auto-discovered. Publish the config to customize key names or default messages:

```bash
php artisan vendor:publish --tag=data-response-config
```

## Usage

Static calls anywhere:

```php
use Local\DataResponse\DataResponse;

return DataResponse::success($user, 'User created.', 201);
return DataResponse::error('Not found.', 404);
```

Or drop the trait into a controller for `jsonResponse()`/`jsonError()` helpers:

```php
use Local\DataResponse\Concerns\BuildsDataResponses;

class UserController extends Controller
{
    use BuildsDataResponses;

    public function show($id)
    {
        return $this->jsonResponse(new UserResource($user), 'User retrieved.');
    }
}
```

`App\Modules\Core\Controllers\Controller` already uses this trait, so every module controller gets `jsonResponse()`/`jsonError()` for free.

## Envelope

```json
{ "success": true, "message": "User created.", "data": { ... } }
{ "success": false, "message": "Not found.", "errors": { ... } }
```

## Config (`config/data_response.php`)

| Key | Default | Purpose |
| --- | --- | --- |
| `keys.success` / `message` / `data` / `errors` | `success` / `message` / `data` / `errors` | Rename the top-level envelope keys project-wide |
| `messages.success` / `error` | `Success` / `Error` | Fallback message when none is passed |
