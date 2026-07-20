<?php

namespace App\Modules\Auth\Requests;

use Illuminate\Validation\Rules\Password;

class ChangePasswordRequest extends ConfirmSensitiveActionRequest
{
    public function rules(): array
    {
        return parent::rules() + [
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ];
    }
}
