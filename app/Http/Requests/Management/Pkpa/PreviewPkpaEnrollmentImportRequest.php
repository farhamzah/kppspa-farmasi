<?php

namespace App\Http\Requests\Management\Pkpa;

use Illuminate\Foundation\Http\FormRequest;

class PreviewPkpaEnrollmentImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['admin', 'koordinator_kp']) ?? false;
    }

    public function rules(): array
    {
        return [
            'pkpa_program_id' => ['required', 'exists:pkpa_programs,id'],
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:5120'],
        ];
    }
}
