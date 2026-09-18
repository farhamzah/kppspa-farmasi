<?php

namespace Database\Seeders;

use App\Models\PkpaPortfolioTemplate;
use App\Models\PkpaPracticeDomain;
use App\Support\PkpaApotekPortfolio;
use Illuminate\Database\Seeder;

class PkpaPortfolioTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedTemplate('APT', 'PORT-APT-v1', 'Template Portofolio PKPA Apotek', PkpaApotekPortfolio::templateSections());

        $this->seedTemplate('RS', 'PORT-RS-v1', 'Template Portofolio PKPA Rumah Sakit', [
            ['cover', 'Sampul', 'static_content', 'all'],
            ['approval', 'Lembar Pengesahan', 'approval', 'field_internal'],
            ['common_sections', 'Bagian Umum', 'static_content', 'all'],
            ['identity', 'Identitas Mahasiswa', 'auto_identity', 'all'],
            ['integrity_pact', 'Pakta Integritas', 'approval', 'student'],
            ['hospital_competencies', 'Kompetensi Rumah Sakit', 'auto_competency', 'field_internal'],
            ['daily_logbook', 'Logbook Harian', 'auto_logbook', 'field'],
            ['pharmacy_warehouse', 'Gudang Farmasi Rumah Sakit', 'structured_form', 'field'],
            ['outpatient_pharmacy', 'Farmasi Rawat Jalan', 'structured_form', 'field'],
            ['inpatient_pharmacy', 'Farmasi Rawat Inap', 'structured_form', 'field'],
            ['clinical_pharmacy', 'Farmasi Klinik', 'structured_form', 'field_internal'],
            ['pio', 'Pelayanan Informasi Obat', 'structured_form', 'field'],
            ['counselling', 'Konseling', 'structured_form', 'field'],
            ['medication_reconciliation', 'Rekonsiliasi Obat', 'structured_form', 'field_internal'],
            ['adr_meso', 'ADR/MESO', 'structured_form', 'field_internal'],
            ['ward_round', 'Visite Ruang Rawat', 'structured_form', 'field_internal'],
            ['sterile_preparations', 'Sediaan Steril', 'structured_form', 'field'],
            ['weekly_reflection', 'Refleksi Mingguan', 'weekly_reflection', 'internal'],
            ['case_report', 'Studi Kasus', 'repeatable_case', 'field'],
            ['self_assessment', 'Penilaian Diri', 'self_assessment', 'internal'],
            ['field_assessment', 'Penilaian Pembimbing Lapangan', 'auto_assessment', 'field'],
            ['internal_assessment', 'Penilaian Pembimbing Dalam', 'auto_assessment', 'internal'],
            ['rubric', 'Rubrik', 'static_content', 'all'],
            ['documentation', 'Bukti Kegiatan', 'evidence_gallery', 'field'],
            ['attachments', 'Lampiran', 'attachment_list', 'all'],
        ]);

        $this->seedTemplate('PBF', 'PORT-PBF-v1', 'Template Portofolio PKPA Pedagang Besar Farmasi', [
            ['cover', 'Sampul', 'static_content', 'all'],
            ['approval', 'Lembar Pengesahan', 'approval', 'field_internal'],
            ['common_sections', 'Bagian Umum', 'static_content', 'all'],
            ['identity', 'Identitas Mahasiswa', 'auto_identity', 'all'],
            ['integrity_pact', 'Pakta Integritas', 'approval', 'student'],
            ['site_profile', 'Profil Tempat PKPA PBF', 'structured_form', 'field_internal'],
            ['daily_logbook', 'Logbook Harian', 'auto_logbook', 'field'],
            ['pbf_orientation', 'Laporan Kegiatan: Orientasi dan Pengenalan PBF', 'structured_form', 'field'],
            ['procurement', 'Laporan Kegiatan: Pengadaan', 'structured_form', 'field'],
            ['goods_receipt', 'Laporan Kegiatan: Penerimaan Barang', 'structured_form', 'field'],
            ['warehouse_storage', 'Laporan Kegiatan: Gudang dan Penyimpanan', 'structured_form', 'field'],
            ['cold_chain_product', 'Laporan Kegiatan: Cold Chain Product', 'structured_form', 'field'],
            ['inventory_control', 'Laporan Kegiatan: Inventory Control', 'structured_form', 'field'],
            ['picking_packing', 'Laporan Kegiatan: Picking dan Packing', 'structured_form', 'field'],
            ['distribution', 'Laporan Kegiatan: Distribusi', 'structured_form', 'field'],
            ['quality_assurance', 'Laporan Kegiatan: Quality Assurance', 'structured_form', 'field'],
            ['return_recall', 'Laporan Kegiatan: Penanganan Retur dan Recall', 'structured_form', 'field'],
            ['damaged_expired_products', 'Laporan Kegiatan: Produk Rusak dan Kedaluwarsa', 'structured_form', 'field'],
            ['weekly_reflection', 'Refleksi Mingguan', 'weekly_reflection', 'internal'],
            ['case_report', 'Studi Kasus PBF', 'repeatable_case', 'field'],
            ['self_assessment', 'Self Assessment PBF', 'self_assessment', 'internal'],
            ['field_assessment', 'Penilaian Preseptor', 'auto_assessment', 'field'],
            ['internal_assessment', 'Penilaian Pembimbing Dalam', 'auto_assessment', 'internal'],
            ['documentation', 'Dokumentasi Kegiatan PBF', 'evidence_gallery', 'field'],
            ['guidance', 'Formulir Bimbingan', 'structured_form', 'field_internal', false],
            ['attachments', 'Lampiran', 'attachment_list', 'all', false],
        ]);
    }

    private function seedTemplate(string $domainCode, string $code, string $name, array $sections): void
    {
        $domain = PkpaPracticeDomain::where('code', $domainCode)->firstOrFail();
        $template = PkpaPortfolioTemplate::updateOrCreate([
            'code' => $code,
            'version_number' => 1,
        ], [
            'practice_domain_id' => $domain->id,
            'name' => $name,
            'status' => 'active',
            'is_current' => true,
            'current_key' => 'DOMAIN:'.$domain->id,
            'export_configuration' => [
                'formats' => ['docx', 'pdf'],
                'cover' => true,
                'table_of_contents' => true,
                'page_numbering' => true,
                'internal_label_until_official' => true,
            ],
            'integrity_pact' => [
                'version' => 'v1',
                'text' => 'Saya menyatakan portofolio PKPA ini disusun jujur, tidak memuat identitas langsung pasien, dan digunakan untuk keperluan akademik internal MY PKPA. Persetujuan elektronik ini bukan tanda tangan digital tersertifikasi.',
            ],
        ]);

        $codes = [];
        foreach ($sections as $index => $sectionConfig) {
            [$sectionCode, $title, $sourceType, $reviewer] = array_slice($sectionConfig, 0, 4);
            $isRequired = $sectionConfig[4] ?? ! in_array($sourceType, ['attachment_list'], true);
            $staticContent = $sectionConfig[5] ?? null;
            $codes[] = $sectionCode;
            $template->sections()->updateOrCreate(['code' => $sectionCode], [
                'title' => $title,
                'source_type' => $sourceType,
                'reviewer_type' => $reviewer,
                'is_required' => $isRequired,
                'minimum_items' => match ($sourceType) {
                    'repeatable_case', 'weekly_reflection', 'self_assessment', 'evidence_gallery' => 1,
                    default => 0,
                },
                'sort_order' => ($index + 1) * 10,
                'requirement_rules' => [
                    'no_duplicate_existing_data' => str_starts_with($sourceType, 'auto_'),
                    'private_files' => in_array($sourceType, ['evidence_gallery', 'attachment_list'], true),
                ],
                'content_schema' => $this->schemaFor($sourceType, $sectionCode, $domainCode),
                'static_content' => $sourceType === 'static_content'
                    ? ($staticContent ?? 'Konten pola '.$title.' dikelola oleh Pembuat Portofolio MY PKPA.')
                    : null,
            ]);
        }
    }

    private function schemaFor(string $sourceType, ?string $sectionCode = null, ?string $domainCode = null): array
    {
        if ($domainCode === 'APT' && $sectionCode) {
            $apotekSection = PkpaApotekPortfolio::sectionDefinition($sectionCode);
            if ($apotekSection) {
                return array_filter([
                    'fields' => $apotekSection['fields'] ?? [],
                    'activity_hint' => $apotekSection['activity_hint'] ?? null,
                ]);
            }
        }

        if ($domainCode === 'PBF' && $sectionCode) {
            $field = fn (string $name, string $label, int $rows = 4, bool $required = true): array => [
                'name' => $name,
                'label' => $label,
                'type' => 'textarea',
                'rows' => $rows,
                'required' => $required,
            ];

            if ($sectionCode === 'site_profile') {
                return ['fields' => [
                    $field('overview', 'Gambaran Umum PBF', 4),
                    $field('vision', 'Visi', 3),
                    $field('mission', 'Misi', 3),
                    $field('main_duties', 'Tugas dan Tanggung Jawab Utama', 4),
                    $field('organization_structure', 'Struktur Organisasi', 4),
                    $field('facilities', 'Sarana dan Prasarana', 4),
                    $field('units_studied', 'Unit yang Dipelajari', 4),
                    $field('site_analysis', 'Analisis Pembelajaran di Tempat PKPA', 5),
                ]];
            }

            if ($sectionCode === 'guidance') {
                return ['fields' => [
                    $field('guidance_date', 'Tanggal Bimbingan', 1, false),
                    $field('topic', 'Topik Bimbingan', 2, false),
                    $field('notes', 'Catatan Bimbingan', 4, false),
                    $field('follow_up', 'Rencana Tindak Lanjut', 3, false),
                ]];
            }

            if (in_array($sectionCode, [
                'pbf_orientation', 'procurement', 'goods_receipt', 'warehouse_storage',
                'cold_chain_product', 'inventory_control', 'picking_packing', 'distribution',
                'quality_assurance', 'return_recall', 'damaged_expired_products',
            ], true)) {
                return ['fields' => [
                    $field('purpose', 'Tujuan', 3),
                    $field('theory', 'Dasar Teori atau Acuan', 4, false),
                    $field('activity', 'Kegiatan yang Dilaksanakan', 5),
                    $field('result', 'Hasil dan Pembelajaran', 5),
                ]];
            }
        }

        return match ($sourceType) {
            'repeatable_case' => ['fields' => ['case_code', 'case_date', 'patient_initials', 'gender', 'age', 'complaint', 'diagnosis', 'soap', 'drp', 'intervention', 'monitoring', 'education', 'references']],
            'weekly_reflection' => ['fields' => ['week_number', 'period_start_date', 'period_end_date', 'unit', 'target', 'achievement', 'obstacle', 'solution', 'reflection', 'next_plan']],
            'self_assessment' => ['score_scale' => [1, 5], 'fields' => ['aspect', 'score', 'evidence_experience', 'strength', 'weakness', 'improvement_plan', 'final_reflection']],
            'evidence_gallery' => ['fields' => ['category', 'activity_date', 'activity', 'description', 'competency_label', 'file']],
            default => [],
        };
    }
}
