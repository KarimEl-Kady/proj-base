<?php

namespace App\Modules\Auth\Requests;

use App\Modules\Core\Requests\BaseRequest;
use App\Modules\User\Support\UserRules;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', UserRules::uniqueEmail()],
            'password' => ['required', 'string', Password::defaults()],
        ];
    }
}
