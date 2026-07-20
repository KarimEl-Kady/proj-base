<?php

namespace App\Modules\Geo\Database\Seeders;

use App\Modules\Geo\Models\City;
use App\Modules\Geo\Models\Country;
use Illuminate\Database\Seeder;
use Local\GeoSeeder\GeoDataRepository;

/**
 * Seeds cities for the countries in config('geo_seeder.countries') — run
 * after CountrySeeder, since it looks up each country by iso2. Upserts by
 * (country_id, name), so it's safe to run repeatedly.
 */
class CitySeeder extends Seeder
{
    public function run(GeoDataRepository $geo): void
    {
        foreach (config('geo_seeder.countries', []) as $code) {
            $code = strtoupper($code);

            if (! $geo->has($code)) {
                continue; // CountrySeeder already warned about this one
            }

            $country = Country::query()->where('iso2', $code)->first();

            if ($country === null) {
                $this->command?->warn("  Skipping cities for [{$code}] — country not seeded yet.");

                continue;
            }

            foreach ($geo->cities($code) as $city) {
                City::query()->updateOrCreate(
                    ['country_id' => $country->id, 'name' => $city['name']],
                    $city + ['country_id' => $country->id]
                );
            }
        }
    }
}
