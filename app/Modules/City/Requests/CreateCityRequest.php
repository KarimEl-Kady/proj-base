<?php

namespace App\Modules\City\Requests;

use App\Modules\Core\Requests\BaseRequest;
use App\Modules\Country\Models\Country;
use Illuminate\Validation\Rule;

class CreateCityRequest extends BaseRequest
{
    /**
     * `country_id` is the country's public uuid (like every other
     * identifier in the API) — resolved here to the real internal id
     * purely so the uniqueness rule below can scope by it. CityService
     * re-resolves it independently before writing to the database.
     */
    protected function prepareForValidation(): void
    {
        if ($this->filled('country_id')) {
            $this->merge([
                'resolved_country_id' => Country::query()->where('uuid', $this->input('country_id'))->value('id'),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'country_id' => ['required', 'exists:countries,uuid'],
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('cities')->where(fn ($query) => $query->where('country_id', $this->input('resolved_country_id'))),
            ],
            'name_ar' => ['sometimes', 'nullable', 'string', 'max:255'],
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
