<?php

/*
|--------------------------------------------------------------------------
| Geo Module — Permission Definitions
|--------------------------------------------------------------------------
|
| Owned by this module and merged into the central definitions by
| local/permission's DefinitionLoader (config/permission.php →
| definition_paths). Apply with: php artisan permission:seed
|
| Reads are public reference data (no permission needed); writes are
| gated by countries.manage / cities.manage in Routes/api.php. The .view
| permissions exist for roles that should see countries/cities in
| authenticated/backoffice contexts.
|
*/

return [

    'permissions' => [
        'countries.view',
        'countries.manage',
        'cities.view',
        'cities.manage',
    ],

];
