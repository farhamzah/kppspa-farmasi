<?php

namespace App\Support;

class PkpaHealthOfficePortfolio
{
    public const TEMPLATE_CODE = 'PORT-DINKES-v1';

    public static function editableSections(): array
    {
        $sections = ['site_profile' => [
            'title' => 'Profil Tempat PKPA Dinas Kesehatan', 'description' => 'Lengkapi profil Dinas Kesehatan dan gudang farmasi.',
            'source_type' => 'structured_form', 'reviewer_type' => 'field_internal', 'is_required' => true,
            'fields' => collect([
                ['overview', 'Gambaran Umum Dinas Kesehatan', 5], ['history', 'Sejarah Singkat Dinas Kesehatan', 4],
                ['vision', 'Visi', 3], ['mission', 'Misi', 4], ['main_duties', 'Tugas Pokok', 4], ['functions', 'Fungsi', 4],
                ['warehouse_profile', 'Profil Gudang Farmasi', 5], ['warehouse_organization', 'Struktur Organisasi Gudang Farmasi', 4],
                ['human_resources', 'Sumber Daya Manusia', 4], ['facilities', 'Sarana dan Prasarana', 4],
                ['pharmacy_services', 'Pelayanan Kefarmasian', 5], ['units_studied', 'Unit PKPA yang Dipelajari', 4],
                ['health_programs', 'Program Kesehatan Dinas Kesehatan', 5], ['site_analysis', 'Analisis Singkat Tempat PKPA', 6],
            ])->map(fn ($f) => self::field(...$f))->all(),
        ]];
        foreach (self::reportDefinitions() as $code => $definition) $sections[$code] = $definition + ['source_type' => 'structured_form', 'reviewer_type' => 'field', 'is_required' => false, 'fields' => self::reportFields()];
        $sections['bibliography'] = ['title' => 'Daftar Pustaka', 'description' => 'Tuliskan regulasi dan referensi yang digunakan.', 'source_type' => 'structured_form', 'reviewer_type' => 'internal', 'is_required' => false, 'fields' => [self::field('references', 'Daftar Referensi', 6, false)]];
        $sections['attachments'] = ['title' => 'Lampiran', 'description' => 'Catat daftar lampiran pendukung.', 'source_type' => 'attachment_list', 'reviewer_type' => 'all', 'is_required' => false, 'fields' => [self::field('items', 'Daftar Lampiran', 5, false)]];
        return $sections;
    }

    public static function reportSectionCodes(): array { return array_keys(self::reportDefinitions()); }
    public static function sectionDefinition(string $code): ?array { return self::editableSections()[$code] ?? null; }
    public static function templateSections(): array
    {
        $sections = [
            ['cover', 'Sampul', 'static_content', 'all', false], ['approval', 'Lembar Pengesahan', 'approval', 'field_internal', false],
            ['vision_mission', 'Visi, Misi, Tujuan, dan Sasaran', 'static_content', 'all', false], ['rules', 'Tata Tertib PKPA', 'static_content', 'all', false],
            ['identity', 'Identitas Mahasiswa', 'auto_identity', 'all', false], ['integrity_pact', 'Pakta Integritas Mahasiswa PKPA', 'approval', 'student', false],
            ['table_of_contents', 'Daftar Isi', 'static_content', 'all', false], ['site_profile', 'Profil Tempat PKPA Dinas Kesehatan', 'structured_form', 'field_internal', true],
            ['daily_logbook', 'Logbook Harian', 'auto_logbook', 'field', true],
        ];
        foreach (self::reportDefinitions() as $code => $definition) $sections[] = [$code, $definition['title'], 'structured_form', 'field', false];
        return array_merge($sections, [
            ['weekly_reflection', 'Refleksi Mingguan', 'weekly_reflection', 'internal', true], ['case_report', 'Studi Kasus Dinas Kesehatan', 'repeatable_case', 'field', true],
            ['self_assessment', 'Self Assessment Dinas Kesehatan', 'self_assessment', 'internal', true], ['documentation', 'Dokumentasi Kegiatan PKPA Dinas Kesehatan', 'evidence_gallery', 'field', true],
            ['bibliography', 'Daftar Pustaka', 'structured_form', 'internal', false], ['attachments', 'Lampiran', 'attachment_list', 'all', false],
        ]);
    }

    private static function reportDefinitions(): array
    {
        return [
            'health_office_orientation' => self::report('Orientasi dan Pengenalan Dinas Kesehatan'),
            'medicine_planning' => self::report('Perencanaan Kebutuhan Obat dan BMHP'), 'government_procurement' => self::report('Pengadaan Obat Pemerintah'),
            'goods_receipt' => self::report('Penerimaan dan Pemeriksaan Obat'), 'warehouse_storage' => self::report('Penyimpanan Obat di Instalasi Farmasi Kabupaten/Kota'),
            'health_facility_distribution' => self::report('Distribusi Obat ke Puskesmas dan Fasilitas Pelayanan Kesehatan'),
            'medicine_monitoring' => self::report('Monitoring dan Evaluasi Pengelolaan Obat'), 'program_medicines' => self::report('Pengelolaan Obat Program'),
            'pharmacy_supervision' => self::report('Pembinaan dan Supervisi Fasilitas Pelayanan Kefarmasian'),
            'pharmacy_reporting' => self::report('Pelaporan Kefarmasian dan Analisis Data Logistik'),
        ];
    }
    private static function report(string $name): array { return ['title' => 'Laporan Kegiatan: '.$name, 'description' => 'Isi tujuan, dasar teori, kegiatan, dan hasil pembelajaran pada bagian ini.']; }
    private static function reportFields(): array { return [self::field('purpose', 'Tujuan', 4), self::field('theory', 'Dasar Teori', 5), self::field('activity', 'Kegiatan yang Dilaksanakan', 6), self::field('result', 'Hasil dan Pembelajaran', 5)]; }
    private static function field(string $name, string $label, int $rows, bool $required = true): array { return compact('name', 'label', 'rows', 'required') + ['type' => 'textarea']; }
}
