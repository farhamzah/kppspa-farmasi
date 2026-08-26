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
            'core_user_id' => ['nullable', 'string', 'max:80', 'required_without_all:student_number,selected_students'],
            'student_number' => ['nullable', 'string', 'max:80', 'required_without_all:core_user_id,selected_students'],
            'selected_students' => ['nullable', 'array', 'required_without_all:core_user_id,student_number'],
            'selected_students.*.core_user_id' => ['required_with:selected_students', 'string', 'max:80'],
            'selected_students.*.student_number' => ['nullable', 'string', 'max:80'],
            'selected_students.*.name' => ['nullable', 'string', 'max:255'],
            'selected_students.*.email' => ['nullable', 'string', 'max:255'],
            'pkpa_student_group_id' => ['nullable', 'exists:pkpa_student_groups,id'],
            'notes' => ['nullable', 'string', 'max:4000'],
        ];
    }
}
