@extends('layouts.app')

@section('title', 'Edit Profil Akademik - '.config('app.name'))
@section('page_title', 'Edit Profil Akademik')

@section('content')
<div class="grid gap-6 lg:grid-cols-[300px_1fr] items-start">
    @php($coreManaged = (bool) $coreOfficialProfile)

    <!-- LEFT COLUMN: Sticky Profile Sidebar -->
    <div class="lg:sticky lg:top-24 space-y-6">
        <section class="rounded-3xl border border-sky-100 bg-white p-6 shadow-xl shadow-sky-900/5 ring-1 ring-sky-100/50">
            <!-- Avatar Section with decorative gradient ring -->
            <div class="flex flex-col items-center text-center">
                <div class="relative group">
                    <div class="absolute inset-0 rounded-[2rem] bg-linear-to-tr from-cyan-600 to-teal-400 opacity-75 blur-md transition duration-300 group-hover:opacity-100"></div>
                    <x-ui.avatar :user="$user" size="xl" class="relative bg-white p-1 ring-4 ring-white shadow-xl" />
                </div>

                <h3 class="mt-5 text-xl font-black tracking-tight text-slate-900 leading-tight">
                    {{ $coreOfficialProfile ? data_get($coreOfficialProfile, 'user.name', $user->name) : $user->name }}
                </h3>
                <p class="mt-1.5 break-all text-xs font-semibold text-slate-500 tracking-normal transition-colors">
                    {{ strtolower($coreOfficialProfile ? data_get($coreOfficialProfile, 'user.email', $user->email) : $user->email) }}
                </p>
                
                <div class="mt-4 flex flex-wrap justify-center gap-1.5">
                    <span class="rounded-full bg-cyan-50 px-3 py-1 text-[11px] font-bold text-cyan-700 ring-1 ring-cyan-100/80">
                        {{ \App\Support\RoleDashboard::labelFor(session('active_role')) }}
                    </span>
                    @if($coreOfficialProfile)
                        <span class="rounded-full bg-emerald-50 px-3 py-1 text-[11px] font-bold text-emerald-700 ring-1 ring-emerald-100/80">
                            Core Sync
                        </span>
                    @endif
                </div>
            </div>

            <div class="my-5 border-t border-slate-100"></div>

            <!-- Quick Metrics/Stats -->
            <div class="space-y-2.5 text-xs">
                <div class="flex items-center justify-between rounded-xl bg-slate-50/50 p-3 ring-1 ring-slate-100">
                    <span class="font-medium text-slate-500">Status Akun</span>
                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 font-bold text-emerald-700 ring-1 ring-emerald-100/50">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                        {{ $user->status === 'active' ? 'Aktif' : 'Nonaktif' }}
                    </span>
                </div>
                <div class="flex items-center justify-between rounded-xl bg-slate-50/50 p-3 ring-1 ring-slate-100">
                    <span class="font-medium text-slate-500">Kelengkapan Data</span>
                    <span class="rounded-full {{ $user->profile_completed || $coreOfficialProfile ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-100/50' : 'bg-amber-50 text-amber-700 ring-1 ring-amber-100/50' }} px-2 py-0.5 font-bold">
                        {{ $user->profile_completed || $coreOfficialProfile ? 'Lengkap' : 'Belum Lengkap' }}
                    </span>
                </div>
            </div>

            <div class="mt-6">
                <a href="{{ route('profile.show') }}" class="flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-center text-xs font-bold text-slate-700 hover:bg-slate-50 transition-all hover:shadow-slate-100/30 hover:-translate-y-[1px]">
                    <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Kembali ke Profil
                </a>
            </div>
        </section>
    </div>

    <!-- RIGHT COLUMN: Editing Form & Settings -->
    <div class="space-y-6">
        
        <!-- Core Profile Info Banner -->
        @if($coreProfileUrl)
            <div class="rounded-2xl border border-blue-100 bg-linear-to-r from-blue-50 to-indigo-50/50 p-4 shadow-sm shadow-blue-950/5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between ring-1 ring-blue-100/50">
                <div class="flex items-start gap-3">
                    <svg class="w-5 h-5 shrink-0 text-blue-600 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <div class="space-y-0.5">
                        <h4 class="text-xs font-extrabold text-blue-950">Profil utama dikelola di Core Farmasi</h4>
                        <p class="text-[11px] leading-relaxed text-blue-900/80">Identitas utama, nomor HP, dan alamat disinkronkan dari Core. Hubungi admin atau klik tombol untuk mengupdate data di Core.</p>
                    </div>
                </div>
                <a href="{{ $coreProfileUrl }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center justify-center shrink-0 gap-1.5 rounded-xl bg-blue-600 px-3.5 py-2 text-xs font-bold text-white shadow-md shadow-blue-600/20 hover:bg-blue-700 transition-all hover:shadow-blue-700/20">
                    Ubah Profil di Core
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                    </svg>
                </a>
            </div>
        @endif

        <!-- Core Official Profile Details (Reference) -->
        @if($coreOfficialProfile)
            <section class="overflow-hidden rounded-3xl bg-white shadow-xl shadow-cyan-950/5 ring-1 ring-cyan-100">
                <div class="relative p-6 sm:p-8">
                    <div class="absolute inset-x-0 top-0 h-1.5 bg-linear-to-r from-cyan-700 via-teal-500 to-emerald-400"></div>
                    <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                        <div>
                            <h3 class="text-lg font-black text-slate-900">Data Resmi Core</h3>
                            <p class="mt-1 text-xs text-slate-500">Informasi identitas resmi dikunci dan hanya dapat diubah via Core.</p>
                        </div>
                        <span class="rounded-full bg-emerald-50 px-3 py-1 text-[11px] font-bold text-emerald-700 ring-1 ring-emerald-100/50">Source of Truth</span>
                    </div>

                    <div class="mt-6 space-y-6">
                        @foreach(data_get($coreOfficialProfile, 'sections', []) as $sectionTitle => $items)
                            <div class="space-y-3">
                                <h4 class="text-xs font-black uppercase tracking-wider text-cyan-800 flex items-center gap-2">
                                    <span class="h-1.5 w-1.5 rounded-full bg-cyan-600"></span>
                                    {{ $sectionTitle }}
                                </h4>
                                
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-y-3 gap-x-5 rounded-2xl bg-slate-50/50 p-4 ring-1 ring-slate-100">
                                    @foreach($items as $label => $value)
                                        <div class="space-y-0.5">
                                            <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ $label }}</span>
                                            <span class="block text-xs font-bold text-slate-800 break-words">{{ filled($value) ? $value : '-' }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        <!-- Local Avatar Upload Card (if not managed by Core) -->
        @unless($coreOfficialProfile)
            <section class="rounded-3xl border border-slate-200/80 bg-white p-6 shadow-sm shadow-slate-100 ring-1 ring-slate-100">
                <div class="flex flex-col gap-5 sm:flex-row sm:items-center">
                    <x-ui.avatar :user="$user" size="xl" class="shadow-xl shadow-cyan-900/10" />
                    <div class="min-w-0 flex-1">
                        <h3 class="text-lg font-bold text-slate-900">Foto Profil</h3>
                        <p class="mt-1 text-xs text-slate-500 leading-relaxed">Gunakan foto JPG/PNG/WebP maksimal 2MB. Foto akan tampil di bilah atas, dashboard, dan halaman pilih peran.</p>
                        @if($user->avatar_original_filename)
                            <p class="mt-2 truncate text-xs font-semibold text-cyan-700">{{ $user->avatar_original_filename }}</p>
                        @endif
                    </div>
                </div>

                @error('avatar')
                    <div class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-xs font-semibold text-rose-700">{{ $message }}</div>
                @enderror

                <div class="mt-5 grid gap-3 sm:grid-cols-[1fr_auto]">
                    <form method="POST" action="{{ route('profile.avatar.update') }}" enctype="multipart/form-data" class="flex flex-col gap-3 sm:flex-row">
                        @csrf
                        <input type="file" name="avatar" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs file:mr-4 file:rounded-lg file:border-0 file:bg-cyan-50 file:px-3 file:py-1 file:text-xs file:font-bold file:text-cyan-700 focus:border-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-500/20">
                        <button class="flex-none rounded-xl bg-cyan-700 px-4 py-2 text-xs font-bold text-white shadow-md shadow-cyan-700/15 hover:bg-cyan-800 transition">Ubah Foto</button>
                    </form>
                    @if($user->hasLocalAvatar())
                        <form method="POST" action="{{ route('profile.avatar.delete') }}">
                            @csrf
                            @method('DELETE')
                            <button class="w-full rounded-xl border border-rose-200 bg-white px-4 py-2 text-xs font-bold text-rose-700 hover:bg-rose-50 transition">Hapus Foto</button>
                        </form>
                    @endif
                </div>
            </section>
        @endunless

        <!-- Edit Form -->
        <form method="POST" action="{{ route('profile.update') }}">
            @csrf
            @method('PUT')

            <section class="rounded-3xl border border-slate-200/80 bg-white p-6 sm:p-8 shadow-sm shadow-slate-100 ring-1 ring-slate-100">
                <div class="mb-6 pb-6 border-b border-slate-100">
                    <h3 class="text-lg font-black text-slate-900">Pembaruan Data Operasional KP</h3>
                    <p class="mt-1 text-xs text-slate-500 leading-relaxed">
                        @if($coreManaged)
                            Field identitas resmi dikunci karena sudah dikelola Core. Isi hanya data tambahan yang dibutuhkan workflow KP.
                        @else
                            Lengkapi informasi akademis wajib untuk mengaktifkan fitur utama sistem.
                        @endif
                    </p>
                </div>

                @if($errors->any())
                    <div class="mb-6 rounded-2xl border border-rose-200 bg-rose-50/50 px-5 py-4 text-xs font-semibold text-rose-700 ring-1 ring-rose-100">
                        <div class="flex gap-3">
                            <svg class="w-4 h-4 shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                            </svg>
                            <span>{{ $errors->first() }}</span>
                        </div>
                    </div>
                @endif

                <div class="grid gap-5 md:grid-cols-2">
                    @if($profileType === 'mahasiswa')
                        <div class="md:col-span-2">
                            <label class="block text-xs font-bold text-slate-700 mb-1.5">Nomor Induk Mahasiswa (NIM)</label>
                            <input value="{{ $coreManaged ? data_get($coreOfficialProfile, 'linked_profile.student_number') : $profile?->nim }}" disabled class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-xs text-slate-400 cursor-not-allowed ring-1 ring-slate-100">
                            <p class="mt-1 text-[10px] text-slate-400">Data NIM tidak dapat diubah di KP. Hubungi admin Core jika ada kesalahan data resmi.</p>
                        </div>
                        @unless($coreManaged)
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1.5">Nomor Telepon Aktif</label>
                                <input name="phone" value="{{ old('phone', $profile?->phone) }}" placeholder="+62..." class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-xs focus:border-cyan-600 focus:ring-4 focus:ring-cyan-100/50 outline-none transition">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1.5">Program Studi</label>
                                <input name="study_program" value="{{ old('study_program', $profile?->study_program) }}" placeholder="Farmasi" class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-xs focus:border-cyan-600 focus:ring-4 focus:ring-cyan-100/50 outline-none transition">
                            </div>
                        @endunless
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1.5">Tingkat Semester</label>
                            <input name="semester" type="number" min="1" max="14" value="{{ old('semester', $profile?->semester) }}" placeholder="7" class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-xs focus:border-cyan-600 focus:ring-4 focus:ring-cyan-100/50 outline-none transition">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1.5">Kelas</label>
                            <input name="class_name" value="{{ old('class_name', $profile?->class_name) }}" placeholder="A" class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-xs focus:border-cyan-600 focus:ring-4 focus:ring-cyan-100/50 outline-none transition">
                        </div>
                        @unless($coreManaged)
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1.5">Jenis Kelamin</label>
                                <select name="gender" class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-xs focus:border-cyan-600 focus:ring-4 focus:ring-cyan-100/50 outline-none transition bg-white">
                                    <option value="">Pilih jenis kelamin</option>
                                    <option value="Laki-laki" @selected(old('gender', $profile?->gender) === 'Laki-laki')>Laki-laki</option>
                                    <option value="Perempuan" @selected(old('gender', $profile?->gender) === 'Perempuan')>Perempuan</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1.5">Tempat Lahir</label>
                                <input name="birth_place" value="{{ old('birth_place', $profile?->birth_place) }}" placeholder="Kota/Kabupaten" class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-xs focus:border-cyan-600 focus:ring-4 focus:ring-cyan-100/50 outline-none transition">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1.5">Tanggal Lahir</label>
                                <input name="birth_date" type="date" value="{{ old('birth_date', $profile?->birth_date?->format('Y-m-d')) }}" class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-xs focus:border-cyan-600 focus:ring-4 focus:ring-cyan-100/50 outline-none transition">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-xs font-bold text-slate-700 mb-1.5">Alamat Lengkap</label>
                                <textarea name="address" rows="3" placeholder="Jl. ... No. ... Kelurahan ... Kecamatan ..." class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-xs focus:border-cyan-600 focus:ring-4 focus:ring-cyan-100/50 outline-none transition resize-none">{{ old('address', $profile?->address) }}</textarea>
                            </div>
                        @endunless
                    @elseif($profileType === 'dosen')
                        <div class="md:col-span-2">
                            <label class="block text-xs font-bold text-slate-700 mb-1.5">Nomor Induk Dosen Nasional (NIDN/NIP)</label>
                            <input value="{{ $coreManaged ? (data_get($coreOfficialProfile, 'linked_profile.nidn') ?: data_get($coreOfficialProfile, 'linked_profile.lecturer_number')) : $profile?->nidn_nip }}" disabled class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-xs text-slate-400 cursor-not-allowed ring-1 ring-slate-100">
                            <p class="mt-1 text-[10px] text-slate-400">Data NIDN/NIP tidak dapat diubah di KP. Hubungi admin Core jika ada kesalahan data resmi.</p>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs font-bold text-slate-700 mb-1.5">Nomor Pegawai</label>
                            <input value="{{ $coreManaged ? data_get($coreOfficialProfile, 'linked_profile.nip') : $profile?->employee_number }}" disabled class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-xs text-slate-400 cursor-not-allowed ring-1 ring-slate-100">
                        </div>
                        @unless($coreManaged)
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1.5">Nomor Telepon Aktif</label>
                                <input name="phone" value="{{ old('phone', $profile?->phone) }}" placeholder="+62..." class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-xs focus:border-cyan-600 focus:ring-4 focus:ring-cyan-100/50 outline-none transition">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1.5">Program Studi/Prodi</label>
                                <input name="study_program" value="{{ old('study_program', $profile?->study_program) }}" placeholder="Farmasi" class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-xs focus:border-cyan-600 focus:ring-4 focus:ring-cyan-100/50 outline-none transition">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1.5">Departemen/Unit</label>
                                <input name="department" value="{{ old('department', $profile?->department) }}" placeholder="Teknologi Farmasi" class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-xs focus:border-cyan-600 focus:ring-4 focus:ring-cyan-100/50 outline-none transition">
                            </div>
                        @endunless
                        <div class="md:col-span-2">
                            <label class="block text-xs font-bold text-slate-700 mb-1.5">Bidang Keahlian/Expertise</label>
                            <input name="expertise" value="{{ old('expertise', $profile?->expertise) }}" placeholder="Misal: Teknologi Sediaan, Farmakologi, dll" class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-xs focus:border-cyan-600 focus:ring-4 focus:ring-cyan-100/50 outline-none transition">
                        </div>
                        @unless($coreManaged)
                            <div class="md:col-span-2">
                                <label class="block text-xs font-bold text-slate-700 mb-1.5">Alamat Lengkap</label>
                                <textarea name="address" rows="3" placeholder="Jl. ... No. ... Kelurahan ... Kecamatan ..." class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-xs focus:border-cyan-600 focus:ring-4 focus:ring-cyan-100/50 outline-none transition resize-none">{{ old('address', $profile?->address) }}</textarea>
                            </div>
                        @endunless
                    @elseif($profileType === 'pembimbing_lapangan')
                        <div class="md:col-span-2 rounded-2xl border border-cyan-100 bg-cyan-50/60 p-4 text-xs leading-relaxed text-cyan-900 ring-1 ring-cyan-100">
                            Jika akun ini juga dosen, data dosen resmi tetap dibaca dari Core. Bagian ini hanya mencatat peran operasional sebagai mitra atau preseptor di tempat PKPA tertentu.
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1.5">Nomor Telepon Aktif</label>
                            <input name="phone" value="{{ old('phone', $profile?->phone) }}" placeholder="+62..." class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-xs focus:border-cyan-600 focus:ring-4 focus:ring-cyan-100/50 outline-none transition">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1.5">Nama Institusi/Tempat KP</label>
                            <input name="institution_name" value="{{ old('institution_name', $profile?->institution_name) }}" placeholder="Nama tempat kerja praktik" class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-xs focus:border-cyan-600 focus:ring-4 focus:ring-cyan-100/50 outline-none transition">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs font-bold text-slate-700 mb-1.5">Jabatan/Posisi</label>
                            <input name="position" value="{{ old('position', $profile?->position) }}" placeholder="Misal: Apoteker, Manajer, Supervisor, dll" class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-xs focus:border-cyan-600 focus:ring-4 focus:ring-cyan-100/50 outline-none transition">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs font-bold text-slate-700 mb-1.5">Alamat Lengkap</label>
                            <textarea name="address" rows="3" placeholder="Jl. ... No. ... Kelurahan ... Kecamatan ..." class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-xs focus:border-cyan-600 focus:ring-4 focus:ring-cyan-100/50 outline-none transition resize-none">{{ old('address', $profile?->address) }}</textarea>
                        </div>
                        <div class="md:col-span-2">
                            @php($selectedPlaceIds = collect(old('place_ids', $profile?->places?->pluck('id')->all() ?? []))->map(fn ($id) => (int) $id)->all())
                            <label class="block text-xs font-bold text-slate-700 mb-1.5">Tempat KP / Mitra Terkait</label>
                            <div class="max-h-72 space-y-2 overflow-y-auto rounded-2xl border border-slate-200 bg-slate-50/60 p-3">
                                @forelse($availablePlaces as $place)
                                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 bg-white p-3 text-xs shadow-sm transition hover:border-cyan-200 hover:bg-cyan-50/30">
                                        <input type="checkbox" name="place_ids[]" value="{{ $place->id }}" @checked(in_array($place->id, $selectedPlaceIds, true)) class="mt-1 rounded border-slate-300 text-cyan-700 focus:ring-cyan-500">
                                        <span class="min-w-0">
                                            <span class="block font-black text-slate-900">{{ $place->name }}</span>
                                            <span class="mt-0.5 block text-[11px] text-slate-500">{{ $place->typeLabel() }}{{ $place->city ? ' - '.$place->city : '' }}</span>
                                        </span>
                                    </label>
                                @empty
                                    <div class="rounded-xl border border-amber-100 bg-amber-50 px-4 py-3 text-xs text-amber-800">Belum ada tempat PKPA aktif untuk ditautkan.</div>
                                @endforelse
                            </div>
                            <p class="mt-2 text-[11px] leading-relaxed text-slate-500">Centang satu atau beberapa tempat PKPA yang terkait dengan peran mitra atau preseptor akun ini.</p>
                        </div>
                    @else
                        @if($coreManaged)
                            <div class="md:col-span-2 rounded-2xl border border-blue-100 bg-blue-50/40 p-5 text-xs leading-relaxed text-blue-900 ring-1 ring-blue-100/50">
                                Identitas akun admin dikelola dari Core Farmasi. Tidak ada field operasional PKPA yang perlu diisi untuk role aktif ini.
                            </div>
                        @else
                            <div class="md:col-span-2">
                                <label class="block text-xs font-bold text-slate-700 mb-1.5">Nama Lengkap</label>
                                <input name="name" value="{{ old('name', $user->name) }}" class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-xs focus:border-cyan-600 focus:ring-4 focus:ring-cyan-100/50 outline-none transition">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-xs font-bold text-slate-700 mb-1.5">Alamat Email</label>
                                <input name="email" type="email" value="{{ old('email', strtolower($user->email)) }}" class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-xs focus:border-cyan-600 focus:ring-4 focus:ring-cyan-100/50 outline-none transition">
                            </div>
                        @endif
                    @endif
                </div>

                <div class="mt-8 flex justify-end gap-3 pt-6 border-t border-slate-100">
                    <a href="{{ route('profile.show') }}" class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 transition shadow-sm">
                        <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                        Batal
                    </a>
                    <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl bg-cyan-700 px-4 py-2 text-xs font-bold text-white hover:bg-cyan-800 transition shadow-md shadow-cyan-700/15">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                        </svg>
                        Simpan Perubahan
                    </button>
                </div>
            </section>
        </form>
    </div>
</div>
@endsection
