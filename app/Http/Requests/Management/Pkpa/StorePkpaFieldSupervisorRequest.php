<?php

namespace App\Http\Requests\Management\Pkpa;

use App\Models\PkpaSiteFieldSupervisor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePkpaFieldSupervisorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['admin', 'koordinator_kp']) ?? false;
    }

    public function rules(): array
    {
        return [
            'core_user_id' => ['required', 'string', 'max:80'],
            'position_title' => ['nullable', 'string', 'max:255'],
            'is_primary_contact' => ['nullable', 'boolean'],
            'maximum_active_students' => ['nullable', 'integer', 'min:0'],
            'effective_start_date' => ['nullable', 'date'],
            'effective_end_date' => ['nullable', 'date', 'after_or_equal:effective_start_date'],
            'status' => ['required', Rule::in(PkpaSiteFieldSupervisor::STATUSES)],
            'notes' => ['nullable', 'string', 'max:4000'],
        ];
    }
}
