@csrf
@if($user->exists)
    @method('PUT')
@endif

<div class="grid gap-5 lg:grid-cols-[1.1fr_0.9fr]">
    <section class="space-y-4 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <h2 class="text-lg font-bold text-slate-950">Data Akun</h2>
        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label class="text-sm font-semibold text-slate-700">Nama</label>
                <input name="name" value="{{ old('name', $user->name) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="text-sm font-semibold text-slate-700">Email</label>
                <input name="email" type="email" value="{{ old('email', $user->email) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                @error('email')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="text-sm font-semibold text-slate-700">Password Awal</label>
                <input name="password" type="password" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" {{ $user->exists ? '' : 'required' }}>
                @error('password')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="text-sm font-semibold text-slate-700">Konfirmasi Password</label>
                <input name="password_confirmation" type="password" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" {{ $user->exists ? '' : 'required' }}>
            </div>
            <div>
                <label class="text-sm font-semibold text-slate-700">Status</label>
                <select name="status" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="active" @selected(old('status', $user->status ?: 'active') === 'active')>Aktif</option>
                    <option value="inactive" @selected(old('status', $user->status) === 'inactive')>Nonaktif</option>
                </select>
            </div>
            <div>
                <label class="text-sm font-semibold text-slate-700">Tipe Profil</label>
                <select name="profile_type" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    @foreach(['mahasiswa' => 'Mahasiswa', 'dosen' => 'Dosen', 'pembimbing_lapangan' => 'Preseptor', 'admin' => 'Admin/Koordinator tanpa profil khusus'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('profile_type', $profileType) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div>
            <label class="text-sm font-semibold text-slate-700">Peran</label>
            <div class="mt-2 grid gap-2 md:grid-cols-2">
                @foreach($roles as $role)
                    <label class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm">
                        <input type="checkbox" name="roles[]" value="{{ $role->id }}" @checked(in_array($role->id, old('roles', $selectedRoles), false)) class="rounded border-slate-300 text-teal-600">
                        {{ $role->label }}
                    </label>
                @endforeach
            </div>
            @error('roles')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
    </section>

    <section class="space-y-4 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <h2 class="text-lg font-bold text-slate-950">Data Profil Awal</h2>
        @php
            $student = $user->student;
            $lecturer = $user->lecturer;
            $field = $user->fieldSupervisor;
        @endphp
        <div class="grid gap-4">
            <div>
                <label class="text-sm font-semibold text-slate-700">NIM</label>
                <input name="nim" value="{{ old('nim', $student?->nim) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                @error('nim')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="text-sm font-semibold text-slate-700">NIDN/NIP</label>
                <input name="nidn_nip" value="{{ old('nidn_nip', $lecturer?->nidn_nip) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                @error('nidn_nip')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="text-sm font-semibold text-slate-700">Nomor Pegawai</label>
                <input name="employee_number" value="{{ old('employee_number', $lecturer?->employee_number) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="text-sm font-semibold text-slate-700">Program Studi</label>
                <input name="study_program" value="{{ old('study_program', $student?->study_program ?? $lecturer?->study_program) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="text-sm font-semibold text-slate-700">Semester</label>
                    <input name="semester" type="number" min="1" max="14" value="{{ old('semester', $student?->semester) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="text-sm font-semibold text-slate-700">Kelas</label>
                    <input name="class_name" value="{{ old('class_name', $student?->class_name) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
            </div>
            <div>
                <label class="text-sm font-semibold text-slate-700">Departemen</label>
                <input name="department" value="{{ old('department', $lecturer?->department) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="text-sm font-semibold text-slate-700">Bidang Keahlian</label>
                <input name="expertise" value="{{ old('expertise', $lecturer?->expertise) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="text-sm font-semibold text-slate-700">Institusi Lapangan</label>
                <input name="institution_name" value="{{ old('institution_name', $field?->institution_name) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="text-sm font-semibold text-slate-700">Jabatan</label>
                <input name="position" value="{{ old('position', $field?->position) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="text-sm font-semibold text-slate-700">No HP</label>
                <input name="phone" value="{{ old('phone', $student?->phone ?? $lecturer?->phone ?? $field?->phone) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="text-sm font-semibold text-slate-700">Alamat</label>
                <textarea name="address" rows="3" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('address', $student?->address ?? $lecturer?->address ?? $field?->address) }}</textarea>
            </div>
        </div>
    </section>
</div>

<div class="mt-5 flex items-center justify-end gap-2">
    <a href="{{ route('admin.users.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Batal</a>
    <button class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white">Simpan User</button>
</div>
