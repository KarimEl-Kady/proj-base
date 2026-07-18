<?php

namespace App\Modules\User\Requests;

use App\Modules\Core\Requests\BaseRequest;
use App\Modules\User\Support\UserRules;
use Illuminate\Validation\Rules\Password;

class CreateUserRequest extends BaseRequest
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
