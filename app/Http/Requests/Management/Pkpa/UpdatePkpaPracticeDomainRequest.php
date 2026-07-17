<?php

namespace App\Http\Requests\Management\Pkpa;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePkpaPracticeDomainRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['admin', 'koordinator_kp']) ?? false;
    }

    public function rules(): array
    {
        $domain = $this->route('pkpa_practice_domain');

        return [
            'code' => ['required', 'string', 'max:30', 'alpha_dash', Rule::unique('pkpa_practice_domains', 'code')->ignore($domain?->id)],
            'name' => ['required', 'string', 'max:255'],
            'short_name' => ['nullable', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:4000'],
            'is_active' => ['boolean'],
            'confirm_system_deactivation' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ];
    }
}
