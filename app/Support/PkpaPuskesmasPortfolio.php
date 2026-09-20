<?php

namespace App\Support;

class PkpaPuskesmasPortfolio
{
    public const TEMPLATE_CODE = 'PORT-PKM-v1';

    public static function editableSections(): array
    {
        $sections = ['site_profile' => [
            'title' => 'Profil Tempat PKPA Puskesmas',
            'description' => 'Lengkapi profil Puskesmas dan ruang farmasi sesuai format resmi.',
            'source_type' => 'structured_form', 'reviewer_type' => 'field_internal', 'is_required' => true,
            'fields' => collect([
                ['overview', 'Gambaran Umum Puskesmas', 5], ['history', 'Sejarah Singkat Puskesmas', 4],
                ['vision', 'Visi', 3], ['mission', 'Misi', 4], ['values', 'Nilai-Nilai Puskesmas', 4],
                ['organization', 'Struktur Organisasi Puskesmas', 4], ['pharmacy_profile', 'Profil Ruang Farmasi Puskesmas', 5],
                ['pharmacy_organization', 'Struktur Organisasi Farmasi Puskesmas', 4], ['human_resources', 'Sumber Daya Manusia', 4],
                ['facilities', 'Sarana dan Prasarana', 4], ['pharmacy_services', 'Pelayanan Kefarmasian', 5],
                ['health_programs', 'Program Kesehatan Puskesmas', 5], ['units_studied', 'Unit PKPA yang Dipelajari', 4],
                ['information_system', 'Sistem Informasi Puskesmas', 4], ['site_analysis', 'Analisis Singkat Tempat PKPA', 6],
            ])->map(fn ($field) => self::field(...$field))->all(),
        ]];

        foreach (self::reportDefinitions() as $code => $definition) {
            $sections[$code] = $definition + [
                'source_type' => 'structured_form', 'reviewer_type' => 'field', 'is_required' => false,
                'fields' => self::reportFields(),
            ];
        }

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
            ['table_of_contents', 'Daftar Isi', 'static_content', 'all', false], ['site_profile', 'Profil Tempat PKPA Puskesmas', 'structured_form', 'field_internal', true],
            ['daily_logbook', 'Logbook Harian', 'auto_logbook', 'field', true],
        ];
        foreach (self::reportDefinitions() as $code => $definition) $sections[] = [$code, $definition['title'], 'structured_form', 'field', false];
        return array_merge($sections, [
            ['weekly_reflection', 'Refleksi Mingguan', 'weekly_reflection', 'internal', true], ['case_report', 'Studi Kasus', 'repeatable_case', 'field', true],
            ['self_assessment', 'Self Assessment Puskesmas', 'self_assessment', 'internal', true], ['documentation', 'Dokumentasi Kegiatan PKPA Puskesmas', 'evidence_gallery', 'field', true],
            ['bibliography', 'Daftar Pustaka', 'structured_form', 'internal', false], ['attachments', 'Lampiran', 'attachment_list', 'all', false],
        ]);
    }

    private static function reportDefinitions(): array
    {
        return [
            'puskesmas_orientation' => self::report('Orientasi Puskesmas'), 'pharmacy_warehouse' => self::report('Pengelolaan Gudang Farmasi'),
            'pharmacy_management' => self::report('Kegiatan Manajerial di Instalasi Farmasi Puskesmas'), 'clinical_pharmacy' => self::report('Farmasi Klinik'),
            'pio' => self::report('Pelayanan Informasi Obat'), 'counselling' => self::report('Konseling Pasien'),
            'drp' => self::report('Drug Related Problems (DRPs)'), 'adr_meso' => self::report('Monitoring Efek Samping Obat'),
            'ward_round' => self::report('Visite Apoteker'), 'sterile_medicine' => self::report('Pelayanan Obat Steril'),
        ];
    }

    private static function report(string $name): array { return ['title' => 'Laporan Kegiatan: '.$name, 'description' => 'Isi tujuan, dasar teori, kegiatan, dan hasil pembelajaran pada unit ini.']; }
    private static function reportFields(): array { return [self::field('purpose', 'Tujuan', 4), self::field('theory', 'Dasar Teori', 5), self::field('activity', 'Kegiatan yang Dilaksanakan', 6), self::field('result', 'Hasil dan Pembelajaran', 5)]; }
    private static function field(string $name, string $label, int $rows, bool $required = true): array { return compact('name', 'label', 'rows', 'required') + ['type' => 'textarea']; }
}
