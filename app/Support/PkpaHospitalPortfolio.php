<?php

namespace App\Support;

class PkpaHospitalPortfolio
{
    public static function isHospitalCode(?string $code): bool
    {
        return strtoupper((string) $code) === 'RS';
    }

    public static function editableSections(): array
    {
        $sections = [
            'site_profile' => [
                'title' => 'Profil Tempat PKPA Rumah Sakit',
                'description' => 'Lengkapi profil rumah sakit dan Instalasi Farmasi Rumah Sakit sesuai format portofolio.',
                'source_type' => 'structured_form',
                'reviewer_type' => 'field_internal',
                'is_required' => true,
                'fields' => [
                    self::field('overview', 'Gambaran Umum Rumah Sakit', 5),
                    self::field('history', 'Sejarah Singkat Rumah Sakit', 4),
                    self::field('vision', 'Visi', 3),
                    self::field('mission', 'Misi', 4),
                    self::field('values', 'Nilai-Nilai Rumah Sakit', 4),
                    self::field('hospital_organization', 'Struktur Organisasi Rumah Sakit', 4),
                    self::field('pharmacy_profile', 'Profil Instalasi Farmasi Rumah Sakit', 5),
                    self::field('pharmacy_organization', 'Struktur Organisasi IFRS', 4),
                    self::field('pharmacy_human_resources', 'Sumber Daya Manusia IFRS', 4),
                    self::field('pharmacy_facilities', 'Sarana dan Prasarana IFRS', 4),
                    self::field('pharmacy_services', 'Pelayanan Kefarmasian', 5),
                    self::field('units_studied', 'Unit PKPA yang Dipelajari', 4),
                    self::field('hospital_information_system', 'Sistem Informasi Rumah Sakit', 4),
                    self::field('site_analysis', 'Analisis Singkat Tempat PKPA', 6),
                ],
            ],
        ];

        foreach (self::reportDefinitions() as $code => $definition) {
            $sections[$code] = array_merge($definition, [
                'source_type' => 'structured_form',
                'reviewer_type' => 'field',
                'is_required' => false,
                'fields' => self::reportFields(),
            ]);
        }

        $sections['bibliography'] = [
            'title' => 'Daftar Pustaka',
            'description' => 'Tuliskan referensi ilmiah, regulasi, formularium, atau SOP yang digunakan.',
            'source_type' => 'structured_form',
            'reviewer_type' => 'internal',
            'is_required' => false,
            'fields' => [self::field('references', 'Daftar Referensi', 6, false)],
        ];
        $sections['attachments'] = [
            'title' => 'Lampiran',
            'description' => 'Catat daftar lampiran pendukung portofolio bila tersedia.',
            'source_type' => 'attachment_list',
            'reviewer_type' => 'all',
            'is_required' => false,
            'fields' => [self::field('items', 'Daftar Lampiran', 5, false)],
        ];

        return $sections;
    }

    public static function reportSectionCodes(): array
    {
        return array_keys(self::reportDefinitions());
    }

    public static function sectionDefinition(string $code): ?array
    {
        return self::editableSections()[$code] ?? null;
    }

    public static function templateSections(): array
    {
        $sections = [
            ['cover', 'Sampul', 'static_content', 'all', false, 'Dokumen portofolio PKPA Rumah Sakit mengikuti format resmi PSPPA UBP Karawang.'],
            ['approval', 'Lembar Pengesahan', 'approval', 'field_internal', false, null],
            ['vision_mission', 'Visi, Misi, Tujuan, dan Sasaran', 'static_content', 'all', false, 'Mengacu pada Panduan PKPA 2026 Program Studi Pendidikan Profesi Apoteker UBP Karawang.'],
            ['rules', 'Tata Tertib PKPA', 'static_content', 'all', false, 'Mahasiswa mengikuti tata tertib PKPA 2026 dan kebijakan rumah sakit.'],
            ['identity', 'Identitas Mahasiswa', 'auto_identity', 'all', false, null],
            ['integrity_pact', 'Pakta Integritas Mahasiswa PKPA', 'approval', 'student', false, null],
            ['table_of_contents', 'Daftar Isi', 'static_content', 'all', false, 'Daftar isi dibentuk otomatis mengikuti bagian portofolio PKPA Rumah Sakit.'],
            ['site_profile', 'Profil Tempat PKPA Rumah Sakit', 'structured_form', 'field_internal', true, null],
            ['daily_logbook', 'Logbook Harian', 'auto_logbook', 'field', true, null],
        ];

        foreach (self::reportDefinitions() as $code => $definition) {
            $sections[] = [$code, $definition['title'], 'structured_form', 'field', false, null];
        }

        return array_merge($sections, [
            ['weekly_reflection', 'Refleksi Mingguan', 'weekly_reflection', 'internal', true, null],
            ['case_report', 'Studi Kasus', 'repeatable_case', 'field', true, null],
            ['self_assessment', 'Self Assessment', 'self_assessment', 'internal', true, null],
            ['documentation', 'Dokumentasi Kegiatan PKPA Rumah Sakit', 'evidence_gallery', 'field', true, null],
            ['bibliography', 'Daftar Pustaka', 'structured_form', 'internal', false, null],
            ['attachments', 'Lampiran', 'attachment_list', 'all', false, null],
        ]);
    }

    private static function reportDefinitions(): array
    {
        return [
            'pharmacy_warehouse' => self::report('Gudang Farmasi', 'Catat kegiatan pengelolaan sediaan farmasi dan BMHP di gudang.'),
            'outpatient_pharmacy' => self::report('Pelayanan Farmasi Rawat Jalan', 'Catat alur pelayanan resep dan pasien rawat jalan.'),
            'inpatient_pharmacy' => self::report('Pelayanan Farmasi Rawat Inap', 'Catat distribusi serta pelayanan obat pasien rawat inap.'),
            'clinical_pharmacy' => self::report('Farmasi Klinik', 'Catat pelayanan farmasi klinik yang dipelajari.'),
            'pio' => self::report('Pelayanan Informasi Obat', 'Catat pelayanan informasi obat kepada pasien atau tenaga kesehatan.'),
            'counselling' => self::report('Konseling Pasien', 'Catat konseling pasien yang dilakukan atau diamati.'),
            'medication_reconciliation' => self::report('Rekonsiliasi Obat', 'Catat proses rekonsiliasi obat pada transisi pelayanan.'),
            'adr_meso' => self::report('Monitoring Efek Samping Obat', 'Catat kegiatan monitoring dan pelaporan efek samping obat.'),
            'ward_round' => self::report('Visite Apoteker', 'Catat keterlibatan apoteker dalam visite dan tindak lanjut terapi.'),
            'sterile_preparations' => self::report('Pelayanan Sediaan Steril', 'Catat kegiatan penyiapan atau pelayanan sediaan steril.'),
        ];
    }

    private static function report(string $name, string $description): array
    {
        return ['title' => 'Laporan Kegiatan: '.$name, 'description' => $description];
    }

    private static function reportFields(): array
    {
        return [
            self::field('purpose', 'Tujuan', 4),
            self::field('theory', 'Dasar Teori', 5),
            self::field('activity', 'Kegiatan yang Dilaksanakan', 6),
            self::field('result', 'Hasil dan Pembelajaran', 5),
        ];
    }

    private static function field(string $name, string $label, int $rows, bool $required = true): array
    {
        return compact('name', 'label', 'rows', 'required') + ['type' => 'textarea'];
    }
}
