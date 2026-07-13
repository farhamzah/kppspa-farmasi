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
            'evidence_url' => ['nullable', 'url:http,https', 'max:4096'],
            'evidence_url_label' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $evidenceUrl = $this->normalizeEvidenceUrl($this->input('evidence_url'));
        $evidenceLabel = $this->input('evidence_url_label');

        $this->merge([
            'evidence_url' => $evidenceUrl,
            'evidence_url_label' => is_string($evidenceLabel) ? trim($evidenceLabel) : $evidenceLabel,
        ]);
    }

    public function messages(): array
    {
        return [
            'evidence.mimes' => 'Bukti kegiatan harus berupa PDF atau foto JPG, JPEG, PNG, WebP, HEIC, atau HEIF.',
            'evidence.max' => 'Ukuran bukti kegiatan maksimal 5MB.',
            'evidence.file' => 'Bukti kegiatan harus berupa file yang valid.',
            'evidence_url.url' => 'Link bukti kegiatan harus berupa URL yang valid.',
            'evidence_url.url.http,https' => 'Link bukti kegiatan harus memakai awalan http:// atau https://.',
            'evidence_url.max' => 'Link bukti kegiatan terlalu panjang.',
            'evidence_url_label.max' => 'Label link bukti kegiatan maksimal 255 karakter.',
        ];
    }

    private function normalizeEvidenceUrl(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $url = trim($value);
        $url = trim($url, "\"'<>");
        $url = preg_replace('/\s+/', '', $url) ?? $url;

        if ($url === '') {
            return null;
        }

        if (! preg_match('~^[a-z][a-z0-9+.-]*://~i', $url) && preg_match('~^(www\.|drive\.google\.com/|docs\.google\.com/|photos\.app\.goo\.gl/|forms\.gle/)~i', $url)) {
            $url = 'https://'.$url;
        }

        return $url;
    }
}
