<?php

namespace App\Modules\City\Requests;

use App\Modules\City\Models\City;
use App\Modules\Core\Requests\BaseRequest;
use App\Modules\Country\Models\Country;
use Illuminate\Validation\Rule;

class UpdateCityRequest extends BaseRequest
{
    /**
     * `country_id` is the country's public uuid, resolved here to the real
     * internal id purely so the uniqueness rule below can scope by it —
     * falls back to the city's current country when not part of this
     * update. CityService re-resolves any given `country_id` independently
     * before writing to the database.
     */
    protected function prepareForValidation(): void
    {
        if ($this->filled('country_id')) {
            $this->merge([
                'resolved_country_id' => Country::query()->where('uuid', $this->input('country_id'))->value('id'),
            ]);

            return;
        }

        $current = City::query()->where('uuid', $this->route('city'))->first();

        if ($current !== null) {
            $this->merge(['resolved_country_id' => $current->country_id]);
        }
    }

    public function rules(): array
    {
        return [
            'country_id' => ['sometimes', 'exists:countries,uuid'],
            'name' => [
                'sometimes', 'string', 'max:255',
                Rule::unique('cities')
                    ->where(fn ($query) => $query->where('country_id', $this->input('resolved_country_id')))
                    ->ignore($this->route('city'), 'uuid'),
            ],
            'name_ar' => ['sometimes', 'nullable', 'string', 'max:255'],
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
