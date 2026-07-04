<?php

namespace App\Modules\Country\Database\Seeders;

use App\Modules\Country\Models\Country;
use Illuminate\Database\Seeder;
use Local\GeoSeeder\GeoDataRepository;

/**
 * Seeds the countries in config('geo_seeder.countries') (default:
 * EG,KW,AE,SA — override via GEO_SEED_COUNTRIES or `geo:seed --countries=`).
 * Upserts by iso2, so it's safe to run repeatedly.
 */
class CountrySeeder extends Seeder
{
    public function run(GeoDataRepository $geo): void
    {
        foreach (config('geo_seeder.countries', []) as $code) {
            $code = strtoupper($code);

            if (! $geo->has($code)) {
                $this->command?->warn("  Skipping [{$code}] — no geo data shipped for this country.");

                continue;
            }

            $data = $geo->country($code);

            Country::query()->updateOrCreate(
                ['iso2' => $data['iso2']],
                $data
            );
        }
    }
}
