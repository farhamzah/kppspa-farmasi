<?php

namespace App\Http\Requests\Management\Pkpa;

use Illuminate\Foundation\Http\FormRequest;

class StorePkpaEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['admin', 'koordinator_kp']) ?? false;
    }

    public function rules(): array
    {
        return [
            'pkpa_program_id' => ['required', 'exists:pkpa_programs,id'],
            'core_user_id' => ['nullable', 'string', 'max:80', 'required_without:student_number'],
            'student_number' => ['nullable', 'string', 'max:80', 'required_without:core_user_id'],
            'pkpa_student_group_id' => ['nullable', 'exists:pkpa_student_groups,id'],
            'notes' => ['nullable', 'string', 'max:4000'],
        ];
    }
}
