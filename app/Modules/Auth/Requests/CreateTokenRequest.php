<?php

namespace App\Modules\Auth\Requests;

use App\Modules\Core\Requests\BaseRequest;

class CreateTokenRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:64'],
        ];
    }
}
