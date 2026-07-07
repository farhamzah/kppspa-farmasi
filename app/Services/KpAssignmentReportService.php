<?php

namespace App\Services;

use App\Models\KpAssignment;
use App\Models\KpPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class KpAssignmentReportService
{
    public function query(Request $request): Builder
    {
        $query = KpAssignment::query()
            ->with(['period', 'student.user', 'place', 'internalSupervisor.user', 'fieldSupervisor.user'])
            ->when($request->filled('period'), fn ($q) => $q->where('kp_period_id', $request->period))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('q'), function ($q) use ($request) {
                $keyword = $request->q;
                $q->whereHas('student', fn ($student) => $student->where('nim', 'like', "%{$keyword}%")
                    ->orWhereHas('user', fn ($user) => $user->where('name', 'like', "%{$keyword}%")->orWhere('email', 'like', "%{$keyword}%")));
            })
            ->when($request->filled('place'), function ($q) use ($request) {
                $keyword = $request->place;
                $q->whereHas('place', fn ($place) => $place
                    ->where('name', 'like', "%{$keyword}%")
                    ->orWhere('city', 'like', "%{$keyword}%")
                    ->orWhere('address', 'like', "%{$keyword}%"));
            })
            ->when($request->filled('internal_supervisor'), function ($q) use ($request) {
                $keyword = $request->internal_supervisor;
                $q->whereHas('internalSupervisor', fn ($lecturer) => $lecturer
                    ->where('nidn_nip', 'like', "%{$keyword}%")
                    ->orWhere('employee_number', 'like', "%{$keyword}%")
                    ->orWhereHas('user', fn ($user) => $user->where('name', 'like', "%{$keyword}%")->orWhere('email', 'like', "%{$keyword}%")));
            })
            ->when($request->filled('field_supervisor'), function ($q) use ($request) {
                $keyword = $request->field_supervisor;
                $q->whereHas('fieldSupervisor', fn ($supervisor) => $supervisor
                    ->where('institution_name', 'like', "%{$keyword}%")
                    ->orWhere('position', 'like', "%{$keyword}%")
                    ->orWhereHas('user', fn ($user) => $user->where('name', 'like', "%{$keyword}%")->orWhere('email', 'like', "%{$keyword}%")));
            });

        return $this->applySort($query, (string) $request->input('sort', 'latest'));
    }

    public function rows(Request $request): Collection
    {
        return $this->query($request)
            ->get()
            ->values()
            ->map(function (KpAssignment $assignment, int $index) {
                $studentDisplay = app(KpMasterDataReadService::class)->getStudentDisplayData($assignment->student);
                $internalSupervisorDisplay = $assignment->internalSupervisor
                    ? app(KpMasterDataReadService::class)->getLecturerDisplayData($assignment->internalSupervisor)
                    : null;

                return [
                    'No' => $index + 1,
                    'Mahasiswa' => $studentDisplay->name,
                    'NIM' => $studentDisplay->studentNumber ?: '-',
                    'Periode' => $assignment->period?->name ?? '-',
                    'Tempat KP' => $assignment->place?->name ?? '-',
                    'Pembimbing Dalam' => $internalSupervisorDisplay?->name ?? '-',
                    'Pembimbing Lapangan' => $assignment->fieldSupervisor ? field_supervisor_display_name($assignment->fieldSupervisor) : '-',
                    'Status' => $assignment->statusLabel(),
                    'Tanggal Penempatan' => $assignment->assigned_at?->format('d/m/Y H:i') ?? '-',
                    'Catatan' => $assignment->note ?: '-',
                ];
            });
    }

    public function filterSummary(Request $request): array
    {
        return [
            'Mahasiswa/NIM' => $request->filled('q') ? $request->q : 'Semua',
            'Tempat KP' => $request->filled('place') ? $request->place : 'Semua',
            'Periode' => $this->periodLabel($request),
            'Status' => $this->statusOptions()[$request->input('status')] ?? 'Semua',
            'Pembimbing Dalam' => $request->filled('internal_supervisor') ? $request->internal_supervisor : 'Semua',
            'Pembimbing Lapangan' => $request->filled('field_supervisor') ? $request->field_supervisor : 'Semua',
            'Urutan' => $this->sortOptions()[$request->input('sort', 'latest')] ?? $this->sortOptions()['latest'],
            'Dicetak pada' => now()->format('d/m/Y H:i'),
        ];
    }

    public function periods(): Collection
    {
        return KpPeriod::latest()->get();
    }

    public function statusOptions(): array
    {
        return [
            'menunggu_pembimbing' => 'Menunggu Pembimbing',
            'aktif' => 'Aktif',
            'berjalan' => 'Berjalan',
            'selesai' => 'Selesai',
            'dibatalkan' => 'Dibatalkan',
        ];
    }

    public function sortOptions(): array
    {
        return [
            'latest' => 'Terbaru',
            'student' => 'Nama mahasiswa',
            'period' => 'Periode',
            'place' => 'Tempat KP',
            'internal_supervisor' => 'Pembimbing dalam',
            'field_supervisor' => 'Pembimbing lapangan',
            'status' => 'Status',
        ];
    }

    private function applySort(Builder $query, string $sort): Builder
    {
        return match ($sort) {
            'student' => $query
                ->leftJoin('students as sort_students', 'sort_students.id', '=', 'kp_assignments.student_id')
                ->leftJoin('users as sort_student_users', 'sort_student_users.id', '=', 'sort_students.user_id')
                ->select('kp_assignments.*')
                ->orderBy('sort_student_users.name')
                ->orderByDesc('kp_assignments.id'),
            'period' => $query
                ->leftJoin('kp_periods as sort_periods', 'sort_periods.id', '=', 'kp_assignments.kp_period_id')
                ->select('kp_assignments.*')
                ->orderBy('sort_periods.name')
                ->orderByDesc('kp_assignments.id'),
            'place' => $query
                ->leftJoin('kp_places as sort_places', 'sort_places.id', '=', 'kp_assignments.kp_place_id')
                ->select('kp_assignments.*')
                ->orderBy('sort_places.name')
                ->orderByDesc('kp_assignments.id'),
            'internal_supervisor' => $query
                ->leftJoin('lecturers as sort_internal_lecturers', 'sort_internal_lecturers.id', '=', 'kp_assignments.internal_supervisor_id')
                ->leftJoin('users as sort_internal_users', 'sort_internal_users.id', '=', 'sort_internal_lecturers.user_id')
                ->select('kp_assignments.*')
                ->orderByRaw('sort_internal_users.name is null')
                ->orderBy('sort_internal_users.name')
                ->orderByDesc('kp_assignments.id'),
            'field_supervisor' => $query
                ->leftJoin('field_supervisors as sort_field_supervisors', 'sort_field_supervisors.id', '=', 'kp_assignments.field_supervisor_id')
                ->leftJoin('users as sort_field_users', 'sort_field_users.id', '=', 'sort_field_supervisors.user_id')
                ->select('kp_assignments.*')
                ->orderByRaw('sort_field_users.name is null')
                ->orderBy('sort_field_users.name')
                ->orderByDesc('kp_assignments.id'),
            'status' => $query->orderBy('status')->latest('id'),
            default => $query->latest(),
        };
    }

    private function periodLabel(Request $request): string
    {
        if (! $request->filled('period')) {
            return 'Semua';
        }

        return KpPeriod::find($request->period)?->name ?? 'Periode tidak ditemukan';
    }
}
