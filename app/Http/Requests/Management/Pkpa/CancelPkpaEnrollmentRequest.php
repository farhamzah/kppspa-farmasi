<?php

namespace App\Http\Requests\Management\Pkpa;

use Illuminate\Foundation\Http\FormRequest;

class CancelPkpaEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['admin', 'koordinator_kp']) ?? false;
    }

    public function rules(): array
    {
        return [
            'cancellation_reason' => ['required', 'string', 'max:4000'],
        ];
    }
}
