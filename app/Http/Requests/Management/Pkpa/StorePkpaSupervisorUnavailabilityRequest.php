<?php

namespace App\Http\Requests\Management\Pkpa;

use Illuminate\Foundation\Http\FormRequest;

class StorePkpaSupervisorUnavailabilityRequest extends FormRequest
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
            'reason' => ['required', 'string', 'max:4000'],
            'status' => ['nullable', 'in:active,cancelled,expired'],
        ];
    }
}
