<?php

namespace App\Modules\Country\Tests\Feature;

use App\Modules\Country\Models\Country;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CountryApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! in_array('Country', config('project.modules'))) {
            $this->markTestSkipped('Module [Country] is disabled.');
        }
    }

    protected function makeRecord(): Country
    {
        return Country::factory()->create();
    }

    public function test_index_returns_records(): void
    {
        $this->makeRecord();

        $this->getJson('/api/v1/countries')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_store_creates_a_record(): void
    {
        $this->postJson('/api/v1/countries', [
            'name' => 'Egypt',
            'iso2' => 'EG',
            'iso3' => 'EGY',
            'phone_code' => '20',
            'currency' => 'EGP',
        ])->assertCreated();

        $this->assertDatabaseHas('countries', ['iso2' => 'EG']);
    }

    public function test_store_validates_unique_iso_codes(): void
    {
        $this->makeRecord()->update(['iso2' => 'EG', 'iso3' => 'EGY']);

        $this->postJson('/api/v1/countries', [
            'name' => 'Egypt Again',
            'iso2' => 'EG',
            'iso3' => 'EGY',
        ])->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_show_returns_a_record(): void
    {
        $record = $this->makeRecord();

        $this->getJson("/api/v1/countries/{$record->uuid}")
            ->assertOk()
            ->assertJsonPath('data.iso2', $record->iso2);
    }

    public function test_update_modifies_a_record(): void
    {
        $record = $this->makeRecord();

        $this->putJson("/api/v1/countries/{$record->uuid}", [
            'name' => 'Updated Name',
        ])->assertOk()->assertJsonPath('data.name', 'Updated Name');
    }

    public function test_destroy_deletes_a_record(): void
    {
        $record = $this->makeRecord();

        $this->deleteJson("/api/v1/countries/{$record->uuid}")->assertOk();

        $this->assertDatabaseMissing('countries', ['id' => $record->id]);
    }

    public function test_word_filter_searches_name_and_iso_columns(): void
    {
        Country::factory()->create(['name' => 'Egypt', 'iso2' => 'EG', 'iso3' => 'EGY']);
        Country::factory()->create(['name' => 'Kuwait', 'iso2' => 'KW', 'iso3' => 'KWT']);

        $response = $this->getJson('/api/v1/countries?word=Kuwait');

        $response->assertOk();
        $this->assertCount(1, $response->json('data.data'));
        $this->assertSame('Kuwait', $response->json('data.data.0.name'));
    }
}
