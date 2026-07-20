<?php

namespace App\Modules\Geo\Tests\Feature;

use App\Modules\Geo\Models\City;
use App\Modules\Geo\Models\Country;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CityApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! in_array('Geo', config('project.modules'))) {
            $this->markTestSkipped('Module [Geo] is disabled.');
        }
    }

    protected function makeRecord(): City
    {
        return City::factory()->create();
    }

    // ── Authentication gate (writes only — reads are public reference data) ──

    public function test_guests_cannot_write(): void
    {
        $record = $this->makeRecord();

        $this->postJson('/api/v1/cities', [])->assertUnauthorized();
        $this->putJson("/api/v1/cities/{$record->uuid}", [])->assertUnauthorized();
        $this->deleteJson("/api/v1/cities/{$record->uuid}")->assertUnauthorized();
    }

    public function test_authenticated_users_without_cities_manage_cannot_write(): void
    {
        $record = $this->makeRecord();
        $this->actingAsUser();

        $this->postJson('/api/v1/cities', [])->assertForbidden();
        $this->putJson("/api/v1/cities/{$record->uuid}", [])->assertForbidden();
        $this->deleteJson("/api/v1/cities/{$record->uuid}")->assertForbidden();
    }

    public function test_index_returns_records(): void
    {
        $this->makeRecord();

        $this->getJson('/api/v1/cities')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_store_creates_a_record(): void
    {
        $this->actingAsUser('cities.manage');
        $country = Country::factory()->create(['iso2' => 'EG']);

        $this->postJson('/api/v1/cities', [
            'country_id' => $country->uuid,
            'name' => 'Cairo',
        ])->assertCreated();

        $this->assertDatabaseHas('cities', ['name' => 'Cairo', 'country_id' => $country->id]);
    }

    public function test_store_validates_country_exists(): void
    {
        $this->actingAsUser('cities.manage');

        $this->postJson('/api/v1/cities', [
            'country_id' => (string) Str::uuid(),
            'name' => 'Nowhere',
        ])->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_store_rejects_duplicate_name_within_the_same_country(): void
    {
        $this->actingAsUser('cities.manage');
        $country = Country::factory()->create();
        City::factory()->create(['country_id' => $country->id, 'name' => 'Cairo']);

        $this->postJson('/api/v1/cities', [
            'country_id' => $country->uuid,
            'name' => 'Cairo',
        ])->assertStatus(422);
    }

    public function test_show_returns_a_record_with_country_embedded(): void
    {
        $record = $this->makeRecord();

        $this->getJson("/api/v1/cities/{$record->uuid}")
            ->assertOk()
            ->assertJsonPath('data.country.iso2', $record->country->iso2);
    }

    public function test_update_modifies_a_record(): void
    {
        $this->actingAsUser('cities.manage');
        $record = $this->makeRecord();

        $this->putJson("/api/v1/cities/{$record->uuid}", [
            'name' => 'Updated City',
        ])->assertOk()->assertJsonPath('data.name', 'Updated City');
    }

    public function test_destroy_deletes_a_record(): void
    {
        $this->actingAsUser('cities.manage');
        $record = $this->makeRecord();

        $this->deleteJson("/api/v1/cities/{$record->uuid}")->assertOk();

        $this->assertDatabaseMissing('cities', ['id' => $record->id]);
    }

    public function test_country_filter_scopes_the_listing(): void
    {
        $egypt = Country::factory()->create(['iso2' => 'EG']);
        $kuwait = Country::factory()->create(['iso2' => 'KW']);

        City::factory()->create(['country_id' => $egypt->id, 'name' => 'Cairo']);
        City::factory()->create(['country_id' => $kuwait->id, 'name' => 'Kuwait City']);

        $response = $this->getJson('/api/v1/cities?country=EG');

        $response->assertOk();
        $this->assertCount(1, $response->json('data.data'));
        $this->assertSame('Cairo', $response->json('data.data.0.name'));
    }
}
