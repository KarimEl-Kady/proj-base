<?php

namespace App\Modules\User\Requests;

use App\Modules\Core\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => [
                'sometimes', 'string', 'email', 'max:255',
                Rule::unique('users')->ignore($this->route('user')),
            ],
            'password' => ['sometimes', 'string', 'min:8'],
        ];
    }
}
