<?php

namespace App\Modules\Country\Requests;

use App\Modules\Core\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class UpdateCountryRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'name_ar' => ['sometimes', 'nullable', 'string', 'max:255'],
            'iso2' => [
                'sometimes', 'string', 'size:2', 'uppercase',
                Rule::unique('countries', 'iso2')->ignore($this->route('country'), 'uuid'),
            ],
            'iso3' => [
                'sometimes', 'string', 'size:3', 'uppercase',
                Rule::unique('countries', 'iso3')->ignore($this->route('country'), 'uuid'),
            ],
            'phone_code' => ['sometimes', 'nullable', 'string', 'max:10'],
            'currency' => ['sometimes', 'nullable', 'string', 'max:10'],
            'currency_symbol' => ['sometimes', 'nullable', 'string', 'max:10'],
            'flag_emoji' => ['sometimes', 'nullable', 'string', 'max:8'],
            'timezone' => ['sometimes', 'nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
