<?php

namespace App\Services;

use App\Models\PkpaDocumentDistributionLog;
use App\Models\PkpaEnrollment;
use App\Models\PkpaFinalGradeRelease;
use App\Models\PkpaFinalGradeResult;
use App\Models\PkpaGeneratedDocument;
use App\Models\PkpaGraduationDecision;
use App\Models\PkpaPlacementPublication;
use App\Models\PkpaProgram;
use App\Models\PkpaPublishedAssignment;
use App\Models\PkpaRemedialCase;
use App\Models\PkpaRotationGradeResult;
use App\Models\PkpaRotationRun;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PkpaAnalyticsService
{
    public function dashboard(?int $programId = null): array
    {
        $programs = PkpaProgram::query()->when($programId, fn (Builder $query) => $query->whereKey($programId));
        $enrollments = PkpaEnrollment::query()->when($programId, fn (Builder $query) => $query->where('pkpa_program_id', $programId));
        $assignments = PkpaPublishedAssignment::query()->when($programId, fn (Builder $query) => $query->whereHas('publication', fn ($sub) => $sub->where('pkpa_program_id', $programId)));
        $runs = PkpaRotationRun::query()->when($programId, fn (Builder $query) => $query->where('pkpa_program_id', $programId));
        $documents = PkpaGeneratedDocument::query()->when($programId, fn (Builder $query) => $query->where('pkpa_program_id', $programId));

        return [
            'program' => [
                'program_count' => (clone $programs)->count(),
                'participants' => (clone $enrollments)->count(),
                'active' => (clone $enrollments)->where('status', 'active')->count(),
                'completed' => (clone $enrollments)->where('status', 'completed')->count(),
                'cancelled' => (clone $enrollments)->where('status', 'cancelled')->count(),
                'six_requirements_completed' => (clone $enrollments)->whereDoesntHave('requirements', fn ($query) => $query->where('status', '!=', 'completed'))->count(),
            ],
            'placement' => [
                'assignments' => (clone $assignments)->count(),
                'published_publications' => PkpaPlacementPublication::query()->when($programId, fn ($query) => $query->where('pkpa_program_id', $programId))->where('status', 'published')->count(),
                'acknowledged' => (clone $assignments)->whereHas('acknowledgements', fn ($query) => $query->where('acknowledgement_type', 'acknowledged'))->count(),
                'sites' => (clone $assignments)->distinct('practice_site_id')->count('practice_site_id'),
            ],
            'operations' => [
                'runs' => (clone $runs)->count(),
                'operational_completed' => (clone $runs)->where('operational_status', 'completed')->count(),
                'attendance_approved' => (clone $runs)->whereHas('attendanceRecords', fn ($query) => $query->where('status', 'approved'))->count(),
                'logbook_approved' => (clone $runs)->whereHas('logbookEntries', fn ($query) => $query->where('status', 'approved'))->count(),
            ],
            'assessment' => [
                'wahana_grade_finalized' => PkpaRotationGradeResult::query()->when($programId, fn ($query) => $query->whereHas('run', fn ($sub) => $sub->where('pkpa_program_id', $programId)))->whereIn('result_status', ['finalized', 'released'])->count(),
                'final_grade_finalized' => PkpaFinalGradeResult::query()->when($programId, fn ($query) => $query->whereHas('enrollment', fn ($sub) => $sub->where('pkpa_program_id', $programId)))->whereIn('result_status', ['finalized', 'released'])->count(),
                'final_released' => PkpaFinalGradeRelease::query()->when($programId, fn ($query) => $query->whereHas('enrollment', fn ($sub) => $sub->where('pkpa_program_id', $programId)))->where('status', 'released')->count(),
            ],
            'graduation' => [
                'passed' => PkpaGraduationDecision::query()->when($programId, fn ($query) => $query->whereHas('enrollment', fn ($sub) => $sub->where('pkpa_program_id', $programId)))->where('decision', 'passed')->count(),
                'not_passed' => PkpaGraduationDecision::query()->when($programId, fn ($query) => $query->whereHas('enrollment', fn ($sub) => $sub->where('pkpa_program_id', $programId)))->where('decision', 'not_passed')->count(),
                'pending_remedial' => PkpaGraduationDecision::query()->when($programId, fn ($query) => $query->whereHas('enrollment', fn ($sub) => $sub->where('pkpa_program_id', $programId)))->where('decision', 'pending_remedial')->count(),
                'remedial_cases' => PkpaRemedialCase::query()->when($programId, fn ($query) => $query->whereHas('enrollment', fn ($sub) => $sub->where('pkpa_program_id', $programId)))->count(),
            ],
            'documents' => [
                'draft' => (clone $documents)->where('status', 'draft')->count(),
                'generated' => (clone $documents)->where('status', 'generated')->count(),
                'published' => (clone $documents)->where('status', 'published')->count(),
                'cancelled' => (clone $documents)->where('status', 'cancelled')->count(),
                'downloads' => PkpaDocumentDistributionLog::where('channel', 'download')->count(),
            ],
        ];
    }

    public function rows(?int $programId = null): Collection
    {
        $data = $this->dashboard($programId);

        return collect($data)->flatMap(fn (array $items, string $section) => collect($items)->map(fn ($value, string $key) => [
            'section' => $section,
            'metric' => $key,
            'value' => $value,
        ]))->values();
    }
}
