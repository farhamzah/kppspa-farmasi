<?php

namespace App\Support;

class PkpaIndustryPortfolio
{
    public static function isIndustryCode(?string $code): bool
    {
        return strtoupper((string) $code) === 'IND';
    }

    public static function editableSections(): array
    {
        $sections = [
            'site_profile' => [
                'title' => 'Profil Tempat PKPA Industri Farmasi',
                'description' => 'Lengkapi profil industri farmasi sesuai format portofolio resmi.',
                'source_type' => 'structured_form',
                'reviewer_type' => 'field_internal',
                'is_required' => true,
                'fields' => [
                    self::field('overview', 'Gambaran Umum Industri Farmasi', 5),
                    self::field('identity', 'Identitas Instansi', 4),
                    self::field('vision', 'Visi', 3),
                    self::field('mission', 'Misi', 4),
                    self::field('main_duties', 'Tugas Pokok', 4),
                    self::field('functions', 'Fungsi', 4),
                    self::field('organization_structure', 'Struktur Organisasi', 4),
                    self::field('facilities', 'Sarana dan Prasarana', 4),
                    self::field('main_activities', 'Kegiatan Utama', 5),
                    self::field('units_studied', 'Unit PKPA yang Dipelajari', 4),
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
            'description' => 'Tuliskan CPOB, regulasi, SOP, dan referensi ilmiah yang digunakan.',
            'source_type' => 'structured_form',
            'reviewer_type' => 'internal',
            'is_required' => false,
            'fields' => [self::field('references', 'Daftar Referensi', 6, false)],
        ];
        $sections['attachments'] = [
            'title' => 'Lampiran',
            'description' => 'Catat daftar lampiran pendukung yang diizinkan oleh tempat PKPA.',
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
            ['cover', 'Sampul', 'static_content', 'all', false, 'Dokumen portofolio PKPA Industri Farmasi mengikuti format resmi PSPPA UBP Karawang.'],
            ['approval', 'Lembar Pengesahan', 'approval', 'field_internal', false, null],
            ['vision_mission', 'Visi, Misi, Tujuan, dan Sasaran', 'static_content', 'all', false, 'Mengacu pada Panduan PKPA 2026 Program Studi Pendidikan Profesi Apoteker UBP Karawang.'],
            ['rules', 'Tata Tertib PKPA', 'static_content', 'all', false, 'Mahasiswa mengikuti tata tertib PKPA 2026 dan kebijakan industri farmasi.'],
            ['identity', 'Identitas Mahasiswa', 'auto_identity', 'all', false, null],
            ['integrity_pact', 'Pakta Integritas Mahasiswa PKPA', 'approval', 'student', false, null],
            ['table_of_contents', 'Daftar Isi', 'static_content', 'all', false, 'Daftar isi dibentuk otomatis mengikuti bagian portofolio Industri Farmasi.'],
            ['site_profile', 'Profil Tempat PKPA Industri Farmasi', 'structured_form', 'field_internal', true, null],
            ['daily_logbook', 'Logbook Harian', 'auto_logbook', 'field', true, null],
        ];

        foreach (self::reportDefinitions() as $code => $definition) {
            $sections[] = [$code, $definition['title'], 'structured_form', 'field', false, null];
        }

        return array_merge($sections, [
            ['weekly_reflection', 'Refleksi Mingguan', 'weekly_reflection', 'internal', true, null],
            ['case_report', 'Studi Kasus Industri Farmasi', 'repeatable_case', 'field', true, null],
            ['self_assessment', 'Self Assessment Industri Farmasi', 'self_assessment', 'internal', true, null],
            ['documentation', 'Dokumentasi Kegiatan PKPA Industri Farmasi', 'evidence_gallery', 'field', true, null],
            ['bibliography', 'Daftar Pustaka', 'structured_form', 'internal', false, null],
            ['attachments', 'Lampiran', 'attachment_list', 'all', false, null],
        ]);
    }

    private static function reportDefinitions(): array
    {
        return [
            'quality_assurance' => self::report('Quality Assurance (QA)', 'Catat penerapan sistem manajemen mutu dan kegiatan QA.'),
            'quality_control' => self::report('Quality Control (QC)', 'Catat pengujian dan pengendalian mutu bahan maupun produk.'),
            'production' => self::report('Produksi', 'Catat proses produksi serta pengendalian selama proses.'),
            'warehouse' => self::report('Gudang (Warehouse)', 'Catat penerimaan, penyimpanan, dan pengelolaan material.'),
            'research_development' => self::report('Research and Development (R&D)', 'Catat pengembangan formula atau proses yang dipelajari.'),
            'regulatory_affairs' => self::report('Regulatory Affairs', 'Catat pengelolaan dokumen dan pemenuhan regulasi.'),
            'validation' => self::report('Validasi', 'Catat kegiatan validasi, kualifikasi, atau verifikasi.'),
            'ppic' => self::report('Production Planning and Inventory Control (PPIC)', 'Catat perencanaan produksi dan pengendalian persediaan.'),
            'pharmacovigilance' => self::report('Pharmacovigilance', 'Catat pemantauan keamanan produk dan farmakovigilans.'),
            'engineering' => self::report('Engineering', 'Catat sistem utilitas, pemeliharaan, atau fasilitas penunjang.'),
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
