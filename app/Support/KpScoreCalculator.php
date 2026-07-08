<?php

namespace App\Support;

use App\Models\KpAssignment;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

class KpScoreCalculator
{
    public const SECTION_WEIGHTS = [
        'kehadiran' => 15,
        'pembimbing_lapangan' => 35,
        'pembimbing_dalam' => 20,
        'penguji' => 30,
    ];

    public const SECTION_LABELS = [
        'kehadiran' => 'Kehadiran dan Logbook',
        'pembimbing_lapangan' => 'Pembimbing Lapangan',
        'pembimbing_dalam' => 'Pembimbing Dalam',
        'penguji' => 'Penguji Seminar KP',
    ];

    public function breakdown(KpAssignment $assignment): array
    {
        $assignment->loadMissing([
            'period.assessmentComponents',
            'scores.component',
            'scores.assessor',
            'logbooks',
            'finalScore',
        ]);

        $sections = [];
        foreach (self::SECTION_WEIGHTS as $key => $weight) {
            $score = $key === 'kehadiran'
                ? $this->attendanceScore($assignment)
                : $this->assessorSectionScore($assignment, $key);

            $sections[$key] = [
                'key' => $key,
                'label' => self::SECTION_LABELS[$key],
                'score' => round($score['score'], 2),
                'weight' => $weight,
                'contribution' => round(($score['score'] * $weight) / 100, 2),
                'meta' => $score['meta'] ?? [],
            ];
        }

        return [
            'sections' => $sections,
            'final_score' => round(collect($sections)->sum('contribution'), 2),
        ];
    }

    private function assessorSectionScore(KpAssignment $assignment, string $assessorType): array
    {
        $components = $assignment->period?->assessmentComponents
            ->where('status', 'aktif')
            ->where('assessor_type', $assessorType)
            ->sortBy('sort_order')
            ->values() ?? collect();

        if ($components->isEmpty()) {
            return ['score' => 0, 'meta' => ['components' => 0, 'assessors' => 0]];
        }

        $componentWeights = $components->mapWithKeys(fn ($component) => [$component->id => (float) $component->weight]);
        $weightTotal = max(0.01, (float) $componentWeights->sum());
        $scores = $assignment->scores
            ->where('assessor_type', $assessorType)
            ->whereIn('status', ['submitted', 'locked'])
            ->groupBy('assessor_user_id');

        if ($scores->isEmpty()) {
            return ['score' => 0, 'meta' => ['components' => $components->count(), 'assessors' => 0]];
        }

        $assessorScores = $scores->map(function (Collection $assessorScores) use ($components, $componentWeights, $weightTotal): float {
            $byComponent = $assessorScores->keyBy('kp_assessment_component_id');

            return round($components->sum(function ($component) use ($byComponent, $componentWeights, $weightTotal): float {
                $score = $byComponent->get($component->id);

                return $score ? ((float) $score->score * ((float) $componentWeights->get($component->id) / $weightTotal)) : 0;
            }), 2);
        });

        return [
            'score' => round((float) $assessorScores->avg(), 2),
            'meta' => [
                'components' => $components->count(),
                'assessors' => $assessorScores->count(),
                'component_weight_total' => round($weightTotal, 2),
            ],
        ];
    }

    private function attendanceScore(KpAssignment $assignment): array
    {
        $override = $assignment->finalScore?->attendance_score_override;
        if ($override !== null) {
            return [
                'score' => (float) $override,
                'meta' => ['source' => 'override', 'note' => $assignment->finalScore?->attendance_note],
            ];
        }

        $approvedDates = $assignment->logbooks
            ->where('status', 'disetujui')
            ->pluck('activity_date')
            ->filter()
            ->map(fn ($date) => $date->toDateString())
            ->unique()
            ->count();

        $workdays = $this->workdayCount($assignment);
        if ($workdays === 0) {
            $workdays = $approvedDates;
        }

        $score = $workdays > 0 ? min(100, ($approvedDates / $workdays) * 100) : 0;

        return [
            'score' => round($score, 2),
            'meta' => [
                'source' => 'logbook',
                'approved_logbook_days' => $approvedDates,
                'workdays' => $workdays,
            ],
        ];
    }

    private function workdayCount(KpAssignment $assignment): int
    {
        $start = $assignment->started_at ?: $assignment->period?->kp_start_date;
        $end = $assignment->ended_at ?: $assignment->period?->kp_end_date;

        if (! $start || ! $end || $end->lt($start)) {
            return 0;
        }

        $days = 0;
        foreach (CarbonPeriod::create($start, $end) as $date) {
            if (! $date->isWeekend()) {
                $days++;
            }
        }

        return $days;
    }
}
