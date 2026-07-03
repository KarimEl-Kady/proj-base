<?php

namespace App\Modules\User\Requests;

use App\Modules\Core\Requests\FetchRequest;

class FetchUserRequest extends FetchRequest
{
    public function rules(): array
    {
        return parent::rules() + [
            // module-specific filters, e.g. 'verified' => ['sometimes', 'boolean'],
        ];
    }
}
