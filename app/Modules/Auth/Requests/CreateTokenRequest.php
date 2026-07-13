<?php

namespace App\Modules\Auth\Requests;

use App\Modules\Core\Requests\BaseRequest;

class CreateTokenRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:64'],
            'abilities' => ['sometimes', 'array', 'max:32'],
            'abilities.*' => ['string', 'max:100', 'distinct'],
        ];
    }
}
