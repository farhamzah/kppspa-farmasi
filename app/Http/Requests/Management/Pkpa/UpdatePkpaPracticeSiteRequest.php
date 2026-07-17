<?php

namespace App\Http\Requests\Management\Pkpa;

use App\Models\PkpaPracticeSite;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePkpaPracticeSiteRequest extends StorePkpaPracticeSiteRequest
{
    public function rules(): array
    {
        $site = $this->route('pkpa_practice_site');
        $rules = parent::rules();
        $rules['code'] = ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('pkpa_practice_sites', 'code')->ignore($site?->id)];
        $rules['status'] = ['required', Rule::in(PkpaPracticeSite::STATUSES)];

        return $rules;
    }
}
