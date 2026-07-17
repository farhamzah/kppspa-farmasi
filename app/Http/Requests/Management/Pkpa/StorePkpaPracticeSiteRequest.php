<?php

namespace App\Http\Requests\Management\Pkpa;

use App\Models\PkpaPracticeSite;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePkpaPracticeSiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['admin', 'koordinator_kp']) ?? false;
    }

    public function rules(): array
    {
        return [
            'practice_domain_id' => ['required', 'exists:pkpa_practice_domains,id'],
            'practice_domain_option_id' => ['nullable', 'exists:pkpa_practice_domain_options,id'],
            'code' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('pkpa_practice_sites', 'code')],
            'name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:4000'],
            'address' => ['nullable', 'string', 'max:4000'],
            'village' => ['nullable', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'contact_person_name' => ['nullable', 'string', 'max:255'],
            'contact_person_phone' => ['nullable', 'string', 'max:50'],
            'cooperation_start_date' => ['nullable', 'date'],
            'cooperation_end_date' => ['nullable', 'date', 'after_or_equal:cooperation_start_date'],
            'status' => ['required', Rule::in(PkpaPracticeSite::STATUSES)],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:4000'],
        ];
    }
}
