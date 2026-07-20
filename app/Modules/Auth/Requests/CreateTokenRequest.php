<?php

namespace App\Modules\Auth\Requests;

use App\Modules\Core\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class CreateTokenRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:64'],
            'current_password' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'size:6'],
            'abilities' => ['sometimes', 'array', 'max:32'],
            'abilities.*' => [
                'string',
                'max:100',
                'distinct',
                Rule::in(config('project.auth.personal_token_abilities', [])),
            ],
        ];
    }
}
