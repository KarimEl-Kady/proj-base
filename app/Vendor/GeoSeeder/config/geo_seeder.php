<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Countries To Seed
    |--------------------------------------------------------------------------
    |
    | ISO 3166-1 alpha-2 codes, comma-separated. This is the single place
    | that decides which countries the Country/City seeders (and the
    | `geo:seed` artisan command) act on by default — change it here or via
    | GEO_SEED_COUNTRIES, or override per-run with `--countries=EG,KW`.
    |
    | Data currently ships for: EG (Egypt), KW (Kuwait), AE (UAE), SA (KSA).
    | Add more by dropping a new file in src/Data/{CODE}.php.
    |
    */

    'countries' => array_values(array_filter(explode(',', (string) env('GEO_SEED_COUNTRIES', 'EG,KW,AE,SA')))),

];
