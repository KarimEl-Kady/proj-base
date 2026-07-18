<?php

namespace App\Modules\User\Requests;

use App\Modules\Core\Requests\BaseRequest;
use App\Modules\User\Support\UserRules;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => [
                'sometimes', 'string', 'email', 'max:255',
                UserRules::uniqueEmail((string) $this->route('user')),
            ],
            'password' => ['sometimes', 'string', Password::defaults()],
        ];
    }
}
