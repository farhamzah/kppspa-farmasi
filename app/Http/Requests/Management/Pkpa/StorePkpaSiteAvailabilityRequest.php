<?php

namespace App\Http\Requests\Management\Pkpa;

use App\Models\PkpaSiteAvailabilityPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePkpaSiteAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['admin', 'koordinator_kp']) ?? false;
    }

    public function rules(): array
    {
        return [
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'minimum_students' => ['nullable', 'integer', 'min:0'],
            'maximum_students' => ['required', 'integer', 'min:1'],
            'reserved_slots' => ['nullable', 'integer', 'min:0', 'lte:maximum_students'],
            'operational_days' => ['nullable', 'array'],
            'operational_days.*' => [Rule::in(PkpaSiteAvailabilityPeriod::OPERATIONAL_DAYS)],
            'daily_start_time' => ['nullable', 'date_format:H:i'],
            'daily_end_time' => ['nullable', 'date_format:H:i', 'after:daily_start_time'],
            'status' => ['required', Rule::in(PkpaSiteAvailabilityPeriod::STATUSES)],
            'notes' => ['nullable', 'string', 'max:4000'],
        ];
    }
}
