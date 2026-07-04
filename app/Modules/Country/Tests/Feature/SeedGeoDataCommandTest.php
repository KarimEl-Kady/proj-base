<?php

namespace App\Modules\Country\Tests\Feature;

use App\Modules\City\Models\City;
use App\Modules\Country\Models\Country;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeedGeoDataCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! in_array('Country', config('project.modules')) || ! in_array('City', config('project.modules'))) {
            $this->markTestSkipped('Modules [Country] and [City] must both be enabled.');
        }
    }

    public function test_it_seeds_countries_and_cities_for_the_configured_default(): void
    {
        config(['geo_seeder.countries' => ['EG', 'KW', 'AE', 'SA']]);

        $this->artisan('geo:seed')->assertSuccessful();

        $this->assertSame(4, Country::query()->count());
        $this->assertDatabaseHas('countries', ['iso2' => 'EG', 'name' => 'Egypt']);
        $this->assertGreaterThan(0, City::query()->count());

        $egypt = Country::query()->where('iso2', 'EG')->first();
        $this->assertTrue($egypt->cities()->where('name', 'Cairo')->exists());
    }

    public function test_countries_option_overrides_the_config_default(): void
    {
        config(['geo_seeder.countries' => ['EG', 'KW', 'AE', 'SA']]);

        $this->artisan('geo:seed', ['--countries' => 'EG'])->assertSuccessful();

        $this->assertSame(1, Country::query()->count());
        $this->assertDatabaseHas('countries', ['iso2' => 'EG']);
    }

    public function test_unsupported_country_code_is_skipped_not_fatal(): void
    {
        $this->artisan('geo:seed', ['--countries' => 'EG,FR'])->assertSuccessful();

        $this->assertSame(1, Country::query()->count());
        $this->assertDatabaseMissing('countries', ['iso2' => 'FR']);
    }

    public function test_running_twice_is_idempotent(): void
    {
        $this->artisan('geo:seed', ['--countries' => 'EG'])->assertSuccessful();
        $this->artisan('geo:seed', ['--countries' => 'EG'])->assertSuccessful();

        $this->assertSame(1, Country::query()->count());
        $this->assertSame(
            City::query()->count(),
            City::query()->whereHas('country', fn ($q) => $q->where('iso2', 'EG'))->count()
        );
    }

    public function test_fresh_option_removes_existing_rows_first(): void
    {
        $this->artisan('geo:seed', ['--countries' => 'EG'])->assertSuccessful();
        $egypt = Country::query()->where('iso2', 'EG')->first();
        $egypt->update(['name' => 'Manually Edited']);

        $this->artisan('geo:seed', ['--countries' => 'EG', '--fresh' => true])->assertSuccessful();

        $this->assertSame('Egypt', Country::query()->where('iso2', 'EG')->value('name'));
    }

    public function test_no_codes_given_fails_gracefully(): void
    {
        config(['geo_seeder.countries' => []]);

        $this->artisan('geo:seed')->assertFailed();
    }
}
