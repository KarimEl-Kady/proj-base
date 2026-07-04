<?php

namespace App\Modules\Country\Commands;

use App\Modules\City\Database\Seeders\CitySeeder;
use App\Modules\City\Models\City;
use App\Modules\Country\Database\Seeders\CountrySeeder;
use App\Modules\Country\Models\Country;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Local\GeoSeeder\GeoDataRepository;

/**
 * Convenience wrapper around CountrySeeder + CitySeeder that reports
 * exactly which countries are about to be seeded before doing it — the
 * "detect easily which countries to seed" entry point.
 */
class SeedGeoDataCommand extends Command
{
    protected $signature = 'geo:seed
                            {--countries= : Comma-separated ISO2 codes, overrides config/env for this run (e.g. EG,KW)}
                            {--fresh : Delete existing rows for these countries before seeding}';

    protected $description = 'Seed countries and cities from the local/geo-seeder package';

    public function handle(GeoDataRepository $geo): int
    {
        $codes = $this->resolveCodes();

        if ($codes === []) {
            $this->error('No country codes given — set GEO_SEED_COUNTRIES, config/geo_seeder.php, or pass --countries=.');

            return self::FAILURE;
        }

        $this->reportPlan($geo, $codes);

        if ($this->option('fresh')) {
            $this->wipe($codes);
        }

        // Scope the seeders to exactly this run's codes, even when they
        // came from --countries instead of config/env.
        config(['geo_seeder.countries' => $codes]);

        Artisan::call('db:seed', ['--class' => CountrySeeder::class, '--force' => true]);
        Artisan::call('db:seed', ['--class' => CitySeeder::class, '--force' => true]);

        $this->reportResult($codes);

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    protected function resolveCodes(): array
    {
        $raw = $this->option('countries') ?? implode(',', config('geo_seeder.countries', []));

        return collect(explode(',', $raw))
            ->map(fn (string $code) => strtoupper(trim($code)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $codes
     */
    protected function reportPlan(GeoDataRepository $geo, array $codes): void
    {
        $rows = collect($codes)->map(function (string $code) use ($geo) {
            if (! $geo->has($code)) {
                return [$code, '—', '<fg=red>no data — will be skipped</>', '—'];
            }

            $country = $geo->country($code);

            return [$code, $country['name'], '<fg=green>ok</>', count($geo->cities($code))];
        });

        $this->info('Planning to seed:');
        $this->table(['Code', 'Country', 'Data', 'Cities'], $rows->all());
    }

    /**
     * @param  array<int, string>  $codes
     */
    protected function wipe(array $codes): void
    {
        $countryIds = Country::query()->whereIn('iso2', $codes)->pluck('id');

        City::query()->whereIn('country_id', $countryIds)->delete();
        Country::query()->whereIn('iso2', $codes)->delete();

        $this->line('Existing rows for these countries removed.');
    }

    /**
     * @param  array<int, string>  $codes
     */
    protected function reportResult(array $codes): void
    {
        $countries = Country::query()->whereIn('iso2', $codes)->count();
        $cities = City::query()->whereHas('country', fn ($q) => $q->whereIn('iso2', $codes))->count();

        $this->newLine();
        $this->info("Done — {$countries} countries, {$cities} cities seeded.");
    }
}
