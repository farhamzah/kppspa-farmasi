<?php

namespace App\Support;

class PkpaApotekPortfolio
{
    public static function isApotekCode(?string $code): bool
    {
        return strtoupper((string) $code) === 'APT';
    }

    public static function editableSections(): array
    {
        return [
            'site_profile' => [
                'title' => 'Profil Tempat PKPA',
                'description' => 'Ringkas profil apotek sesuai format portofolio PKPA Apotek.',
                'source_type' => 'structured_form',
                'reviewer_type' => 'field_internal',
                'is_required' => true,
                'fields' => [
                    ['name' => 'overview', 'label' => 'Gambaran Umum Apotek', 'type' => 'textarea', 'rows' => 3],
                    ['name' => 'history', 'label' => 'Sejarah Singkat Apotek', 'type' => 'textarea', 'rows' => 3],
                    ['name' => 'vision', 'label' => 'Visi', 'type' => 'textarea', 'rows' => 2],
                    ['name' => 'mission', 'label' => 'Misi', 'type' => 'textarea', 'rows' => 3],
                    ['name' => 'facilities', 'label' => 'Sarana dan Prasarana', 'type' => 'textarea', 'rows' => 3],
                    ['name' => 'human_resources', 'label' => 'Sumber Daya Manusia', 'type' => 'textarea', 'rows' => 3],
                    ['name' => 'operational_hours', 'label' => 'Jam Operasional', 'type' => 'text'],
                    ['name' => 'pharmacy_services', 'label' => 'Pelayanan Kefarmasian', 'type' => 'textarea', 'rows' => 3],
                ],
            ],
            'supply_management' => [
                'title' => 'Laporan Kegiatan: Pengelolaan Sediaan Farmasi',
                'description' => 'Catat tujuan, kegiatan, dan hasil pengelolaan sediaan farmasi di apotek.',
                'source_type' => 'structured_form',
                'reviewer_type' => 'field',
                'is_required' => true,
                'activity_hint' => 'Perencanaan, pengadaan, penerimaan, penyimpanan, FEFO/FIFO, stock opname, pemusnahan.',
                'fields' => self::reportFields(),
            ],
            'prescription_services' => [
                'title' => 'Laporan Kegiatan: Pelayanan Resep',
                'description' => 'Isi proses pelayanan resep sesuai praktik di tempat PKPA.',
                'source_type' => 'structured_form',
                'reviewer_type' => 'field',
                'is_required' => true,
                'activity_hint' => 'Skrining administrasi, skrining farmasetik, skrining klinis, penyiapan obat, etiket, penyerahan obat, edukasi pasien.',
                'fields' => self::reportFields(),
            ],
            'self_medication' => [
                'title' => 'Laporan Kegiatan: Pelayanan Swamedikasi',
                'description' => 'Dokumentasikan kegiatan swamedikasi selama PKPA.',
                'source_type' => 'structured_form',
                'reviewer_type' => 'field',
                'is_required' => true,
                'activity_hint' => 'Minimal 10 kasus, mencakup keluhan, anamnesis, red flag, assessment, rekomendasi obat, edukasi, dan follow up.',
                'fields' => self::reportFields(),
            ],
            'counselling' => [
                'title' => 'Laporan Kegiatan: Konseling Pasien',
                'description' => 'Ringkas aktivitas konseling pasien yang dilakukan.',
                'source_type' => 'structured_form',
                'reviewer_type' => 'field',
                'is_required' => true,
                'activity_hint' => 'Minimal 5 pasien.',
                'fields' => self::reportFields(),
            ],
            'pio' => [
                'title' => 'Laporan Kegiatan: Pelayanan Informasi Obat',
                'description' => 'Catat layanan informasi obat yang diberikan selama PKPA.',
                'source_type' => 'structured_form',
                'reviewer_type' => 'field',
                'is_required' => true,
                'activity_hint' => 'Minimal 5 pasien atau permintaan informasi obat.',
                'fields' => self::reportFields(),
            ],
            'narcotics_psychotropics' => [
                'title' => 'Laporan Kegiatan: Pengelolaan Narkotika dan Psikotropika',
                'description' => 'Dokumentasikan penyimpanan, pelaporan, dan dokumentasi narkotika/psikotropika.',
                'source_type' => 'structured_form',
                'reviewer_type' => 'field',
                'is_required' => true,
                'activity_hint' => 'Penyimpanan, pelaporan, dan dokumentasi.',
                'fields' => self::reportFields(),
            ],
            'administration' => [
                'title' => 'Laporan Kegiatan: Administrasi Kefarmasian',
                'description' => 'Catat administrasi operasional apotek yang dipelajari.',
                'source_type' => 'structured_form',
                'reviewer_type' => 'field',
                'is_required' => true,
                'activity_hint' => 'Penjualan, pelaporan, dan BPJS bila ada.',
                'fields' => self::reportFields(),
            ],
            'bibliography' => [
                'title' => 'Daftar Pustaka',
                'description' => 'Isi referensi ilmiah atau regulasi yang dipakai dalam portofolio Apotek.',
                'source_type' => 'structured_form',
                'reviewer_type' => 'internal',
                'is_required' => false,
                'fields' => [
                    ['name' => 'references', 'label' => 'Daftar Referensi', 'type' => 'textarea', 'rows' => 5],
                ],
            ],
            'attachments' => [
                'title' => 'Lampiran',
                'description' => 'Catat lampiran yang melengkapi portofolio, bila ada.',
                'source_type' => 'attachment_list',
                'reviewer_type' => 'all',
                'is_required' => false,
                'fields' => [
                    ['name' => 'items', 'label' => 'Daftar Lampiran', 'type' => 'textarea', 'rows' => 5],
                ],
            ],
        ];
    }

