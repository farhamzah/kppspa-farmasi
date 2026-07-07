<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

class StoreKpLogbookRequest extends FormRequest
{
    public const EVIDENCE_MAX_KB = 5120;

    public const EVIDENCE_TYPES = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'heic', 'heif'];

    public function authorize(): bool
    {
        return $this->user()?->hasRole('mahasiswa') ?? false;
    }

    public function rules(): array
    {
        return [
            'activity_date' => ['required', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            'activity_title' => ['required', 'string', 'max:255'],
            'activity_description' => ['required', 'string'],
            'learning_outcome' => ['nullable', 'string'],
            'obstacle' => ['nullable', 'string'],
            'solution' => ['nullable', 'string'],
            'evidence' => ['nullable', 'file', 'mimes:'.implode(',', self::EVIDENCE_TYPES), 'max:'.self::EVIDENCE_MAX_KB],
        ];
    }

    public function messages(): array
    {
        return [
            'evidence.mimes' => 'Bukti kegiatan harus berupa PDF atau foto JPG, JPEG, PNG, WebP, HEIC, atau HEIF.',
            'evidence.max' => 'Ukuran bukti kegiatan maksimal 5MB.',
            'evidence.file' => 'Bukti kegiatan harus berupa file yang valid.',
        ];
    }
}
