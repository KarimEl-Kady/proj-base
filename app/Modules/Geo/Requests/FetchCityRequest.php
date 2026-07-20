<?php

namespace App\Modules\Geo\Requests;

use App\Modules\Core\Requests\FetchRequest;

class FetchCityRequest extends FetchRequest
{
    public function rules(): array
    {
        return parent::rules() + [
            // ISO2 code, e.g. ?country=EG — see CityRepository::baseQuery()
            'country' => ['sometimes', 'nullable', 'string', 'size:2'],
        ];
    }

    public function countryCode(): ?string
    {
        $code = $this->validated('country');

        return $code !== null ? strtoupper($code) : null;
    }
}