    public static function reportSectionCodes(): array
    {
        return [
            'supply_management',
            'prescription_services',
            'self_medication',
            'counselling',
            'pio',
            'narcotics_psychotropics',
            'administration',
        ];
    }

    public static function reportFields(): array
    {
        return [
            ['name' => 'purpose', 'label' => 'Tujuan', 'type' => 'textarea', 'rows' => 2],
            ['name' => 'activities', 'label' => 'Kegiatan', 'type' => 'textarea', 'rows' => 3],
            ['name' => 'result', 'label' => 'Hasil', 'type' => 'textarea', 'rows' => 3],
            ['name' => 'preceptor_notes', 'label' => 'Catatan Preseptor', 'type' => 'textarea', 'rows' => 2],
        ];
    }

    public static function sectionDefinition(string $code): ?array
    {
        $sections = self::editableSections();
        return $sections[$code] ?? null;
    }

    public static function templateSections(): array
    {
        return [
            ['cover', 'Sampul', 'static_content', 'all', false, 'Dokumen portofolio PKPA Apotek mengikuti format resmi PSPPA UBP Karawang.'],
            ['approval', 'Lembar Pengesahan', 'approval', 'field_internal', false, 'Lembar pengesahan ditempatkan di bagian depan portofolio dan memerlukan persetujuan Preseptor serta Pembimbing Dalam.'],
            ['vision_mission', 'Visi, Misi, Tujuan, dan Sasaran', 'static_content', 'all', false, 'Mengacu pada Panduan PKPA 2026 Program Studi Pendidikan Profesi Apoteker UBP Karawang.'],
            ['rules', 'Tata Tertib PKPA', 'static_content', 'all', false, 'Mahasiswa mengikuti tata tertib PKPA 2026 dan menyelesaikan laporan sebelum berpindah wahana.'],
            ['identity', 'Identitas Mahasiswa', 'auto_identity', 'all', false, null],
            ['integrity_pact', 'Pakta Integritas Mahasiswa PKPA', 'approval', 'student', false, null],
            ['table_of_contents', 'Daftar Isi', 'static_content', 'all', false, 'Daftar isi dibentuk otomatis mengikuti bagian portofolio PKPA Apotek.'],
            ['site_profile', 'Profil Tempat PKPA', 'structured_form', 'field_internal', true, null],
            ['daily_logbook', 'Logbook Harian', 'auto_logbook', 'field', true, null],
            ['supply_management', 'Laporan Kegiatan: Pengelolaan Sediaan Farmasi', 'structured_form', 'field', true, null],
            ['prescription_services', 'Laporan Kegiatan: Pelayanan Resep', 'structured_form', 'field', true, null],
            ['self_medication', 'Laporan Kegiatan: Pelayanan Swamedikasi', 'structured_form', 'field', true, null],
            ['counselling', 'Laporan Kegiatan: Konseling Pasien', 'structured_form', 'field', true, null],
            ['pio', 'Laporan Kegiatan: Pelayanan Informasi Obat', 'structured_form', 'field', true, null],
            ['narcotics_psychotropics', 'Laporan Kegiatan: Pengelolaan Narkotika dan Psikotropika', 'structured_form', 'field', true, null],
            ['administration', 'Laporan Kegiatan: Administrasi Kefarmasian', 'structured_form', 'field', true, null],
            ['case_report', 'Studi Kasus', 'repeatable_case', 'field', true, null],
            ['weekly_reflection', 'Refleksi Mingguan', 'weekly_reflection', 'internal', true, null],
            ['self_assessment', 'Self Assessment', 'self_assessment', 'internal', true, null],
            ['documentation', 'Dokumentasi Kegiatan', 'evidence_gallery', 'field', true, null],
            ['bibliography', 'Daftar Pustaka', 'structured_form', 'internal', false, null],
            ['attachments', 'Lampiran', 'attachment_list', 'all', false, null],
        ];
    }

    public static function completed(array $payload, string $code): bool
    {
        if (in_array($code, self::reportSectionCodes(), true)) {
            return filled($payload['purpose'] ?? null)
                && filled($payload['activities'] ?? null)
                && filled($payload['result'] ?? null);
        }

        return collect($payload)
            ->filter(fn ($value) => filled(is_array($value) ? implode(' ', $value) : $value))
            ->isNotEmpty();
    }

    public static function summaryLines(string $code, array $payload): array
    {
        $definition = self::sectionDefinition($code);
        if (! $definition) {
            return [];
        }

        $lines = [];
        foreach ($definition['fields'] ?? [] as $field) {
            $value = trim((string) ($payload[$field['name']] ?? ''));
            if ($value !== '') {
                $lines[] = $field['label'].': '.$value;
            }
        }

        return $lines;
    }
}
