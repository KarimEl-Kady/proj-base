<?php

namespace App\Modules\Auth\Requests;

use App\Modules\Core\Requests\BaseRequest;

class TwoFactorCodeRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'code' => ['required', 'string'],
        ];
    }
}
