<?php

namespace Tests\Unit;

use App\Support\PkpaPbfCasePrivacy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PkpaPbfCasePrivacyTest extends TestCase
{
    #[DataProvider('operationalTexts')]
    public function test_pbf_operational_terms_are_not_patient_identifiers(string $text): void
    {
        $this->assertSame([], PkpaPbfCasePrivacy::warnings(['complaint' => $text]));
    }

    public static function operationalTexts(): array
    {
        return array_map(fn ($text) => [$text], [
            'Nomor faktur berbeda dengan surat jalan.',
            'Pemeriksaan surat jalan dengan nomor 12345.',
            "Surat\tjalan pelanggan no. 21 tidak sesuai faktur.",
            'Pemeriksaan surat jalan dan bukti penerimaan barang berjalan sesuai CDOB.',
            'Kendala kondisi jalan menyebabkan keterlambatan distribusi.',
            'Rute distribusi melewati kelurahan dan kecamatan.',
            'Penerimaan RM (raw material) sesuai SOP dan permintaan gudang.',
            'Kode RM 001 dicatat pada kartu stok.',
            'Identitas BPJS tidak digunakan dalam proses distribusi PBF.',
            'Data rekam medis tidak dibutuhkan pada studi kasus distribusi.',
            'Nama pasien tidak dicantumkan dan dokumen sudah disamarkan.',
            'Pasien bernama [disamarkan]',
            'Nama pasien: Tn. A',
            'Nama pasien: A.B. (inisial)',
            'Nama pasien: Anonim.',
            'Nama pasien: tidak ada',
            'Nama pasien: N/A',
            'Alamat pasien: [disamarkan]',
        ]);
    }

    #[DataProvider('identifierTexts')]
    public function test_direct_identifiers_still_block_with_field_specific_feedback(string $text): void
    {
        $warnings = PkpaPbfCasePrivacy::warnings(['history' => $text]);
        $this->assertNotEmpty($warnings);
        $this->assertStringContainsString('Bagian "Data Kasus"', $warnings[0]);
        $this->assertStringNotContainsString($text, $warnings[0]);
    }

    public static function identifierTexts(): array
    {
        return array_map(fn ($text) => [$text], [
            'Nomor RM 12345', 'RM: 12345', 'Rekam medis 12345', 'NIK 1234567890123456',
            'KTP: 1234567890123456', 'No. BPJS 1234567890',
            'Nama pasien: Budi Santoso', 'Nama pasien Budi Santoso', 'Pasien bernama Siti Aminah',
            'Nama pasien: Tn. A Budi Santoso', 'Alamat pasien: Rumah di kompleks Melati',
            'Jalan Melati No. 12', 'RT 01/RW 02',
            'Hubungi 081234567890', 'Hubungi +6281234567890', 'Telepon 0812-3456-7890',
        ]);
    }

    public function test_only_content_is_scanned_and_fields_are_not_joined(): void
    {
        $this->assertSame([], PkpaPbfCasePrivacy::warnings(['complaint' => 'nama pasien:', 'history' => 'Pemeriksaan surat jalan', 'created_by_core_user_id' => '081234567890', 'privacy_warnings' => ['Nama pasien: Budi Santoso']]));
        $this->assertNotEmpty(PkpaPbfCasePrivacy::warnings(['drug_data' => [['notes' => 'No RM 12345']]]));
        $this->assertNotEmpty(PkpaPbfCasePrivacy::warnings(['patient_name' => 'Budi Santoso']));
    }
}
