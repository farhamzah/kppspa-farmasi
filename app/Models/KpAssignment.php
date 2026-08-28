<?php

namespace App\Models;

use App\Support\KpScoreCalculator;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Model;

class KpAssignment extends Model
{
    public const WORKDAY_SENIN_JUMAT = 'senin_jumat';
    public const WORKDAY_SENIN_SABTU = 'senin_sabtu';

    public const WORKDAY_PATTERN_LABELS = [
        self::WORKDAY_SENIN_JUMAT => 'Senin - Jumat',
        self::WORKDAY_SENIN_SABTU => 'Senin - Sabtu',
    ];

    protected $fillable = [
        'kp_period_id', 'kp_registration_id', 'kp_place_selection_id', 'student_id', 'kp_place_id',
        'internal_supervisor_id', 'field_supervisor_id', 'status', 'assigned_by', 'assigned_at',
        'started_at', 'ended_at', 'workday_pattern', 'active_key', 'note',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'started_at' => 'date',
            'ended_at' => 'date',
        ];
    }

    public function period() { return $this->belongsTo(KpPeriod::class, 'kp_period_id'); }
    public function registration() { return $this->belongsTo(KpRegistration::class, 'kp_registration_id'); }
    public function selection() { return $this->belongsTo(KpPlaceSelection::class, 'kp_place_selection_id'); }
    public function student() { return $this->belongsTo(Student::class); }
    public function place() { return $this->belongsTo(KpPlace::class, 'kp_place_id'); }
    public function internalSupervisor() { return $this->belongsTo(Lecturer::class, 'internal_supervisor_id'); }
    public function fieldSupervisor() { return $this->belongsTo(FieldSupervisor::class, 'field_supervisor_id'); }
    public function assignedBy() { return $this->belongsTo(User::class, 'assigned_by'); }
    public function logs() { return $this->hasMany(KpAssignmentLog::class, 'kp_assignment_id'); }
    public function logbooks() { return $this->hasMany(KpLogbook::class, 'kp_assignment_id'); }
    public function finalReport() { return $this->hasOne(KpFinalReport::class, 'kp_assignment_id'); }
    public function reportGuidanceLogs() { return $this->hasMany(KpReportGuidanceLog::class, 'kp_assignment_id'); }
    public function examRequest() { return $this->hasOne(KpExamRequest::class, 'kp_assignment_id'); }
    public function exam() { return $this->hasOne(KpExam::class, 'kp_assignment_id'); }
    public function scores() { return $this->hasMany(KpScore::class, 'kp_assignment_id'); }
    public function finalScore() { return $this->hasOne(KpFinalScore::class, 'kp_assignment_id'); }
    public function competencyAchievements() { return $this->hasMany(KpCompetencyAchievement::class, 'kp_assignment_id'); }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'draft' => 'Draf',
            'aktif' => 'Aktif',
            'berjalan' => 'Berjalan',
            'selesai' => 'Selesai',
            'dibatalkan' => 'Dibatalkan',
            default => 'Menunggu Pembimbing',
        };
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            'aktif', 'berjalan' => 'bg-emerald-50 text-emerald-700',
            'selesai' => 'bg-slate-100 text-slate-700',
            'dibatalkan' => 'bg-rose-50 text-rose-700',
            default => 'bg-amber-50 text-amber-700',
        };
    }

    public function isCompleteSupervision(): bool
    {
        return filled($this->internal_supervisor_id) && filled($this->field_supervisor_id);
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['aktif', 'berjalan'], true);
    }

    public function canStart(): bool
    {
        return $this->isCompleteSupervision() && $this->status === 'aktif';
    }

    public function supervisorStatusLabel(): string
    {
        if ($this->isCompleteSupervision()) {
            return 'Lengkap';
        }

        if (! $this->internal_supervisor_id && ! $this->field_supervisor_id) {
            return 'Belum ada pembimbing';
        }

        return ! $this->internal_supervisor_id ? 'Belum ada pembimbing dalam' : 'Belum ada preseptor';
    }

    public function isEligibleForExamRequest(): bool
    {
        return collect($this->examEligibility()['items'])->every(fn (array $item): bool => $item['ready']);
    }

    public function examEligibility(): array
    {
        $this->loadMissing('finalReport');

        $approvedLogbooks = $this->logbooks()->where('status', 'disetujui')->count();
        $openLogbooks = $this->logbooks()->whereIn('status', ['menunggu_validasi', 'revisi', 'ditolak'])->count();
        $approvedGuidance = $this->reportGuidanceLogs()->where('status', 'disetujui')->count();
        $openGuidance = $this->reportGuidanceLogs()->whereIn('status', ['menunggu_validasi', 'revisi', 'ditolak'])->count();
        $report = $this->finalReport;

        $items = [
            [
                'key' => 'assignment_active',
                'label' => 'Penempatan KP aktif',
                'ready' => $this->isActive(),
                'description' => $this->statusLabel(),
            ],
            [
                'key' => 'field_logbook_validated',
                'label' => 'Logbook KP tervalidasi preseptor',
                'ready' => $approvedLogbooks > 0 && $openLogbooks === 0,
                'description' => $approvedLogbooks.' disetujui, '.$openLogbooks.' perlu tindak lanjut',
            ],
            [
                'key' => 'report_guidance_minimum',
                'label' => 'Bimbingan laporan minimal 8 kali',
                'ready' => $approvedGuidance >= 8 && $openGuidance === 0,
                'description' => $approvedGuidance.'/8 disetujui, '.$openGuidance.' perlu tindak lanjut',
            ],
            [
                'key' => 'final_report_submitted',
                'label' => 'Link/file laporan final tersedia',
                'ready' => $report && ($report->files()->exists() || filled($report->final_document_url)),
                'description' => $report?->latestFile?->original_filename ?? ($report?->final_document_label ?: ($report?->final_document_url ? 'Link final tersedia' : 'Belum tersedia')),
            ],
            [
                'key' => 'internal_report_approved',
                'label' => 'Laporan disetujui pembimbing dalam',
                'ready' => $report?->internal_review_status === 'disetujui',
                'description' => $report?->internalReviewStatusLabel() ?? 'Belum review',
            ],
            [
                'key' => 'field_report_approved',
                'label' => 'Laporan disetujui preseptor',
                'ready' => $report?->field_review_status === 'disetujui',
                'description' => $report?->fieldReviewStatusLabel() ?? 'Belum review',
            ],
        ];

        return [
            'ready' => collect($items)->every(fn (array $item): bool => $item['ready']),
            'items' => $items,
        ];
    }

    public function scoresCompletionPercentage(): int
    {
        $this->loadMissing('scores');
        $components = $this->period?->assessmentComponents()->where('status', 'aktif')->where('is_required', true)->get() ?? collect();

        if ($components->isEmpty()) {
            return 0;
        }

        $submitted = $components->filter(fn ($component) => in_array($this->scores->firstWhere('kp_assessment_component_id', $component->id)?->status, ['submitted', 'locked'], true))->count();

        return (int) round(($submitted / $components->count()) * 100);
    }

    public function isAllRequiredScoresSubmitted(): bool
    {
        $this->loadMissing(['scores', 'exam.examiners.user', 'exam.examiner.user']);
        $components = $this->period?->assessmentComponents()->where('status', 'aktif')->where('is_required', true)->get() ?? collect();

        return $components->isNotEmpty() && $components->every(function ($component): bool {
            if ($component->assessor_type !== 'penguji') {
                return in_array($this->scores->firstWhere('kp_assessment_component_id', $component->id)?->status, ['submitted', 'locked'], true);
            }

            $examinerUserIds = $this->exam
                ? $this->exam->examiners
                    ->when($this->exam->examiner, fn ($examiners) => $examiners->prepend($this->exam->examiner))
                    ->unique('id')
                    ->pluck('user_id')
                    ->filter()
                    ->values()
                : collect();

            if ($examinerUserIds->isEmpty()) {
                return false;
            }

            return $examinerUserIds->every(fn (int $userId): bool => $this->scores
                ->where('kp_assessment_component_id', $component->id)
                ->where('assessor_user_id', $userId)
                ->whereIn('status', ['submitted', 'locked'])
                ->isNotEmpty());
        });
    }

    public function calculateFinalScore(): float
    {
        return app(KpScoreCalculator::class)->breakdown($this)['final_score'];
    }

    public function workdayPatternLabel(): string
    {
        return self::WORKDAY_PATTERN_LABELS[$this->workday_pattern ?: self::WORKDAY_SENIN_JUMAT] ?? 'Senin - Jumat';
    }

    public function expectedWorkdaysCount(): int
    {
        $start = $this->started_at ?: $this->period?->kp_start_date;
        $end = $this->ended_at ?: $this->period?->kp_end_date;

        if (! $start || ! $end || $end->lt($start)) {
            return 0;
        }

        $days = 0;
        foreach (CarbonPeriod::create($start, $end) as $date) {
            if ($date->isSunday()) {
                continue;
            }

            if (($this->workday_pattern ?: self::WORKDAY_SENIN_JUMAT) === self::WORKDAY_SENIN_JUMAT && $date->isSaturday()) {
                continue;
            }

            $days++;
        }

        return $days;
    }
}
