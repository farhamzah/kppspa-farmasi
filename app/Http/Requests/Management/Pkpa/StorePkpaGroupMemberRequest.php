<?php

namespace App\Http\Requests\Management\Pkpa;

use Illuminate\Foundation\Http\FormRequest;

class StorePkpaGroupMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['admin', 'koordinator_kp']) ?? false;
    }

    public function rules(): array
    {
        return [
            'pkpa_enrollment_id' => ['required', 'exists:pkpa_enrollments,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
