<?php

namespace App\Modules\Auth\Requests;

use Closure;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class ChangePasswordRequest extends ConfirmSensitiveActionRequest
{
    public function rules(): array
    {
        return parent::rules() + [
            'password' => [
                'required', 'string', 'confirmed', Password::defaults(),
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (is_string($value) && Hash::check($value, $this->user()->password)) {
                        $fail('The new password must be different from your current password.');
                    }
                },
            ],
        ];
    }
}
