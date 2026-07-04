<?php

namespace Local\GeoSeeder\Tests;

use InvalidArgumentException;
use Local\GeoSeeder\GeoDataRepository;
use Tests\TestCase;

class GeoDataRepositoryTest extends TestCase
{
    protected GeoDataRepository $geo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->geo = app(GeoDataRepository::class);
    }

    public function test_supported_lists_the_four_shipped_countries(): void
    {
        $this->assertSame(['AE', 'EG', 'KW', 'SA'], $this->geo->supported());
    }

    public function test_has_is_case_insensitive(): void
    {
        $this->assertTrue($this->geo->has('eg'));
        $this->assertTrue($this->geo->has('EG'));
        $this->assertFalse($this->geo->has('FR'));
    }

    public function test_country_returns_the_expected_shape(): void
    {
        $egypt = $this->geo->country('EG');

        $this->assertSame('Egypt', $egypt['name']);
        $this->assertSame('EG', $egypt['iso2']);
        $this->assertSame('EGY', $egypt['iso3']);
        $this->assertArrayHasKey('phone_code', $egypt);
        $this->assertArrayHasKey('currency', $egypt);
        $this->assertArrayHasKey('flag_emoji', $egypt);
        $this->assertArrayHasKey('timezone', $egypt);
    }

    public function test_cities_returns_a_non_empty_list_with_coordinates(): void
    {
        $cities = $this->geo->cities('KW');

        $this->assertNotEmpty($cities);
        $this->assertSame('Kuwait City', $cities[0]['name']);
        $this->assertIsFloat($cities[0]['latitude']);
        $this->assertIsFloat($cities[0]['longitude']);
    }

    public function test_every_shipped_country_has_at_least_one_city(): void
    {
        foreach ($this->geo->supported() as $code) {
            $this->assertNotEmpty($this->geo->cities($code), "Country [{$code}] has no cities.");
        }
    }

    public function test_unsupported_country_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No geo data for country [FR]');

        $this->geo->country('FR');
    }
}
