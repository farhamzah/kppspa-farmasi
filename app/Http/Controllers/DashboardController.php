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
            'registrationStats' => in_array($role, ['admin', 'koordinator_kp'], true) ? $this->registrationStats() : null,
            'selectionStats' => in_array($role, ['admin', 'koordinator_kp'], true) ? $this->selectionStats() : null,
            'assignmentStats' => $this->assignmentStats($role, $request),
            'logbookStats' => $this->logbookStats($role, $request),
            'finalReportStats' => $this->finalReportStats($role, $request),
            'examStats' => $this->examStats($role, $request),
            'scoreStats' => $this->scoreStats($role, $request),
            'studentRegistration' => $role === 'mahasiswa' ? $request->user()->student?->kpRegistrations()->with(['documents', 'activePlaceSelection.place', 'waitingList'])->latest()->first() : null,
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
                'jadwal_sidang' => $assignment?->exam?->scheduleLabel() ?? 'Belum dijadwalkan',
            ];
        }

        if ($role === 'pembimbing_dalam') {
            $lecturerId = $request->user()->lecturer?->id;

            if (! $lecturerId) {
                return ['sidang_terjadwal' => 0];
            }

            return ['sidang_terjadwal' => KpExam::where('supervisor_id', $lecturerId)->where('status', 'dijadwalkan')->count()];
        }

        if ($role === 'penguji') {
            $lecturerId = $request->user()->lecturer?->id;

            if (! $lecturerId) {
                return ['sidang_ditugaskan' => 0, 'sidang_mendatang' => 0];
            }

            return [
                'sidang_ditugaskan' => KpExam::query()->forExaminer($lecturerId)->count(),
                'sidang_mendatang' => KpExam::query()->forExaminer($lecturerId)->where('status', 'dijadwalkan')->count(),
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
                return ['sidang_belum_submit' => 0];
            }

            return ['sidang_belum_submit' => KpExam::query()->forExaminer($lecturerId)->whereDoesntHave('scores', fn ($q) => $q->where('assessor_type', 'penguji')->where('assessor_user_id', $request->user()->id)->whereIn('status', ['submitted', 'locked']))->count()];
        }

        return null;
    }
}
