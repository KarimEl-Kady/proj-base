<?php

namespace App\Modules\Auth\Requests;

use App\Modules\Core\Requests\BaseRequest;

class LoginRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'code' => ['sometimes', 'nullable', 'string'], // TOTP code when 2FA is enabled
            'device' => ['sometimes', 'string', 'max:64'],
        ];
    }
}
