<?php

namespace App\Modules\User\Requests;

use App\Modules\Core\Requests\BaseRequest;

class UpdateUserRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
        ];
    }
}
