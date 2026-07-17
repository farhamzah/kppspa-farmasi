<?php

namespace App\Http\Requests\Management\Pkpa;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePkpaEnrollmentStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['admin', 'koordinator_kp']) ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['active', 'on_hold', 'archived'])],
        ];
    }
}
