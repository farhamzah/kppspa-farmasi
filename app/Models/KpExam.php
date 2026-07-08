<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class KpExam extends Model
{
    protected $fillable = ['kp_exam_request_id', 'kp_assignment_id', 'supervisor_id', 'examiner_id', 'exam_date', 'start_time', 'end_time', 'mode', 'room', 'meeting_link', 'status', 'scheduled_by', 'scheduled_at', 'note'];

    protected function casts(): array
    {
        return ['exam_date' => 'date', 'scheduled_at' => 'datetime'];
    }

    public function request() { return $this->belongsTo(KpExamRequest::class, 'kp_exam_request_id'); }
    public function assignment() { return $this->belongsTo(KpAssignment::class, 'kp_assignment_id'); }
    public function supervisor() { return $this->belongsTo(Lecturer::class, 'supervisor_id'); }
    public function examiner() { return $this->belongsTo(Lecturer::class, 'examiner_id'); }
    public function examExaminers() { return $this->hasMany(KpExaminer::class, 'kp_exam_id'); }
    public function examiners() { return $this->belongsToMany(Lecturer::class, 'kp_exam_examiners', 'kp_exam_id', 'lecturer_id')->withPivot('sort_order')->withTimestamps()->orderBy('kp_exam_examiners.sort_order'); }
    public function scheduledBy() { return $this->belongsTo(User::class, 'scheduled_by'); }
    public function logs() { return $this->hasMany(KpExamLog::class, 'kp_exam_id'); }
    public function scores() { return $this->hasMany(KpScore::class, 'kp_exam_id'); }

    public function scopeForExaminer(Builder $query, ?int $lecturerId): Builder
    {
        if (! $lecturerId) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $q) use ($lecturerId): void {
            $q->where('examiner_id', $lecturerId)
                ->orWhereHas('examiners', fn (Builder $examiner) => $examiner->where('lecturers.id', $lecturerId));
        });
    }

    public function hasExaminer(int|Lecturer|null $lecturer): bool
    {
        $lecturerId = $lecturer instanceof Lecturer ? $lecturer->id : $lecturer;
        if (! $lecturerId) {
            return false;
        }

        if ((int) $this->examiner_id === (int) $lecturerId) {
            return true;
        }

        $this->loadMissing('examiners');

        return $this->examiners->contains('id', $lecturerId);
    }

    public function examinerIds(): array
    {
        $this->loadMissing('examiners');

        return $this->examiners->pluck('id')
            ->when($this->examiner_id, fn ($ids) => $ids->prepend($this->examiner_id))
            ->unique()
            ->values()
            ->all();
    }

    public function examinerNamesLabel(): string
    {
        $this->loadMissing(['examiners.user', 'examiner.user']);
        $examiners = $this->examiners->isNotEmpty()
            ? $this->examiners
            : collect([$this->examiner])->filter();

        return $examiners->map(fn (Lecturer $lecturer): string => lecturer_display_name($lecturer))->implode(', ') ?: '-';
    }

    public function statusLabel(): string
    {
        return ['dijadwalkan' => 'Dijadwalkan', 'selesai' => 'Selesai', 'dibatalkan' => 'Dibatalkan', 'ditunda' => 'Ditunda'][$this->status] ?? ucfirst((string) $this->status);
    }

    public function statusBadgeClass(): string
    {
        return [
            'dijadwalkan' => 'bg-emerald-100 text-emerald-800 ring-emerald-200',
            'selesai' => 'bg-cyan-100 text-cyan-800 ring-cyan-200',
            'ditunda' => 'bg-amber-100 text-amber-800 ring-amber-200',
            'dibatalkan' => 'bg-red-100 text-red-800 ring-red-200',
        ][$this->status] ?? 'bg-slate-100 text-slate-700 ring-slate-200';
    }

    public function modeLabel(): string
    {
        return ['offline' => 'Offline', 'online' => 'Online', 'hybrid' => 'Hybrid'][$this->mode] ?? ucfirst((string) $this->mode);
    }

    public function scheduleLabel(): string
    {
        return $this->exam_date?->format('d M Y').' '.substr((string) $this->start_time, 0, 5).' - '.substr((string) $this->end_time, 0, 5);
    }

    public function canBeRescheduled(): bool
    {
        return in_array($this->status, ['dijadwalkan', 'ditunda'], true);
    }

    public function canBeCancelled(): bool
    {
        return ! in_array($this->status, ['selesai', 'dibatalkan'], true);
    }
}
