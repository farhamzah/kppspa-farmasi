<?php

namespace App\Support;

class PkpaLokaPomPortfolio
{
    public const TEMPLATE_CODE = 'PORT-LOKAPOM-v1';

    public static function editableSections(): array
    {
        $sections = ['site_profile' => [
            'title' => 'Profil Tempat PKPA Loka POM', 'description' => 'Lengkapi profil Loka POM sesuai format portofolio.',
            'source_type' => 'structured_form', 'reviewer_type' => 'field_internal', 'is_required' => true,
            'fields' => collect([
                ['overview', 'Gambaran Umum Loka POM', 5], ['identity', 'Identitas Instansi', 4], ['vision', 'Visi', 3],
                ['mission', 'Misi', 4], ['main_duties', 'Tugas Pokok', 4], ['functions', 'Fungsi', 4],
                ['organization', 'Struktur Organisasi', 4], ['facilities', 'Sarana dan Prasarana', 4],
                ['main_activities', 'Kegiatan Utama', 5], ['units_studied', 'Unit PKPA yang Dipelajari', 4],
                ['site_analysis', 'Analisis Singkat Tempat PKPA', 6],
            ])->map(fn ($f) => self::field(...$f))->all(),
        ]];
        foreach (self::reportDefinitions() as $code => $definition) $sections[$code] = $definition + ['source_type' => 'structured_form', 'reviewer_type' => 'field', 'is_required' => false, 'fields' => self::reportFields()];
        $sections['bibliography'] = ['title' => 'Daftar Pustaka', 'description' => 'Tuliskan regulasi BPOM dan referensi yang digunakan.', 'source_type' => 'structured_form', 'reviewer_type' => 'internal', 'is_required' => false, 'fields' => [self::field('references', 'Daftar Referensi', 6, false)]];
        $sections['attachments'] = ['title' => 'Lampiran', 'description' => 'Catat daftar lampiran yang diizinkan.', 'source_type' => 'attachment_list', 'reviewer_type' => 'all', 'is_required' => false, 'fields' => [self::field('items', 'Daftar Lampiran', 5, false)]];
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
            ['table_of_contents', 'Daftar Isi', 'static_content', 'all', false], ['site_profile', 'Profil Tempat PKPA Loka POM', 'structured_form', 'field_internal', true],
            ['daily_logbook', 'Logbook Harian', 'auto_logbook', 'field', true],
        ];
        foreach (self::reportDefinitions() as $code => $definition) $sections[] = [$code, $definition['title'], 'structured_form', 'field', false];
        return array_merge($sections, [
            ['weekly_reflection', 'Refleksi Mingguan', 'weekly_reflection', 'internal', true], ['case_report', 'Studi Kasus Loka POM', 'repeatable_case', 'field', true],
            ['self_assessment', 'Self Assessment Loka POM', 'self_assessment', 'internal', true], ['documentation', 'Dokumentasi Kegiatan PKPA Loka POM', 'evidence_gallery', 'field', true],
            ['bibliography', 'Daftar Pustaka', 'structured_form', 'internal', false], ['attachments', 'Lampiran', 'attachment_list', 'all', false],
        ]);
    }
    private static function reportDefinitions(): array
    {
        return [
            'loka_pom_orientation' => self::report('Orientasi dan Pengenalan Loka POM'), 'organization_functions' => self::report('Struktur Organisasi dan Tugas Fungsi Loka POM'),
            'food_drug_regulation' => self::report('Regulasi Pengawasan Obat dan Makanan'), 'medicine_production_inspection' => self::report('Pemeriksaan Sarana Produksi Obat'),
            'medicine_distribution_inspection' => self::report('Pemeriksaan Sarana Distribusi Obat (CDOB)'), 'product_sampling' => self::report('Pengambilan Sampel Produk'),
            'laboratory_testing' => self::report('Observasi Pengujian Laboratorium'), 'traditional_medicine_surveillance' => self::report('Pengawasan Obat Tradisional'),
            'cosmetics_surveillance' => self::report('Pengawasan Kosmetik'), 'supplement_surveillance' => self::report('Pengawasan Suplemen Kesehatan'),
            'processed_food_surveillance' => self::report('Pengawasan Pangan Olahan'), 'marketplace_surveillance' => self::report('Pengawasan Produk di Marketplace'),
            'public_complaints' => self::report('Penanganan Pengaduan Masyarakat'), 'public_education' => self::report('Komunikasi Informasi dan Edukasi (KIE)'),
            'surveillance_reporting' => self::report('Penyusunan Laporan Hasil Pengawasan'),
        ];
    }
    private static function report(string $name): array { return ['title' => 'Laporan Kegiatan: '.$name, 'description' => 'Isi tujuan, dasar teori, kegiatan, dan hasil pembelajaran pada topik ini.']; }
    private static function reportFields(): array { return [self::field('purpose', 'Tujuan', 4), self::field('theory', 'Dasar Teori', 5), self::field('activity', 'Kegiatan yang Dilaksanakan', 6), self::field('result', 'Hasil dan Pembelajaran', 5)]; }
    private static function field(string $name, string $label, int $rows, bool $required = true): array { return compact('name', 'label', 'rows', 'required') + ['type' => 'textarea']; }
}
