<?php

namespace App\Http\Requests\Management\Pkpa;

use App\Models\PkpaProgram;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePkpaProgramRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['admin', 'koordinator_kp']) ?? false;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('pkpa_programs', 'code')],
            'name' => ['required', 'string', 'max:255'],
            'academic_year' => ['nullable', 'string', 'max:20'],
            'cohort_name' => ['nullable', 'string', 'max:255'],
            'semester' => ['nullable', 'string', 'max:30'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'registration_start_at' => ['nullable', 'date'],
            'registration_end_at' => ['nullable', 'date', 'after_or_equal:registration_start_at'],
            'status' => ['nullable', Rule::in(PkpaProgram::STATUSES)],
            'description' => ['nullable', 'string', 'max:4000'],
        ];
    }
}
