<?php

namespace App\Modules\Auth\Requests;

use App\Modules\Core\Requests\BaseRequest;

class ConfirmSensitiveActionRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'size:6'],
        ];
    }
}
