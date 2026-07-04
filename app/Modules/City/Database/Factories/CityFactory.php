<?php

namespace App\Modules\City\Database\Factories;

use App\Modules\City\Models\City;
use App\Modules\Country\Database\Factories\CountryFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<City>
 */
class CityFactory extends Factory
{
    protected $model = City::class;

    public function definition(): array
    {
        return [
            'country_id' => CountryFactory::new(),
            'name' => fake()->unique()->city(),
            'name_ar' => null,
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
            'is_active' => true,
        ];
    }
}
