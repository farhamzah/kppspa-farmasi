<?php

namespace App\Support;

class PkpaPbfPortfolio
{
    public const TEMPLATE_CODE = 'PORT-PBF-v1';

    public static function isPbfCode(?string $code): bool
    {
        return strtoupper((string) $code) === 'PBF';
    }

    public static function editableSections(): array
    {
        $sections = [
            'site_profile' => [
                'title' => 'Profil Tempat PKPA PBF',
                'description' => 'Lengkapi profil, legalitas, organisasi, fasilitas, dan ruang lingkup operasional PBF.',
                'source_type' => 'structured_form',
                'reviewer_type' => 'field_internal',
                'is_required' => true,
                'fields' => [
                    self::field('overview', 'Gambaran Umum PBF', 5),
                    self::field('identity', 'Identitas dan Legalitas PBF', 4),
                    self::field('vision', 'Visi', 3),
                    self::field('mission', 'Misi', 4),
                    self::field('main_duties', 'Tugas, Fungsi, dan Ruang Lingkup Kegiatan', 5),
                    self::field('organization_structure', 'Struktur Organisasi dan Peran Apoteker Penanggung Jawab', 5),
                    self::field('facilities', 'Sarana, Prasarana, dan Kondisi Gudang', 5),
                    self::field('quality_system', 'Sistem Manajemen Mutu dan Penerapan CDOB', 5),
                    self::field('units_studied', 'Unit yang Dipelajari', 4),
                    self::field('site_analysis', 'Analisis Pembelajaran di Tempat PKPA', 6),
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
            'description' => 'Tuliskan CDOB, regulasi, SOP, dan referensi yang digunakan.',
            'source_type' => 'structured_form',
            'reviewer_type' => 'internal',
            'is_required' => false,
            'fields' => [self::field('references', 'Daftar Referensi', 6, false)],
        ];
        $sections['attachments'] = [
            'title' => 'Lampiran',
            'description' => 'Catat lampiran pendukung yang diizinkan oleh PBF.',
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
            ['cover', 'Sampul', 'static_content', 'all', false, 'Dokumen portofolio PKPA Pedagang Besar Farmasi mengikuti Panduan PKPA 2026 PSPPA UBP Karawang.'],
            ['approval', 'Lembar Pengesahan', 'approval', 'field_internal', false, null],
            ['vision_mission', 'Visi dan Misi', 'static_content', 'all', false, 'Mengacu pada Panduan PKPA 2026 Program Studi Pendidikan Profesi Apoteker UBP Karawang.'],
            ['rules', 'Tata Tertib PKPA', 'static_content', 'all', false, 'Mahasiswa mengikuti tata tertib PKPA 2026, kebijakan PBF, serta ketentuan Cara Distribusi Obat yang Baik (CDOB).'],
            ['identity', 'Identitas Mahasiswa', 'auto_identity', 'all', false, null],
            ['integrity_pact', 'Pakta Integritas Mahasiswa PKPA', 'approval', 'student', false, null],
            ['table_of_contents', 'Daftar Isi', 'static_content', 'all', false, 'Daftar isi dibentuk otomatis mengikuti bagian portofolio PKPA PBF.'],
            ['site_profile', 'Profil Tempat PKPA PBF', 'structured_form', 'field_internal', true, null],
            ['daily_logbook', 'Logbook Harian', 'auto_logbook', 'field', true, null],
        ];

        foreach (self::reportDefinitions() as $code => $definition) {
            $sections[] = [$code, $definition['title'], 'structured_form', 'field', false, null];
        }

        return array_merge($sections, [
            ['weekly_reflection', 'Refleksi Mingguan', 'weekly_reflection', 'internal', true, null],
            ['case_report', 'Studi Kasus PBF', 'repeatable_case', 'field', true, null],
            ['self_assessment', 'Self Assessment PBF', 'self_assessment', 'internal', true, null],
            ['documentation', 'Dokumentasi Kegiatan PBF', 'evidence_gallery', 'field', true, null],
            ['bibliography', 'Daftar Pustaka', 'structured_form', 'internal', false, null],
            ['attachments', 'Lampiran', 'attachment_list', 'all', false, null],
        ]);
    }

    private static function reportDefinitions(): array
    {
        return [
            'pbf_orientation' => self::report('Orientasi, Organisasi, dan Peran Apoteker Penanggung Jawab', 'Catat struktur organisasi, alur kerja, legalitas, dan tanggung jawab APJ.'),
            'procurement' => self::report('Perencanaan dan Pengadaan', 'Catat perencanaan kebutuhan, pengadaan, dan pengendalian pemasok.'),
            'goods_receipt' => self::report('Penerimaan Barang', 'Catat pemeriksaan fisik, administrasi, kesesuaian dokumen, dan penanganan ketidaksesuaian.'),
            'warehouse_storage' => self::report('Gudang dan Penyimpanan', 'Catat zonasi, karantina, kondisi gudang, FIFO/FEFO, suhu, dan kelembaban.'),
            'cold_chain_product' => self::report('Cold Chain Product', 'Catat rantai dingin, alat pemantau suhu, dan penanganan temperature excursion.'),
            'inventory_control' => self::report('Pengendalian Persediaan', 'Catat stock opname, analisis stok, overstock, stockout, dan ketertelusuran.'),
            'picking_packing' => self::report('Picking dan Packing', 'Catat penyiapan pesanan, verifikasi, pengemasan, dan pencegahan kesalahan.'),
            'distribution' => self::report('Distribusi dan Transportasi', 'Catat pengiriman, transportasi, keamanan, mutu, dan bukti penerimaan produk.'),
            'documentation_administration' => self::report('Dokumentasi dan Administrasi Distribusi', 'Catat PO, faktur, surat jalan, pengarsipan, dan dokumen ketertelusuran.'),
            'quality_assurance' => self::report('Sistem Manajemen Mutu, Audit, CAPA, dan Manajemen Risiko', 'Catat SOP, deviasi, audit internal, CAPA, change control, dan manajemen risiko.'),
            'return_recall' => self::report('Keluhan, Retur, dan Recall', 'Catat investigasi keluhan, retur, klasifikasi recall, dan tindak lanjutnya.'),
            'damaged_expired_products' => self::report('Produk Rusak, Kedaluwarsa, Karantina, dan Pemusnahan', 'Catat identifikasi, pemisahan, karantina, dokumentasi, dan pemusnahan produk.'),
            'controlled_products_regulatory' => self::report('Narkotika, Psikotropika, Prekursor, dan Regulasi', 'Catat pengelolaan produk khusus, perizinan, pelaporan, dan kepatuhan regulasi.'),
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
            self::field('theory', 'Dasar Teori, CDOB, Regulasi, atau SOP', 5, false),
            self::field('activity', 'Kegiatan yang Dilaksanakan atau Diamati', 6),
            self::field('result', 'Hasil, Analisis, dan Pembelajaran', 6),
        ];
    }

    private static function field(string $name, string $label, int $rows, bool $required = true): array
    {
        return compact('name', 'label', 'rows', 'required') + ['type' => 'textarea'];
    }
}
