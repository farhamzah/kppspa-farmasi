@extends('layouts.app')

@section('title', 'Profil Akademik - '.config('app.name'))
@section('page_title', 'Profil Akademik')

@section('content')
@if($errors->any())
    <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
@endif
@if(session('status'))
    <div class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
@endif

<div class="grid gap-6 lg:grid-cols-[300px_1fr] items-start">
    
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

            <div class="mt-6 space-y-2.5">
                <form method="POST" action="{{ route('profile.sync-core') }}" onsubmit="return confirm('Segarkan data profil dan peran dari Core?')">
                    @csrf
                    <button class="flex w-full items-center justify-center gap-2 rounded-xl border border-cyan-200 bg-cyan-50 px-4 py-2.5 text-center text-xs font-bold text-cyan-800 transition-all hover:-translate-y-[1px] hover:bg-cyan-100">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        Refresh dari Core
                    </button>
                </form>
                <a href="{{ route('profile.edit') }}" class="flex w-full items-center justify-center gap-2 rounded-xl bg-linear-to-r from-cyan-700 to-cyan-800 px-4 py-2.5 text-center text-xs font-bold text-white shadow-lg shadow-cyan-700/20 hover:from-cyan-800 hover:to-cyan-900 transition-all hover:shadow-cyan-800/20 hover:-translate-y-[1px]">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    Edit Data KP
                </a>
                @if($coreProfileUrl)
                    <a href="{{ $coreProfileUrl }}" target="_blank" rel="noopener noreferrer" class="flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-center text-xs font-bold text-slate-700 hover:bg-slate-50 transition-all hover:-translate-y-[1px]">
                        <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                        </svg>
                        Buka Profil Core
                    </a>
                @endif
            </div>
        </section>
    </div>

    <!-- RIGHT COLUMN: Detailed Profiles & Roles -->
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

        <!-- Core Official Profile Details -->
        @if($coreOfficialProfile)
            <section class="overflow-hidden rounded-3xl bg-white shadow-xl shadow-cyan-950/5 ring-1 ring-cyan-100">
                <div class="relative p-6 sm:p-8">
                    <div class="absolute inset-x-0 top-0 h-1.5 bg-linear-to-r from-cyan-700 via-teal-500 to-emerald-400"></div>
                    <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                        <div>
                            <h3 class="text-lg font-black text-slate-900">Profil Resmi Core</h3>
                            <p class="mt-1 text-xs text-slate-500">Data terintegrasi resmi dari database Core Farmasi UBP</p>
                        </div>
                        <span class="rounded-full bg-emerald-50 px-3 py-1 text-[11px] font-bold text-emerald-700 ring-1 ring-emerald-100/50">Read-only</span>
                    </div>

                    <div class="mt-6 space-y-8">
                        @foreach(data_get($coreOfficialProfile, 'sections', []) as $sectionTitle => $items)
                            <div class="space-y-4">
                                <h4 class="text-xs font-black uppercase tracking-wider text-cyan-800 flex items-center gap-2">
                                    <span class="h-1.5 w-1.5 rounded-full bg-cyan-600"></span>
                                    {{ $sectionTitle }}
                                </h4>
                                
                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-y-4 gap-x-6 rounded-2xl bg-slate-50/50 p-5 ring-1 ring-slate-100">
                                    @foreach($items as $label => $value)
                                        <div class="space-y-1">
                                            <span class="block text-[10px] font-black uppercase tracking-wider text-slate-400">{{ $label }}</span>
                                            <span class="block text-sm font-bold text-slate-800 break-words">{{ filled($value) ? $value : '-' }}</span>
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

        <!-- Peran Akademik -->
        <section class="rounded-3xl border border-slate-200/80 bg-white p-6 shadow-sm shadow-slate-100 ring-1 ring-slate-100">
            <div class="mb-5">
                <h3 class="text-lg font-bold text-slate-900">Peran Akademik</h3>
                <p class="mt-1 text-xs text-slate-500">Peran yang terikat pada akun Anda dalam sistem.</p>
            </div>
            
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($user->roles as $role)
                    @php
                        $isActive = session('active_role') === $role->name;
                    @endphp
                    <div class="relative group rounded-2xl border p-4 transition-all duration-300 {{ $isActive ? 'border-cyan-500 bg-cyan-50/20 shadow-md ring-1 ring-cyan-500/25 hover:shadow-cyan-500/5' : 'border-slate-100 bg-white hover:border-slate-200 hover:shadow-sm hover:scale-[1.01]' }}">
                        @if($isActive)
                            <div class="absolute -top-1.5 -right-1.5 flex h-4 w-4 items-center justify-center rounded-full bg-cyan-600 shadow-md shadow-cyan-600/30">
                                <span class="h-1.5 w-1.5 rounded-full bg-white animate-pulse"></span>
                            </div>
                        @endif
                        <div class="space-y-1">
                            <h4 class="font-extrabold text-xs {{ $isActive ? 'text-cyan-900' : 'text-slate-900' }}">{{ $role->label }}</h4>
                            <p class="text-[10px] leading-relaxed {{ $isActive ? 'text-cyan-700/85' : 'text-slate-500' }}">{{ $role->description }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        <!-- Data Operasional KP -->
        <section class="rounded-3xl border border-slate-200/80 bg-white p-6 shadow-sm shadow-slate-100 ring-1 ring-slate-100">
            <div class="mb-5">
                <h3 class="text-lg font-bold text-slate-900">Data Operasional KP</h3>
                <p class="mt-1 text-xs text-slate-500">Informasi {{ ucwords(str_replace('_', ' ', $profileType)) }} yang tersimpan di KP untuk kebutuhan workflow kerja praktek.</p>
            </div>

            @if($operationalAttributes)
                <div class="grid gap-4 grid-cols-1 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($operationalAttributes as $label => $value)
                        <div class="rounded-2xl border border-slate-100 bg-slate-50/40 p-4 transition duration-300 hover:bg-slate-50/80">
                            <p class="text-[10px] font-black uppercase tracking-wider text-slate-400 mb-1.5">{{ $label }}</p>
                            <p class="text-xs font-extrabold text-slate-800 break-words">{{ $value ?: '-' }}</p>
                        </div>
                    @endforeach
                </div>
            @elseif($coreOfficialProfile)
                <div class="rounded-2xl border border-emerald-100 bg-emerald-50/40 p-5 text-sm text-emerald-800 ring-1 ring-emerald-100/50 flex items-start gap-3">
                    <svg class="w-5 h-5 shrink-0 text-emerald-600 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <p class="leading-relaxed text-xs">
                        Belum ada data operasional tambahan yang perlu diisi di KP untuk peran aktif ini. Data identitas yang sama sudah diambil dari Core.
                    </p>
                </div>
            @else
                <div class="rounded-2xl border border-amber-100 bg-amber-50/40 p-5 text-sm text-amber-800 ring-1 ring-amber-100/50 flex items-start gap-3">
                    <svg class="w-5 h-5 shrink-0 text-amber-600 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    <p class="leading-relaxed text-xs">
                        Profil khusus belum terdaftar. Silakan lengkapi profil bila diperlukan.
                    </p>
                </div>
            @endif
        </section>

    </div>
</div>
@endsection
