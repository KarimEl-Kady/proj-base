<?php

/*
|--------------------------------------------------------------------------
| City Module — Permission Definitions
|--------------------------------------------------------------------------
|
| Owned by this module and merged into the central definitions by
| local/permission's DefinitionLoader (config/permission.php →
| definition_paths). Apply with: php artisan permission:seed
|
| Reads are public reference data (no permission needed); writes are
| gated by cities.manage in Routes/api.php. cities.view exists for roles
| that should see cities in authenticated/backoffice contexts.
|
*/

return [

    'permissions' => [
        'cities.view',
        'cities.manage',
    ],

];
