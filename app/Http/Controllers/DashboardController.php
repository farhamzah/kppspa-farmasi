<?php

namespace App\Http\Controllers;

use App\Models\KpPeriod;
use App\Models\KpAssignment;
use App\Models\KpLogbook;
use App\Models\KpFinalReport;
use App\Models\KpExam;
use App\Models\KpExamRequest;
use App\Models\KpFinalScore;
use App\Models\KpPlace;
use App\Models\KpPlaceQuota;
use App\Models\KpPlaceSelection;
use App\Models\KpRegistration;
use App\Models\KpWaitingList;
use App\Models\PkpaPracticeDomain;
use App\Models\PkpaPracticeSite;
use App\Models\PkpaProgram;
use App\Models\PkpaEnrollment;
use App\Models\PkpaEnrollmentImportBatch;
use App\Models\PkpaEnrollmentRequirement;
use App\Models\PkpaInternalSupervisorEligibility;
use App\Models\PkpaPlacementPlan;
use App\Models\PkpaPlacementPublication;
use App\Models\PkpaPlacementValidationIssue;
use App\Models\PkpaProgramSite;
use App\Models\PkpaNotificationDelivery;
use App\Models\PkpaPlacementChangeRequest;
use App\Models\PkpaPublishedAssignment;
use App\Models\PkpaScheduleAcknowledgement;
use App\Models\PkpaRotationAssignment;
use App\Models\PkpaSiteAvailabilityPeriod;
use App\Models\PkpaSiteFieldSupervisor;
use App\Models\PkpaStudentGroup;
use App\Models\User;
use App\Models\UserImportBatch;
use App\Support\RoleDashboard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        $activeRole = $request->session()->get('active_role');

        return redirect()->route(RoleDashboard::routeFor($activeRole));
    }

    public function show(Request $request, string $role): View
    {
        return view('dashboard.show', [
            'role' => $role,
            'roleData' => RoleDashboard::dataFor($role),
            'features' => RoleDashboard::dataFor($role)['features'],
            'adminStats' => $role === 'admin' ? $this->adminStats() : null,
            'kpStats' => in_array($role, ['admin', 'koordinator_kp'], true) ? $this->kpStats() : null,
            'pkpaMasterStats' => in_array($role, ['admin', 'koordinator_kp'], true) ? $this->pkpaMasterStats() : null,
            'pkpaEnrollmentStats' => in_array($role, ['admin', 'koordinator_kp'], true) ? $this->pkpaEnrollmentStats() : null,
            'pkpaPlacementReadinessStats' => in_array($role, ['admin', 'koordinator_kp'], true) ? $this->pkpaPlacementReadinessStats() : null,
            'pkpaPlacementPlannerStats' => in_array($role, ['admin', 'koordinator_kp'], true) ? $this->pkpaPlacementPlannerStats() : null,
            'pkpaPublicationStats' => in_array($role, ['admin', 'koordinator_kp'], true) ? $this->pkpaPublicationStats() : null,
            'registrationStats' => in_array($role, ['admin', 'koordinator_kp'], true) ? $this->registrationStats() : null,
            'selectionStats' => in_array($role, ['admin', 'koordinator_kp'], true) ? $this->selectionStats() : null,
            'assignmentStats' => $this->assignmentStats($role, $request),
            'logbookStats' => $this->logbookStats($role, $request),
            'finalReportStats' => $this->finalReportStats($role, $request),
            'examStats' => $this->examStats($role, $request),
            'scoreStats' => $this->scoreStats($role, $request),
            'studentRegistration' => $role === 'mahasiswa' ? $request->user()->student?->kpRegistrations()->with(['documents', 'activePlaceSelection.place', 'waitingList'])->latest()->first() : null,
            'studentPkpaEnrollment' => $role === 'mahasiswa' ? $this->studentPkpaEnrollment($request) : null,
            'studentPkpaScheduleStats' => $role === 'mahasiswa' ? $this->studentPkpaScheduleStats($request) : null,
            'supervisorPkpaScheduleStats' => in_array($role, ['pembimbing_dalam', 'pembimbing_lapangan'], true) ? $this->supervisorPkpaScheduleStats($role, $request) : null,
        ]);
    }

    private function adminStats(): array
    {
        return [
            'total_users' => User::count(),
            'active_users' => User::where('status', 'active')->count(),
            'inactive_users' => User::where('status', 'inactive')->count(),
            'incomplete_profiles' => User::where('profile_completed', false)->count(),
            'last_import' => UserImportBatch::latest()->first(),
        ];
    }

    private function kpStats(): array
    {
        return [
            'total_periods' => KpPeriod::count(),
            'open_periods' => KpPeriod::where('status', 'dibuka')->count(),
            'active_places' => KpPlace::where('status', 'aktif')->count(),
            'total_quota' => KpPlaceQuota::sum('quota'),
            'open_quotas' => KpPlaceQuota::where('is_open', true)->count(),
        ];
    }

    private function pkpaMasterStats(): array
    {
        return [
            'program_aktif' => PkpaProgram::where('status', 'active')->count(),
            'program_draft' => PkpaProgram::where('status', 'draft')->count(),
            'wahana_aktif' => PkpaPracticeDomain::where('is_active', true)->count(),
            'tempat_praktik_aktif' => PkpaPracticeSite::where('is_active', true)->where('status', 'active')->count(),
            'kerja_sama_akan_berakhir' => PkpaPracticeSite::whereNotNull('cooperation_end_date')->whereBetween('cooperation_end_date', [now(), now()->addDays(90)])->count(),
            'program_belum_lengkap' => PkpaProgram::whereIn('status', ['draft', 'ready'])->get()->filter(fn (PkpaProgram $program) => ! $program->isReadyForActivation())->count(),
        ];
    }

    private function pkpaEnrollmentStats(): array
    {
        $latestImport = PkpaEnrollmentImportBatch::latest()->first();

        return [
            'peserta_aktif' => PkpaEnrollment::where('status', 'active')->count(),
            'peserta_belum_berkelompok' => PkpaEnrollment::where('status', 'active')->whereDoesntHave('activeGroupMembership')->count(),
            'kelompok_aktif' => PkpaStudentGroup::where('is_active', true)->where('status', 'active')->count(),
            'sync_core_bermasalah' => PkpaEnrollment::whereIn('last_core_sync_status', ['failed', 'warning'])->count(),
            'akun_core_nonaktif' => PkpaEnrollment::where('core_account_status_snapshot', 'inactive')->count(),
            'requirement_belum_lengkap' => PkpaEnrollment::whereDoesntHave('requirements')->count()
                + PkpaEnrollmentRequirement::whereIn('status', ['failed', 'cancelled'])->count(),
            'import_terakhir_valid' => $latestImport?->valid_rows ?? 0,
        ];
    }

    private function pkpaPlacementReadinessStats(): array
    {
        return [
            'tempat_program_aktif' => PkpaProgramSite::where('is_active', true)->whereIn('status', ['ready', 'active'])->count(),
            'tempat_tanpa_availability' => PkpaProgramSite::where('is_active', true)->whereIn('status', ['ready', 'active'])->whereDoesntHave('availabilityPeriods', fn ($query) => $query->whereIn('status', ['available', 'full']))->count(),
            'kapasitas_rencana' => PkpaSiteAvailabilityPeriod::whereIn('status', ['available', 'full'])->sum('maximum_students'),
            'availability_aktif' => PkpaSiteAvailabilityPeriod::whereIn('status', ['available', 'full'])->count(),
            'pembimbing_dalam_aktif' => PkpaInternalSupervisorEligibility::where('status', 'active')->distinct('core_user_id')->count('core_user_id'),
            'pembimbing_lapangan_aktif' => PkpaSiteFieldSupervisor::where('status', 'active')->count(),
            'pembimbing_perlu_sync' => PkpaInternalSupervisorEligibility::where(fn ($query) => $query->whereNull('last_core_synced_at')->orWhere('last_core_synced_at', '<', now()->subDays(30)))->distinct('core_user_id')->count('core_user_id')
                + PkpaSiteFieldSupervisor::where(fn ($query) => $query->whereNull('last_core_synced_at')->orWhere('last_core_synced_at', '<', now()->subDays(30)))->count(),
            'akun_core_nonaktif_pembimbing' => PkpaInternalSupervisorEligibility::where('core_account_status_snapshot', 'inactive')->distinct('core_user_id')->count('core_user_id')
                + PkpaSiteFieldSupervisor::where('core_account_status_snapshot', 'inactive')->count(),
        ];
    }

    private function pkpaPlacementPlannerStats(): array
    {
        $currentPlans = PkpaPlacementPlan::where('is_current', true)->count();
        $currentPlanIds = PkpaPlacementPlan::where('is_current', true)->pluck('id');

        return [
            'rancangan_current' => $currentPlans,
            'versi_rancangan' => PkpaPlacementPlan::count(),
            'assignment_terisi' => PkpaRotationAssignment::whereIn('pkpa_placement_plan_id', $currentPlanIds)->whereNotIn('status', ['cancelled', 'superseded'])->count(),
            'assignment_valid' => PkpaRotationAssignment::whereIn('pkpa_placement_plan_id', $currentPlanIds)->where('status', 'valid')->count(),
            'warning' => PkpaPlacementValidationIssue::where('severity', 'warning')->where('is_resolved', false)->count(),
            'error' => PkpaPlacementValidationIssue::where('severity', 'error')->where('is_resolved', false)->count(),
            'kapasitas_kurang' => PkpaPlacementValidationIssue::where('category', 'capacity')->where('severity', 'error')->where('is_resolved', false)->count(),
            'pembimbing_overload' => PkpaPlacementValidationIssue::where('category', 'supervisor')->where('issue_code', 'like', '%OVERLOAD%')->where('is_resolved', false)->count(),
            'jadwal_overlap' => PkpaPlacementValidationIssue::where('issue_code', 'STUDENT_SCHEDULE_OVERLAP')->where('is_resolved', false)->count(),
        ];
    }

    private function pkpaPublicationStats(): array
    {
        $currentPublicationIds = PkpaPlacementPublication::current()->pluck('id');

        return [
            'publikasi_current' => $currentPublicationIds->count(),
            'assignment_resmi' => PkpaPublishedAssignment::whereIn('pkpa_placement_publication_id', $currentPublicationIds)->count(),
            'sudah_acknowledge' => PkpaScheduleAcknowledgement::whereIn('pkpa_placement_publication_id', $currentPublicationIds)->where('acknowledgement_type', 'acknowledged')->count(),
            'change_request_aktif' => PkpaPlacementChangeRequest::whereIn('status', ['draft', 'submitted', 'approved'])->count(),
            'notifikasi_pending' => PkpaNotificationDelivery::whereIn('status', ['pending', 'failed'])->count(),
        ];
    }

    private function studentPkpaScheduleStats(Request $request): array
    {
        $coreUserId = $request->user()->core_user_id;
        if (blank($coreUserId)) {
            return ['jadwal_resmi' => 0, 'sudah_acknowledge' => 0, 'belum_acknowledge' => 0];
        }

        $assignments = PkpaPublishedAssignment::query()
            ->forStudent($coreUserId)
            ->whereHas('publication', fn ($query) => $query->current())
            ->pluck('id');

        $acknowledged = PkpaScheduleAcknowledgement::whereIn('pkpa_published_assignment_id', $assignments)
            ->where('core_user_id', $coreUserId)
            ->where('acknowledgement_type', 'acknowledged')
            ->count();

        return [
            'jadwal_resmi' => $assignments->count(),
            'sudah_acknowledge' => $acknowledged,
            'belum_acknowledge' => max(0, $assignments->count() - $acknowledged),
        ];
    }

    private function supervisorPkpaScheduleStats(string $role, Request $request): array
    {
        $type = $role === 'pembimbing_lapangan' ? 'field' : 'internal';
        $coreUserId = $request->user()->core_user_id;
        if (blank($coreUserId)) {
            return ['jadwal_resmi' => 0, 'sudah_acknowledge' => 0];
        }

        $assignments = PkpaPublishedAssignment::query()
            ->forSupervisor($type, $coreUserId)
            ->whereHas('publication', fn ($query) => $query->current())
            ->pluck('id');

        return [
            'jadwal_resmi' => $assignments->count(),
            'sudah_acknowledge' => PkpaScheduleAcknowledgement::whereIn('pkpa_published_assignment_id', $assignments)
                ->where('core_user_id', $coreUserId)
                ->where('acknowledgement_type', 'acknowledged')
                ->count(),
        ];
    }

    private function studentPkpaEnrollment(Request $request): ?PkpaEnrollment
    {
        if (blank($request->user()->core_user_id)) {
            return null;
        }

        return PkpaEnrollment::query()
            ->with(['program', 'requirements', 'activeGroupMembership.group'])
            ->where('core_user_id', $request->user()->core_user_id)
            ->whereIn('status', ['active', 'on_hold', 'completed'])
            ->latest()
            ->first();
    }

    private function registrationStats(): array
    {
        return [
            'total' => KpRegistration::count(),
            'pending' => KpRegistration::where('status', 'menunggu_verifikasi')->count(),
            'revision' => KpRegistration::where('status', 'revisi')->count(),
            'verified' => KpRegistration::where('status', 'terverifikasi')->count(),
            'rejected' => KpRegistration::where('status', 'ditolak')->count(),
        ];
    }

    private function selectionStats(): array
    {
        $totalQuota = KpPlaceQuota::sum('quota');
        $selected = KpPlaceSelection::where('status', 'aktif')->count();

        return [
            'selected' => $selected,
            'waiting' => KpWaitingList::where('status', 'menunggu')->count(),
            'remaining_quota' => max(0, $totalQuota - $selected),
            'full_places' => KpPlaceQuota::get()->filter->isFull()->count(),
        ];
    }

    private function assignmentStats(string $role, Request $request): ?array
    {
        if (in_array($role, ['admin', 'koordinator_kp'], true)) {
            return [
                'total' => KpAssignment::count(),
                'waiting' => KpAssignment::where('status', 'menunggu_pembimbing')->count(),
                'active' => KpAssignment::whereIn('status', ['aktif', 'berjalan'])->count(),
                'cancelled' => KpAssignment::where('status', 'dibatalkan')->count(),
                'unassigned_selection' => KpPlaceSelection::where('status', 'aktif')->whereDoesntHave('assignment')->count(),
            ];
        }

        if ($role === 'pembimbing_dalam') {
            $lecturerId = $request->user()->lecturer?->id;

            if (! $lecturerId) {
                return ['total' => 0, 'active' => 0];
            }

            return [
                'total' => KpAssignment::where('internal_supervisor_id', $lecturerId)->count(),
                'active' => KpAssignment::where('internal_supervisor_id', $lecturerId)->whereIn('status', ['aktif', 'berjalan'])->count(),
            ];
        }

        if ($role === 'pembimbing_lapangan') {
            $fieldSupervisorId = $request->user()->fieldSupervisor?->id;

            if (! $fieldSupervisorId) {
                return ['total' => 0, 'active' => 0];
            }

            return [
                'total' => KpAssignment::where('field_supervisor_id', $fieldSupervisorId)->count(),
                'active' => KpAssignment::where('field_supervisor_id', $fieldSupervisorId)->whereIn('status', ['aktif', 'berjalan'])->count(),
            ];
        }

        return null;
    }

    private function logbookStats(string $role, Request $request): ?array
    {
        if (in_array($role, ['admin', 'koordinator_kp'], true)) {
            return [
                'total' => KpLogbook::count(),
                'menunggu_validasi' => KpLogbook::where('status', 'menunggu_validasi')->count(),
                'disetujui' => KpLogbook::where('status', 'disetujui')->count(),
                'revisi' => KpLogbook::where('status', 'revisi')->count(),
                'ditolak' => KpLogbook::where('status', 'ditolak')->count(),
            ];
        }

        if ($role === 'mahasiswa') {
            $assignment = $request->user()->student?->assignments()->whereIn('status', ['aktif', 'berjalan'])->latest()->first();

            return $assignment ? [
                'total' => $assignment->logbooks()->count(),
                'menunggu_validasi' => $assignment->logbooks()->where('status', 'menunggu_validasi')->count(),
                'disetujui' => $assignment->logbooks()->where('status', 'disetujui')->count(),
                'revisi' => $assignment->logbooks()->where('status', 'revisi')->count(),
            ] : ['total' => 0, 'menunggu_validasi' => 0, 'disetujui' => 0, 'revisi' => 0];
        }

        if ($role === 'pembimbing_lapangan') {
            $fieldSupervisorId = $request->user()->fieldSupervisor?->id;

            if (! $fieldSupervisorId) {
                return ['total' => 0, 'menunggu_validasi' => 0];
            }

            return [
                'total' => KpLogbook::whereHas('assignment', fn ($q) => $q->where('field_supervisor_id', $fieldSupervisorId))->count(),
                'menunggu_validasi' => KpLogbook::where('status', 'menunggu_validasi')->whereHas('assignment', fn ($q) => $q->where('field_supervisor_id', $fieldSupervisorId))->count(),
            ];
        }

        if ($role === 'pembimbing_dalam') {
            $lecturerId = $request->user()->lecturer?->id;

            if (! $lecturerId) {
                return ['total' => 0, 'komentar' => 0];
            }

            return [
                'total' => KpLogbook::whereHas('assignment', fn ($q) => $q->where('internal_supervisor_id', $lecturerId))->count(),
                'komentar' => KpLogbook::whereHas('comments', fn ($q) => $q->where('user_id', $request->user()->id))->count(),
            ];
        }

        return null;
    }

    private function finalReportStats(string $role, Request $request): ?array
    {
        if (in_array($role, ['admin', 'koordinator_kp'], true)) {
            return [
                'total' => KpFinalReport::count(),
                'menunggu_review' => KpFinalReport::where('status', 'menunggu_review')->count(),
                'revisi' => KpFinalReport::where('status', 'revisi')->count(),
                'disetujui' => KpFinalReport::where('status', 'disetujui')->count(),
            ];
        }

        if ($role === 'pembimbing_dalam') {
            $lecturerId = $request->user()->lecturer?->id;

            if (! $lecturerId) {
                return ['menunggu_review' => 0, 'revisi' => 0, 'disetujui' => 0];
            }

            return [
                'menunggu_review' => KpFinalReport::where('status', 'menunggu_review')->whereHas('assignment', fn ($q) => $q->where('internal_supervisor_id', $lecturerId))->count(),
                'revisi' => KpFinalReport::where('status', 'revisi')->whereHas('assignment', fn ($q) => $q->where('internal_supervisor_id', $lecturerId))->count(),
                'disetujui' => KpFinalReport::where('status', 'disetujui')->whereHas('assignment', fn ($q) => $q->where('internal_supervisor_id', $lecturerId))->count(),
            ];
        }

        if ($role === 'mahasiswa') {
            $assignment = $request->user()->student?->assignments()->whereIn('status', ['aktif', 'berjalan'])->latest()->first();
            $report = $assignment?->finalReport;

            return [
                'status_laporan' => $report?->statusLabel() ?? 'Belum upload',
                'versi' => $report?->current_version ?? 0,
            ];
        }

        return null;
    }

    private function examStats(string $role, Request $request): ?array
    {
        if (in_array($role, ['admin', 'koordinator_kp'], true)) {
            return [
                'total_pengajuan' => KpExamRequest::count(),
                'menunggu_jadwal' => KpExamRequest::whereIn('status', ['diajukan', 'disetujui'])->count(),
                'dijadwalkan' => KpExam::where('status', 'dijadwalkan')->count(),
                'selesai' => KpExam::where('status', 'selesai')->count(),
            ];
        }

        if ($role === 'mahasiswa') {
            $assignment = $request->user()->student?->assignments()->whereIn('status', ['aktif', 'berjalan'])->latest()->first();
            return [
                'status_pengajuan' => $assignment?->examRequest?->statusLabel() ?? 'Belum diajukan',
                'jadwal_ujian' => $assignment?->exam?->scheduleLabel() ?? 'Belum dijadwalkan',
            ];
        }

        if ($role === 'pembimbing_dalam') {
            $lecturerId = $request->user()->lecturer?->id;

            if (! $lecturerId) {
                return ['ujian_terjadwal' => 0];
            }

            return ['ujian_terjadwal' => KpExam::where('supervisor_id', $lecturerId)->where('status', 'dijadwalkan')->count()];
        }

        if ($role === 'penguji') {
            $lecturerId = $request->user()->lecturer?->id;

            if (! $lecturerId) {
                return ['ujian_ditugaskan' => 0, 'ujian_mendatang' => 0];
            }

            return [
                'ujian_ditugaskan' => KpExam::query()->forExaminer($lecturerId)->count(),
                'ujian_mendatang' => KpExam::query()->forExaminer($lecturerId)->where('status', 'dijadwalkan')->count(),
            ];
        }

        return null;
    }

    private function scoreStats(string $role, Request $request): ?array
    {
        if (in_array($role, ['admin', 'koordinator_kp'], true)) {
            return [
                'belum_lengkap' => KpAssignment::whereDoesntHave('finalScore', fn ($q) => $q->whereIn('status', ['locked', 'published']))->count(),
                'siap_finalisasi' => KpFinalScore::where('status', 'calculated')->count(),
                'sudah_publish' => KpFinalScore::where('status', 'published')->count(),
            ];
        }

        if ($role === 'mahasiswa') {
            $assignment = $request->user()->student?->assignments()->whereIn('status', ['aktif', 'berjalan', 'selesai'])->latest()->first();
            return ['status_nilai' => $assignment?->finalScore?->statusLabel() ?? 'Belum tersedia'];
        }

        if ($role === 'pembimbing_dalam') {
            $lecturerId = $request->user()->lecturer?->id;

            if (! $lecturerId) {
                return ['belum_submit' => 0];
            }

            return ['belum_submit' => KpAssignment::where('internal_supervisor_id', $lecturerId)->whereIn('status', ['aktif', 'berjalan'])->whereDoesntHave('scores', fn ($q) => $q->where('assessor_type', 'pembimbing_dalam')->whereIn('status', ['submitted', 'locked']))->count()];
        }

        if ($role === 'pembimbing_lapangan') {
            $fieldSupervisorId = $request->user()->fieldSupervisor?->id;

            if (! $fieldSupervisorId) {
                return ['belum_submit' => 0];
            }

            return ['belum_submit' => KpAssignment::where('field_supervisor_id', $fieldSupervisorId)->whereIn('status', ['aktif', 'berjalan'])->whereDoesntHave('scores', fn ($q) => $q->where('assessor_type', 'pembimbing_lapangan')->whereIn('status', ['submitted', 'locked']))->count()];
        }

        if ($role === 'penguji') {
            $lecturerId = $request->user()->lecturer?->id;

            if (! $lecturerId) {
                return ['ujian_belum_submit' => 0];
            }

            return ['ujian_belum_submit' => KpExam::query()->forExaminer($lecturerId)->whereDoesntHave('scores', fn ($q) => $q->where('assessor_type', 'penguji')->where('assessor_user_id', $request->user()->id)->whereIn('status', ['submitted', 'locked']))->count()];
        }

        return null;
    }
}
