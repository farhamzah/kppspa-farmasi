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

        return match ($sourceType) {
            'repeatable_case' => ['fields' => ['case_code', 'case_date', 'patient_initials', 'gender', 'age', 'complaint', 'diagnosis', 'soap', 'drp', 'intervention', 'monitoring', 'education', 'references']],
            'weekly_reflection' => ['fields' => ['week_number', 'period_start_date', 'period_end_date', 'unit', 'target', 'achievement', 'obstacle', 'solution', 'reflection', 'next_plan']],
            'self_assessment' => ['score_scale' => [1, 5], 'fields' => ['aspect', 'score', 'evidence_experience', 'strength', 'weakness', 'improvement_plan', 'final_reflection']],
            'evidence_gallery' => ['fields' => ['category', 'activity_date', 'activity', 'description', 'competency_label', 'file']],
            default => [],
        };
    }
}
