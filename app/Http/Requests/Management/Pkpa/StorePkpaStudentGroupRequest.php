<?php

namespace App\Http\Requests\Management\Pkpa;

use App\Models\PkpaStudentGroup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePkpaStudentGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['admin', 'koordinator_kp']) ?? false;
    }

    public function rules(): array
    {
        return [
            'pkpa_program_id' => ['required', 'exists:pkpa_programs,id'],
            'code' => ['required', 'string', 'max:50', Rule::unique('pkpa_student_groups', 'code')->where('pkpa_program_id', $this->input('pkpa_program_id'))],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:4000'],
            'maximum_members' => ['nullable', 'integer', 'min:1', 'max:500'],
            'status' => ['required', Rule::in(PkpaStudentGroup::STATUSES)],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
