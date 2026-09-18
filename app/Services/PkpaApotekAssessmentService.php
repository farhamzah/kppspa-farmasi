<?php

namespace App\Services;

use App\Models\PkpaRotationComponentScore;
use App\Models\User;
use App\Support\PkpaApotekAssessment;
use App\Support\PkpaApotekPortfolio;
use Carbon\CarbonPeriod;
use Illuminate\Validation\ValidationException;

class PkpaApotekAssessmentService
{
    public function __construct(
        private readonly PkpaRotationAssessmentService $assessments,
        private readonly PkpaRotationProgressService $progress
    ) {
    }

    public function save(PkpaRotationComponentScore $score, array $data, User $actor): PkpaRotationComponentScore
    {
        $score->loadMissing(['assessment.rotationRun.practiceDomain', 'assessment.rotationRun.requirement', 'component', 'assessor']);
        $this->ensureApotek($score);

        $assessorType = (string) $score->assessor?->assessor_type;
        $definitions = collect(PkpaApotekAssessment::criteria($assessorType));
        $submitted = collect($data['criteria'] ?? []);
        $attendance = $assessorType === 'field_supervisor'
            ? $this->attendanceSummary($score)
            : null;

        $criterionScores = $definitions->mapWithKeys(function (array $criterion) use ($submitted, $attendance) {
            $value = $criterion['automatic']
                ? ($attendance['score'] ?? null)
                : $submitted->get($criterion['code']);

            if ($value !== null && (! is_numeric($value) || (int) $value < 1 || (int) $value > 5)) {
                throw ValidationException::withMessages([
                    'criteria.'.$criterion['code'] => 'Skor harus berada pada rentang 1 sampai 5.',
                ]);
            }

            return [$criterion['code'] => $value === null ? null : (int) $value];
        });

        $total = $definitions->sum(function (array $criterion) use ($criterionScores) {
            $value = $criterionScores->get($criterion['code']);

            return $value === null ? 0 : ($criterion['weight'] * $value / 5);
        });

        $sections = collect(PkpaApotekAssessment::sections($assessorType))->map(function (array $section) use ($criterionScores) {
            $earned = collect($section['criteria'])->sum(function (array $criterion) use ($criterionScores) {
                $value = $criterionScores->get($criterion['code']);

                return $value === null ? 0 : ($criterion['weight'] * $value / 5);
            });

            return [
                'code' => $section['code'],
                'title' => $section['title'],
                'weight' => $section['weight'],
                'score' => round($earned, 2),
            ];
        })->values()->all();

        $saved = $this->assessments->saveDirectScore(
            $score,
            number_format($total, 4, '.', ''),
            $data['overall_comments'] ?? null,
            $actor
        );

        $saved->update(['source_summary' => [
            'format' => 'apotek_assessment_rubric',
            'version' => PkpaApotekAssessment::VERSION,
            'assessor_type' => $assessorType,
            'criteria' => $criterionScores->all(),
            'sections' => $sections,
            'attendance' => $attendance,
            'feedback' => [
                'strengths' => trim((string) ($data['strengths'] ?? '')),
                'improvements' => trim((string) ($data['improvements'] ?? '')),
                'development_suggestions' => trim((string) ($data['development_suggestions'] ?? '')),
            ],
            'recommendations' => [
                'competency_met' => $data['recommendations']['competency_met'] ?? null,
                'portfolio_accepted' => $data['recommendations']['portfolio_accepted'] ?? null,
                'final_exam_recommended' => $data['recommendations']['final_exam_recommended'] ?? null,
            ],
            'completed_criteria' => $criterionScores->filter(fn ($value) => $value !== null)->count(),
            'total_criteria' => $definitions->count(),
            'calculated_score' => round($total, 2),
            'updated_at' => now()->toIso8601String(),
        ]]);

        return $saved->fresh();
    }

    public function submit(PkpaRotationComponentScore $score, User $actor): PkpaRotationComponentScore
    {
        $score->refresh()->loadMissing('assessor');
        $summary = $score->source_summary ?? [];
        $missingCriteria = (int) data_get($summary, 'completed_criteria', 0) < (int) data_get($summary, 'total_criteria', 1);
        $recommendations = collect(data_get($summary, 'recommendations', []));

        if ($missingCriteria || $recommendations->count() !== 3 || $recommendations->contains(fn ($value) => ! in_array($value, ['yes', 'no'], true))) {
            throw ValidationException::withMessages([
                'assessment' => 'Lengkapi seluruh skor rubrik dan tiga rekomendasi sebelum nilai dikirim dan dikunci.',
            ]);
        }

        return $this->assessments->submitScore($score, $actor);
    }

    public function attendanceSummary(PkpaRotationComponentScore $score): array
    {
        $score->loadMissing('assessment.rotationRun.requirement');
        $run = $score->assessment->rotationRun;
        $rule = $this->progress->ruleFor($run);
        $approved = $run->attendanceRecords()->where('submission_status', 'approved')->count();
        $present = $run->attendanceRecords()
            ->where('submission_status', 'approved')
            ->where('attendance_type', 'present')
            ->count();
        $expected = $rule?->attendance_required
            ? $this->businessDays($run->scheduled_start_date, $run->scheduled_end_date)
            : 0;
        if ($expected === 0 && $approved > 0) {
            $expected = $approved;
        }
        $missing = max(0, $expected - $present);
        $scoreValue = match (true) {
            $expected === 0 => 1,
            $missing === 0 => 5,
            $missing === 1 => 4,
            $missing <= 3 => 3,
            $present > 0 => 2,
            default => 1,
        };

        return [
            'expected_days' => $expected,
            'approved_records' => $approved,
            'present_days' => $present,
            'missing_days' => $missing,
            'percentage' => $expected > 0 ? round(min(100, ($present / $expected) * 100), 2) : 0,
            'score' => $scoreValue,
            'generated_at' => now()->toIso8601String(),
        ];
    }

    private function ensureApotek(PkpaRotationComponentScore $score): void
    {
        if (! PkpaApotekPortfolio::isApotekCode($score->assessment?->rotationRun?->practiceDomain?->code)) {
            throw ValidationException::withMessages(['assessment' => 'Rubrik ini hanya berlaku untuk penilaian PKPA Apotek.']);
        }

        if (! in_array($score->assessor?->assessor_type, ['field_supervisor', 'internal_supervisor'], true)) {
            throw ValidationException::withMessages(['assessment' => 'Jenis penilai tidak sesuai dengan rubrik PKPA Apotek.']);
        }
    }

    private function businessDays($start, $end): int
    {
        if (! $start || ! $end || $end < $start) {
            return 0;
        }

        return collect(CarbonPeriod::create($start, $end))
            ->reject(fn ($date) => $date->isWeekend())
            ->count();
    }
}
