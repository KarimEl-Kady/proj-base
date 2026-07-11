<?php

namespace App\Modules\Auth\Requests;

use App\Modules\Core\Requests\BaseRequest;
use Illuminate\Validation\Rules\Password;

class ResetPasswordRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
        ];
    }
}
