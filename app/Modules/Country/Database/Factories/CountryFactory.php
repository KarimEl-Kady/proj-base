<?php

namespace App\Modules\Country\Database\Factories;

use App\Modules\Country\Models\Country;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Country>
 */
class CountryFactory extends Factory
{
    protected $model = Country::class;

    public function definition(): array
    {
        $name = fake()->unique()->country();

        return [
            'name' => $name,
            'name_ar' => null,
            'iso2' => strtoupper(fake()->unique()->lexify('??')),
            'iso3' => strtoupper(fake()->unique()->lexify('???')),
            'phone_code' => (string) fake()->numberBetween(1, 999),
            'currency' => strtoupper(fake()->lexify('???')),
            'currency_symbol' => fake()->currencyCode(),
            'flag_emoji' => null,
            'timezone' => fake()->timezone(),
            'is_active' => true,
        ];
    }
}
