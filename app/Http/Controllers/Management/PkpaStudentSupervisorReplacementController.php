<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Models\PkpaEnrollment;
use App\Models\PkpaInternalSupervisorEligibility;
use App\Models\PkpaPlacementPublication;
use App\Services\PkpaPlacementChangeRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PkpaStudentSupervisorReplacementController extends Controller
{
    private function selection(Request $request): array
    {
        abort_unless($request->user()->hasAnyRole(['admin', 'koordinator_kp']), 403);
        $data = $request->validate([
            'enrollment_ids' => ['required', 'array', 'min:1', 'max:100'],
            'enrollment_ids.*' => ['required', 'integer', 'distinct', 'exists:pkpa_enrollments,id'],
        ]);
        $students = PkpaEnrollment::with('program')->whereIn('id', $data['enrollment_ids'])->get();
        if ($students->count() !== count($data['enrollment_ids']) || $students->pluck('pkpa_program_id')->unique()->count() !== 1) {
            throw ValidationException::withMessages(['enrollment_ids' => 'Pilih mahasiswa dari satu program yang sama.']);
        }
        $publication = PkpaPlacementPublication::current()->where('status', 'published')
            ->where('pkpa_program_id', $students->first()->pkpa_program_id)
            ->with(['assignments' => fn ($q) => $q->whereIn('pkpa_enrollment_id', $students->pluck('id'))->with('supervisors')])->first();
        if (! $publication || $students->contains(fn ($s) => ! $publication->assignments->contains('pkpa_enrollment_id', $s->id))) {
            throw ValidationException::withMessages(['enrollment_ids' => 'Setiap mahasiswa harus memiliki penempatan resmi terkini.']);
        }

        return [$students, $publication];
    }

    public function create(Request $request): View
    {
        [$students, $publication] = $this->selection($request);
        $domainIds = $publication->assignments->pluck('practice_domain_id')->unique();
        $supervisors = PkpaInternalSupervisorEligibility::with('user.lecturer')->where('pkpa_program_id', $publication->pkpa_program_id)
            ->whereIn('practice_domain_id', $domainIds)->where('status', 'active')->whereNotNull('core_user_id')
            ->where(fn ($q) => $q->whereNull('core_account_status_snapshot')->orWhere('core_account_status_snapshot', '!=', 'inactive'))
            ->get()->groupBy('core_user_id')
            ->filter(fn ($items) => $items->pluck('practice_domain_id')->unique()->count() === $domainIds->count())
            ->map(fn ($items) => $items->first())->sortBy('name_snapshot')->values();

        return view('management.pkpa-enrollments.supervisor-replacement', compact('students', 'publication', 'supervisors'));
    }

    public function store(Request $request, PkpaPlacementChangeRequestService $changes): RedirectResponse
    {
        [$students, $publication] = $this->selection($request);
        $data = $request->validate([
            'publication_id' => ['required', 'integer'],
            'replacement_core_user_id' => ['required', 'string', 'max:80'],
            'effective_date' => ['required', 'date'],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
            'include_finished_reviews' => ['required', 'accepted'],
        ]);
        if ($publication->id !== (int) $data['publication_id']) {
            throw ValidationException::withMessages(['publication_id' => 'Jadwal resmi berubah. Muat ulang halaman penggantian.']);
        }
        $assignments = $publication->assignments->reject(fn ($a) => (string) $a->supervisors->firstWhere('supervisor_type', 'internal')?->core_user_id === $data['replacement_core_user_id']);
        if ($assignments->isEmpty()) {
            throw ValidationException::withMessages(['replacement_core_user_id' => 'Seluruh penempatan mahasiswa sudah memakai pembimbing tersebut.']);
        }
        $change = $changes->createInternalSupervisorReplacement($publication, $assignments->pluck('id')->all(), $data['replacement_core_user_id'], $data['effective_date'], $data['reason'], $request->user(), false, true);
        $change->update(['impact_summary' => array_merge($change->impact_summary, [
            'student_enrollment_ids' => $students->pluck('id')->all(), 'scope' => 'all_student_domains',
        ])]);

        return redirect()->route('management.pkpa-change-requests.show', $change)->with('status', 'Pratinjau penggantian seluruh wahana dibuat. Belum ada penugasan yang diubah.');
    }
}
