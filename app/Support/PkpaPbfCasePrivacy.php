<?php

namespace App\Support;

use Illuminate\Support\Arr;

class PkpaPbfCasePrivacy
{
    private const FIELDS = [
        'case_code' => 'Judul/Nomor Kasus', 'patient_initials' => 'Inisial',
        'complaint' => 'Latar Belakang', 'diagnosis' => 'Identifikasi Masalah',
        'history' => 'Data Kasus', 'past_medical_history' => 'Riwayat Kasus',
        'family_history' => 'Riwayat', 'allergy' => 'Alergi', 'medication_use' => 'Data Pendukung',
        'drug_data' => 'Data Obat', 'soap' => 'Analisis', 'drp' => 'Analisis Akar Masalah dan Acuan CDOB/Regulasi',
        'drp_items' => 'Analisis Masalah', 'intervention' => 'Penyelesaian dan CAPA',
        'monitoring' => 'Pemantauan Efektivitas', 'evaluation' => 'Evaluasi', 'education' => 'Edukasi',
        'conclusion' => 'Kesimpulan', 'references' => 'Daftar Pustaka',
    ];

    public static function warnings(array $data): array
    {
        $warnings = [];
        foreach (['patient_name', 'medical_record_number', 'patient_address', 'patient_phone'] as $key) {
            if (filled($data[$key] ?? null)) {
                $warnings[] = 'Nama pasien, nomor rekam medis, alamat, dan kontak pasien dilarang.';
            }
        }
        // Inspect content fields separately: metadata and adjacent fields are not clinical prose.
        foreach (Arr::dot(Arr::only($data, array_keys(self::FIELDS))) as $path => $value) {
            if (! is_string($value)) {
                continue;
            }
            $reason = self::reason($value);
            if ($reason) {
                $label = self::FIELDS[explode('.', (string) $path)[0]];
                $warnings[] = 'Bagian "'.$label.'": terdeteksi '.$reason.'. Samarkan data tersebut sebelum menyimpan.';
            }
        }

        return array_values(array_unique($warnings));
    }

    private static function reason(string $text): ?string
    {
        if (preg_match('/(?<![\p{L}\d])(?:\+62|62|0)8(?:[\h().-]*\d){8,11}(?!\d)/u', $text)) {
            return 'nomor telepon';
        }
        if (preg_match('/\b(?:(?:no\.?|nomor)\h*(?:rm|rekam\h+medis|medical\h+record|nik|ktp|kk|bpjs)|rm\h*[:=#]|nik|ktp|kk|bpjs|rekam\h+medis|medical\h+record)\h*[:=#-]?\h*\d[\d .\/-]*/iu', $text)) {
            return 'nomor identitas atau nomor rekam medis';
        }
        if (preg_match_all('/\b(?:nama\h+pasien\h*[:=]\h*|pasien\h+bernama\h+)([^\r\n;,]+)/iu', $text, $matches)) {
            foreach ($matches[1] as $name) {
                if (! self::isAnonymous($name)) {
                    return 'nama pasien setelah label identitas';
                }
            }
        }
        if (preg_match('/\b(?i:nama\h+pasien)\h+\p{Lu}\p{Ll}{1,}(?:\h+\p{Lu}\p{Ll}{1,})+/u', $text)) {
            return 'nama pasien setelah label identitas';
        }
        if (preg_match_all('/\b(?:alamat|kontak|telepon|no\.?\h*hp)\h+pasien\h*[:=]\h*([^\r\n;]+)/iu', $text, $matches)) {
            foreach ($matches[1] as $address) {
                if (! self::isAnonymous($address)) {
                    return 'alamat atau kontak pasien';
                }
            }
        }
        if (preg_match('/\bsurat\h+jalan\b(*SKIP)(*F)|\b(?:jalan|jl\.)\h+[\p{L}\d][\p{L}\d .\x{27}-]{0,70}\h+(?:no\.?|nomor)\h*\d+|\bRT\h*\d{1,3}\h*\/\h*(?:RW\h*)?\d{1,3}\b/iu', $text)) {
            return 'alamat rinci';
        }

        return null;
    }

    private static function isAnonymous(string $value): bool
    {
        $value = trim($value);

        return preg_match('/^\[?(?:disamarkan|anonim|dirahasiakan|tidak\h+(?:dicantumkan|ditulis|dicatat|ada|tersedia)|n\/?a)\]?[.!]?$/iu', $value) === 1
            || preg_match('/^(?:(?i:pasien|tn\.?|ny\.?|nn\.?|an\.?)\h+)?[A-Z](?:\.[A-Z])*\.?(?:\h*\((?i:inisial|disamarkan|anonim)\))?[.!]?$/u', $value) === 1
            || in_array($value, ['-', '[...]', '...'], true);
    }
}
