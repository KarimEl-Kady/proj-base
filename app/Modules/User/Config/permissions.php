<?php

/*
|--------------------------------------------------------------------------
| User Module — Permission Definitions
|--------------------------------------------------------------------------
|
| Owned by this module and merged into the central definitions by
| local/permission's DefinitionLoader (config/permission.php →
| definition_paths). Apply with: php artisan permission:seed
|
| User records are PII, so each action has its own permission — routes in
| Routes/api.php gate per action, not with a single "manage" bundle.
|
*/

return [

    'permissions' => [
        'users.view',
        'users.create',
        'users.update',
        'users.delete',
    ],

];
