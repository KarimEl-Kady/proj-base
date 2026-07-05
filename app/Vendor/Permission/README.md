# Permission

Roles and permissions (database-backed, cached, middleware-ready), installed as a **local path package** from `app/Vendor/Permission`.

## Install

```bash
composer require local/permission:"*"
php artisan migrate
```

Publish the config to customize table names, cache settings, or your project's roles/permissions:

```bash
php artisan vendor:publish --tag=permission-config
```

## Attach to a model

```php
use Local\Permission\Traits\HasRolesAndPermissions;

class User extends Model
{
    use HasRolesAndPermissions; // or HasRoles / HasPermissions individually
}
```

## Usage

```php
$user->assignRole('admin');                 // creates the role if it doesn't exist
$user->assignRole('admin', 'manager');       // multiple at once
$user->hasRole('admin');                     // bool
$user->hasAnyRole('admin', 'manager');       // bool
$user->hasAllRoles('admin', 'manager');      // bool
$user->removeRole('admin');
$user->syncRoles('manager');                 // replaces the full set

$user->givePermissionTo('posts.create');     // direct grant, bypasses roles
$user->hasPermissionTo('posts.create');      // direct OR via any assigned role
$user->hasDirectPermission('posts.create');  // direct only
$user->hasAnyPermission('posts.create', 'posts.update');
$user->getAllPermissions();                  // Collection<string>, direct + via roles, deduplicated

$role = Local\Permission\Models\Role::findOrCreate('editor');
$role->givePermissionTo('posts.create', 'posts.update');
```

## Route middleware

Three aliases are registered automatically:

```php
Route::get('/admin', ...)->middleware('role:admin');
Route::get('/admin', ...)->middleware('role:admin|manager');       // any of
Route::post('/posts', ...)->middleware('permission:posts.create');
Route::post('/posts', ...)->middleware('permission:posts.create|posts.update');
Route::get('/x', ...)->middleware('role_or_permission:admin|posts.create'); // either kind
```

A failed check throws `Local\Permission\Exceptions\UnauthorizedException` (extends Laravel's own `AuthorizationException`), so the host app's exception handler renders it as a normal 403 with no wiring needed — `App\Modules\Core\Exceptions\Handler` already matches on `AuthorizationException`.

## Which roles/permissions exist — `config/permission.php`

The **single place** that declares what should exist in a project, mirroring `local/geo-seeder`'s `config('geo_seeder.countries')` convention:

```php
'definitions' => [
    'permissions' => [
        'users.view', 'users.create', 'users.update', 'users.delete',
    ],
    'roles' => [
        'admin' => ['*'],                    // every permission defined above
        'manager' => ['users.view'],
    ],
],
```

Sync the database to it:

```bash
php artisan permission:seed              # reports the plan, then creates/updates
php artisan permission:seed --fresh      # also deletes existing roles/permissions first
                                          # (asks to confirm — it strips every model's
                                          # assignments too — pass --force to skip the prompt)
php artisan permission:list              # what's actually in the database right now
```

`permission:seed` is idempotent — `findOrCreate` + full `sync()`, safe to run on every deploy.

## Design notes

- **Guard-aware but simple by default.** `guard_name` defaults to `config('auth.defaults.guard')` (usually `web`) when not given — set explicitly if a project genuinely needs separate roles per guard (e.g. `Role::findOrCreate('admin', 'sanctum')`). It's intentionally **not** nullable in the schema: SQL unique constraints treat every `NULL` as distinct, which would silently defeat the `(name, guard_name)` uniqueness check.
- **Cached as one unit.** The whole role → permission-name map is cached together (it's small and read on every check), not per-model. Anything that writes to roles/permissions automatically flushes it — you never call `forgetCache()` yourself.
- **No models of the host app referenced.** This package only knows about `Illuminate\Database\Eloquent\Model` — attach the traits to whatever models you want (`User`, or anything else with an integer primary key).
