<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FieldSupervisor;
use App\Models\Lecturer;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use App\Services\KpCoreBridgeProvisioningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    public function index(Request $request): View
    {
        $users = User::query()
            ->with(['roles', 'student', 'lecturer', 'fieldSupervisor'])
            ->when($request->filled('q'), function ($query) use ($request) {
                $keyword = $request->string('q');
                $query->where(fn ($sub) => $sub
                    ->where('name', 'like', "%{$keyword}%")
                    ->orWhere('email', 'like', "%{$keyword}%"));
            })
            ->when($request->filled('role'), fn ($query) => $query->whereHas('roles', fn ($roleQuery) => $roleQuery->where('name', $request->role)))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->status))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'roles' => Role::orderBy('label')->get(),
            'filters' => $request->only(['q', 'role', 'status']),
        ]);
    }

    public function create(): View
    {
        return view('admin.users.create', [
            'user' => new User,
            'roles' => Role::orderBy('label')->get(),
            'selectedRoles' => [],
            'profileType' => 'mahasiswa',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateUser($request);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'status' => $validated['status'],
            'must_change_password' => true,
            'profile_completed' => false,
        ]);

        $user->roles()->sync($validated['roles']);
        $this->syncProfile($user, $validated);

        return redirect()->route('admin.users.show', $user)->with('status', 'User berhasil dibuat.');
    }

    public function show(User $user): View
    {
        return view('admin.users.show', [
            'user' => $user->load(['roles', 'student', 'lecturer', 'fieldSupervisor']),
        ]);
    }

    public function edit(User $user): View
    {
        return view('admin.users.edit', [
            'user' => $user->load(['roles', 'student', 'lecturer', 'fieldSupervisor']),
            'roles' => Role::orderBy('label')->get(),
            'selectedRoles' => $user->roles->pluck('id')->all(),
            'profileType' => $user->primaryProfileType(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $this->validateUser($request, $user);

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'status' => $validated['status'],
        ]);

        $user->roles()->sync($validated['roles']);
        $this->syncProfile($user, $validated);
        $this->refreshProfileCompletion($user);

        return redirect()->route('admin.users.show', $user)->with('status', 'User berhasil diperbarui.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($request->user()->is($user)) {
            return back()->withErrors(['user' => 'Admin tidak boleh menghapus akun sendiri.']);
        }

        $user->delete();

        return redirect()->route('admin.users.index')->with('status', 'User berhasil dihapus.');
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        if ($request->user()->is($user)) {
            return back()->withErrors(['user' => 'Gunakan fitur ubah password pribadi untuk akun sendiri.']);
        }

        $user->update([
            'password' => Hash::make('password'),
            'must_change_password' => true,
        ]);

        return back()->with('status', 'Password user berhasil direset ke password development.');
    }

    public function toggleStatus(Request $request, User $user): RedirectResponse
    {
        if ($request->user()->is($user)) {
            return back()->withErrors(['user' => 'Admin tidak boleh mengaktifkan atau menonaktifkan akun sendiri.']);
        }

        $user->update([
            'status' => $user->status === 'active' ? 'inactive' : 'active',
        ]);

        return back()->with('status', 'Status akun berhasil diperbarui.');
    }

    public function syncFromCore(User $user, KpCoreBridgeProvisioningService $service): RedirectResponse
    {
        $report = $service->execute($user->email);

        if ($report['blockers'] !== []) {
            return back()->withErrors(['core_sync' => implode(' ', $report['blockers'])]);
        }

        return back()->with('status', 'User berhasil disinkronkan dari Core. Aksi: '.$report['action'].'.');
    }

    public function bulkSyncFromCore(Request $request, KpCoreBridgeProvisioningService $service): RedirectResponse
    {
        $validated = $request->validate([
            'limit' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ]);

        $summary = $service->syncAll(true, (int) ($validated['limit'] ?? 0));
        $message = 'Sync Core selesai. Total: '.$summary['total']
            .', created: '.$summary['created']
            .', synced: '.$summary['synced']
            .', skipped: '.$summary['skipped']
            .', blocked: '.$summary['blocked'].'.';

        if ($summary['blocked'] > 0) {
            return back()->withErrors(['core_sync' => $message.' Cek Core app access/role untuk user yang blocked.']);
        }

        return back()->with('status', $message);
    }

    private function validateUser(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:8', 'confirmed'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['exists:roles,id'],
            'profile_type' => ['required', Rule::in(['mahasiswa', 'dosen', 'pembimbing_lapangan', 'admin'])],
            'nim' => ['nullable', 'string', 'max:50', Rule::unique('students', 'nim')->ignore($user?->student)],
            'study_program' => ['nullable', 'string', 'max:255'],
            'semester' => ['nullable', 'integer', 'min:1', 'max:14'],
            'class_name' => ['nullable', 'string', 'max:50'],
            'nidn_nip' => ['nullable', 'string', 'max:100', Rule::unique('lecturers', 'nidn_nip')->ignore($user?->lecturer)],
            'employee_number' => ['nullable', 'string', 'max:100', Rule::unique('lecturers', 'employee_number')->ignore($user?->lecturer)],
            'department' => ['nullable', 'string', 'max:255'],
            'expertise' => ['nullable', 'string', 'max:255'],
            'institution_name' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:1000'],
        ], [
            'roles.required' => 'Pilih minimal satu role.',
            'password.confirmed' => 'Konfirmasi password tidak sesuai.',
        ]);
    }

    private function syncProfile(User $user, array $data): void
    {
        match ($data['profile_type']) {
            'mahasiswa' => Student::updateOrCreate(['user_id' => $user->id], [
                'nim' => $data['nim'] ?? null,
                'study_program' => $data['study_program'] ?? null,
                'semester' => $data['semester'] ?? null,
                'class_name' => $data['class_name'] ?? null,
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
            ]),
            'dosen' => Lecturer::updateOrCreate(['user_id' => $user->id], [
                'nidn_nip' => $data['nidn_nip'] ?? null,
                'employee_number' => $data['employee_number'] ?? null,
                'study_program' => $data['study_program'] ?? null,
                'department' => $data['department'] ?? null,
                'expertise' => $data['expertise'] ?? null,
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
            ]),
            'pembimbing_lapangan' => FieldSupervisor::updateOrCreate(['user_id' => $user->id], [
                'institution_name' => $data['institution_name'] ?? null,
                'position' => $data['position'] ?? null,
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
            ]),
            default => null,
        };
    }

    private function refreshProfileCompletion(User $user): void
    {
        $user->refresh()->load(['roles', 'student', 'lecturer', 'fieldSupervisor']);
        $complete = $user->isProfileComplete();
        $profile = $user->profileModel();

        if ($profile && $complete && blank($profile->profile_completed_at)) {
            $profile->update(['profile_completed_at' => now()]);
        }

        $user->update(['profile_completed' => $complete]);
    }
}
