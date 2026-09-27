<?php

namespace Database\Seeders;

use App\Models\PkpaPortfolioTemplate;
use App\Models\PkpaPracticeDomain;
use App\Support\PkpaApotekPortfolio;
use App\Support\PkpaHealthOfficePortfolio;
use App\Support\PkpaHospitalPortfolio;
use App\Support\PkpaIndustryPortfolio;
use App\Support\PkpaLokaPomPortfolio;
use App\Support\PkpaPbfPortfolio;
use App\Support\PkpaPuskesmasPortfolio;
use Illuminate\Database\Seeder;

class PkpaPortfolioTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedTemplate('APT', 'PORT-APT-v1', 'Template Portofolio PKPA Apotek', PkpaApotekPortfolio::templateSections());

        $this->seedTemplate('RS', 'PORT-RS-v1', 'Template Portofolio PKPA Rumah Sakit', PkpaHospitalPortfolio::templateSections());

        $this->seedTemplate('IND', 'PORT-IND-v1', 'Template Portofolio PKPA Industri Farmasi', PkpaIndustryPortfolio::templateSections());

        $this->seedTemplate('PEM', PkpaPuskesmasPortfolio::TEMPLATE_CODE, 'Template Portofolio PKPA Puskesmas', PkpaPuskesmasPortfolio::templateSections());
        $this->seedTemplate('PEM', PkpaHealthOfficePortfolio::TEMPLATE_CODE, 'Template Portofolio PKPA Dinas Kesehatan', PkpaHealthOfficePortfolio::templateSections());
        $this->seedTemplate('PEM', PkpaLokaPomPortfolio::TEMPLATE_CODE, 'Template Portofolio PKPA Loka POM', PkpaLokaPomPortfolio::templateSections());

        $this->seedTemplate('PBF', PkpaPbfPortfolio::TEMPLATE_CODE, 'Template Portofolio PKPA Pedagang Besar Farmasi', PkpaPbfPortfolio::templateSections());
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
                'content_schema' => $this->schemaFor($sourceType, $sectionCode, $domainCode, $code),
                'static_content' => $sourceType === 'static_content'
                    ? ($staticContent ?? 'Konten pola '.$title.' dikelola oleh Pembuat Portofolio MY PKPA.')
                    : null,
            ]);
        }
    }

    private function schemaFor(string $sourceType, ?string $sectionCode = null, ?string $domainCode = null, ?string $templateCode = null): array
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

        if ($domainCode === 'RS' && $sectionCode) {
            $hospitalSection = PkpaHospitalPortfolio::sectionDefinition($sectionCode);
            if ($hospitalSection) {
                return ['fields' => $hospitalSection['fields'] ?? []];
            }
        }

        if ($domainCode === 'IND' && $sectionCode) {
            $industrySection = PkpaIndustryPortfolio::sectionDefinition($sectionCode);
            if ($industrySection) {
                return ['fields' => $industrySection['fields'] ?? []];
            }
        }

        if ($domainCode === 'PEM' && $templateCode === PkpaPuskesmasPortfolio::TEMPLATE_CODE && $sectionCode) {
            $section = PkpaPuskesmasPortfolio::sectionDefinition($sectionCode);
            if ($section) {
                return ['fields' => $section['fields'] ?? []];
            }
        }
        if ($domainCode === 'PEM' && $templateCode === PkpaHealthOfficePortfolio::TEMPLATE_CODE && $sectionCode) {
            $section = PkpaHealthOfficePortfolio::sectionDefinition($sectionCode);
            if ($section) {
                return ['fields' => $section['fields'] ?? []];
            }
        }
        if ($domainCode === 'PEM' && $templateCode === PkpaLokaPomPortfolio::TEMPLATE_CODE && $sectionCode) {
            $section = PkpaLokaPomPortfolio::sectionDefinition($sectionCode);
            if ($section) {
                return ['fields' => $section['fields'] ?? []];
            }
        }

        if ($domainCode === 'PBF' && $sectionCode) {
            $section = PkpaPbfPortfolio::sectionDefinition($sectionCode);
            if ($section) {
                return ['fields' => $section['fields'] ?? []];
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
