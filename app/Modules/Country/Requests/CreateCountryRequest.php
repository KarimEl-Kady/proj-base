<?php

namespace App\Modules\Country\Requests;

use App\Modules\Core\Requests\BaseRequest;

class CreateCountryRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'name_ar' => ['sometimes', 'nullable', 'string', 'max:255'],
            'iso2' => ['required', 'string', 'size:2', 'uppercase', 'unique:countries,iso2'],
            'iso3' => ['required', 'string', 'size:3', 'uppercase', 'unique:countries,iso3'],
            'phone_code' => ['sometimes', 'nullable', 'string', 'max:10'],
            'currency' => ['sometimes', 'nullable', 'string', 'max:10'],
            'currency_symbol' => ['sometimes', 'nullable', 'string', 'max:10'],
            'flag_emoji' => ['sometimes', 'nullable', 'string', 'max:8'],
            'timezone' => ['sometimes', 'nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
