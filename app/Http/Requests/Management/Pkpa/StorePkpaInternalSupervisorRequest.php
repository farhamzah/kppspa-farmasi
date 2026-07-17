<?php

namespace App\Http\Requests\Management\Pkpa;

use App\Models\PkpaInternalSupervisorEligibility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePkpaInternalSupervisorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['admin', 'koordinator_kp']) ?? false;
    }

    public function rules(): array
    {
        return [
            'pkpa_program_id' => ['required', 'exists:pkpa_programs,id'],
            'practice_domain_id' => ['required', 'exists:pkpa_practice_domains,id'],
            'core_user_id' => ['required', 'string', 'max:80'],
            'maximum_active_students' => ['nullable', 'integer', 'min:0'],
            'maximum_students_per_program' => ['nullable', 'integer', 'min:0'],
            'effective_start_date' => ['nullable', 'date'],
            'effective_end_date' => ['nullable', 'date', 'after_or_equal:effective_start_date'],
            'status' => ['required', Rule::in(PkpaInternalSupervisorEligibility::STATUSES)],
            'notes' => ['nullable', 'string', 'max:4000'],
        ];
    }
}
