<?php

namespace App\Http\Requests\Management\Pkpa;

use App\Models\PkpaProgramDomain;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePkpaProgramDomainsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['admin', 'koordinator_kp']) ?? false;
    }

    public function rules(): array
    {
        return [
            'domains' => ['required', 'array'],
            'domains.*.selection_mode' => ['required', Rule::in(PkpaProgramDomain::SELECTION_MODES)],
            'domains.*.minimum_option_count' => ['required', 'integer', 'min:0', 'max:20'],
            'domains.*.duration_value' => ['nullable', 'numeric', 'min:0.01', 'max:99999'],
            'domains.*.duration_unit' => ['nullable', Rule::in(PkpaProgramDomain::DURATION_UNITS)],
            'domains.*.minimum_effective_days' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'domains.*.minimum_practice_hours' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'domains.*.weight_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'domains.*.instructions' => ['nullable', 'string', 'max:4000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            foreach ($this->input('domains', []) as $key => $domain) {
                if (filled($domain['duration_value'] ?? null) && blank($domain['duration_unit'] ?? null)) {
                    $validator->errors()->add("domains.$key.duration_unit", 'Satuan durasi wajib dipilih jika durasi diisi.');
                }
            }
        });
    }
}
