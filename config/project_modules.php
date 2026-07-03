<?php

/*
|--------------------------------------------------------------------------
| Module Registry
|--------------------------------------------------------------------------
|
| Single source of truth for which HMVC modules are active. Toggle the
| boolean by hand or via artisan: module:enable, module:disable,
| module:delete. New modules created with make:module are registered
| here automatically. Keep the simple `'Name' => bool` format — the
| file is rewritten by those commands.
|
*/

return [
    'Auth' => true,
    'User' => true,
];
