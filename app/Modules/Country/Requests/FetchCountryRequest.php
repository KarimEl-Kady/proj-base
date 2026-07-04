<?php

namespace App\Modules\Country\Requests;

use App\Modules\Core\Requests\FetchRequest;

class FetchCountryRequest extends FetchRequest
{
    public function rules(): array
    {
        return parent::rules() + [
            // module-specific filters, e.g. 'status' => ['sometimes', 'in:draft,published'],
        ];
    }
}
