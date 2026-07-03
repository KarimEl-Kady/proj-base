<?php

namespace App\Modules\Core\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Base for all module form requests. Authorization defaults to true —
 * override authorize() where an endpoint needs a policy check.
 */
abstract class BaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [];
    }
}
