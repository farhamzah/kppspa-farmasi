<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Http\Requests\Management\Pkpa\BulkPkpaGroupMemberRequest;
use App\Http\Requests\Management\Pkpa\StorePkpaGroupMemberRequest;
use App\Http\Requests\Management\Pkpa\StorePkpaStudentGroupRequest;
use App\Http\Requests\Management\Pkpa\UpdatePkpaStudentGroupRequest;
use App\Models\PkpaEnrollment;
use App\Models\PkpaProgram;
use App\Models\PkpaStudentGroup;
use App\Models\PkpaStudentGroupMember;
use App\Services\PkpaStudentGroupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PkpaStudentGroupController extends Controller
{
    public function __construct(private readonly PkpaStudentGroupService $groupService)
    {
    }

    public function index(Request $request): View
    {
        $groups = PkpaStudentGroup::query()
            ->with('program')
            ->withCount(['activeMembers'])
            ->search($request->input('q'))
            ->when($request->filled('program_id'), fn ($query) => $query->where('pkpa_program_id', $request->program_id))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->status))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('management.pkpa-student-groups.index', [
            'groups' => $groups,
            'programs' => PkpaProgram::orderByDesc('id')->get(),
            'filters' => $request->only(['q', 'program_id', 'status']),
        ]);
    }

    public function create(): View
    {
        return view('management.pkpa-student-groups.create', [
            'group' => new PkpaStudentGroup(['status' => 'active', 'is_active' => true]),
            'programs' => PkpaProgram::orderByDesc('id')->get(),
        ]);
    }

    public function store(StorePkpaStudentGroupRequest $request): RedirectResponse
    {
        $group = $this->groupService->create($request->validated() + ['is_active' => $request->boolean('is_active', true)], $request->user());

        return redirect()->route('management.pkpa-student-groups.show', $group)->with('status', 'Kelompok mahasiswa berhasil dibuat.');
    }

    public function show(PkpaStudentGroup $pkpaStudentGroup): View
    {
        $pkpaStudentGroup->load(['program', 'activeMembers.enrollment', 'members.enrollment']);

        return view('management.pkpa-student-groups.show', [
            'group' => $pkpaStudentGroup,
            'availableEnrollments' => PkpaEnrollment::query()
                ->where('pkpa_program_id', $pkpaStudentGroup->pkpa_program_id)
                ->where('status', 'active')
                ->whereDoesntHave('activeGroupMembership')
                ->orderBy('student_name_snapshot')
                ->get(),
        ]);
    }

    public function edit(PkpaStudentGroup $pkpaStudentGroup): View
    {
        return view('management.pkpa-student-groups.edit', [
            'group' => $pkpaStudentGroup,
            'programs' => PkpaProgram::orderByDesc('id')->get(),
        ]);
    }

    public function update(UpdatePkpaStudentGroupRequest $request, PkpaStudentGroup $pkpaStudentGroup): RedirectResponse
    {
        $this->groupService->update($pkpaStudentGroup, $request->validated() + ['is_active' => $request->boolean('is_active')], $request->user());

        return redirect()->route('management.pkpa-student-groups.show', $pkpaStudentGroup)->with('status', 'Kelompok mahasiswa berhasil diperbarui.');
    }

    public function addMember(StorePkpaGroupMemberRequest $request, PkpaStudentGroup $pkpaStudentGroup): RedirectResponse
    {
        $this->groupService->addMember($pkpaStudentGroup, PkpaEnrollment::findOrFail($request->validated('pkpa_enrollment_id')), $request->user(), $request->validated('notes') ?? null);

        return back()->with('status', 'Anggota berhasil ditambahkan ke kelompok.');
    }

    public function bulkMembers(BulkPkpaGroupMemberRequest $request, PkpaStudentGroup $pkpaStudentGroup): RedirectResponse
    {
        $count = $this->groupService->addMembers($pkpaStudentGroup, $request->validated('enrollment_ids'), $request->user());

        return back()->with('status', "{$count} peserta berhasil dimasukkan ke kelompok.");
    }

    public function removeMember(PkpaStudentGroup $pkpaStudentGroup, PkpaStudentGroupMember $member, Request $request): RedirectResponse
    {
        abort_unless((int) $member->pkpa_student_group_id === (int) $pkpaStudentGroup->id, 404);
        $this->groupService->removeMember($member->enrollment, $request->user());

        return back()->with('status', 'Anggota berhasil dikeluarkan dari kelompok tanpa menghapus histori.');
    }
}
