<?php

namespace App\Http\Requests\Management\Pkpa;

use App\Models\PkpaProgramSite;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePkpaProgramSiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['admin', 'koordinator_kp']) ?? false;
    }

    public function rules(): array
    {
        return [
            'pkpa_program_id' => ['required', 'exists:pkpa_programs,id'],
            'practice_site_id' => ['required', 'exists:pkpa_practice_sites,id'],
            'status' => ['required', Rule::in(PkpaProgramSite::STATUSES)],
            'is_active' => ['nullable', 'boolean'],
            'registration_notes' => ['nullable', 'string', 'max:4000'],
            'operational_notes' => ['nullable', 'string', 'max:4000'],
            'requirements_notes' => ['nullable', 'string', 'max:4000'],
            'default_minimum_students' => ['nullable', 'integer', 'min:0'],
            'default_maximum_students' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
