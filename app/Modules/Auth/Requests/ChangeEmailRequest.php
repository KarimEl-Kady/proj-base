<?php

namespace App\Modules\Auth\Requests;

use App\Modules\User\Support\UserRules;
use Illuminate\Validation\Rule;

class ChangeEmailRequest extends ConfirmSensitiveActionRequest
{
    public function rules(): array
    {
        return parent::rules() + [
            'email' => [
                'required', 'string', 'email', 'max:255',
                Rule::notIn([$this->user()->email]),
                UserRules::uniqueEmail((string) $this->user()->uuid),
            ],
        ];
    }
}
