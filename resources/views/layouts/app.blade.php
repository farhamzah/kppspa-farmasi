<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name', 'SI-KP Farmasi UBP'))</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/logo-fakultas-farmasi-ubp.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-sky-50 font-sans text-slate-900">
@php
    $activeRole = session('active_role');
    $roleData = $activeRole ? \App\Support\RoleDashboard::dataFor($activeRole) : null;
    $currentUser = auth()->user();
    $roleLabel = $currentUser?->displayRoleLabel($activeRole) ?? \App\Support\RoleDashboard::labelFor($activeRole);
    $topbarRoleLabel = $currentUser?->displayRoleLabel($activeRole) ?? $roleLabel;
    $ownedRoles = $currentUser?->roles ?? collect();
    $currentUserDisplayName = $currentUser ? user_display_name($currentUser, $activeRole) : '';
@endphp
<div class="min-h-screen overflow-x-hidden bg-[radial-gradient(circle_at_top_left,rgba(14,165,233,0.16),transparent_32%),radial-gradient(circle_at_80%_12%,rgba(20,184,166,0.14),transparent_28%),linear-gradient(135deg,#f8fdff,#eef9fb_45%,#f4f9fc)] lg:flex">
    <!-- Sidebar Navigation -->
    <aside class="border-b border-sky-100 bg-white/92 text-slate-800 shadow-xl shadow-sky-900/8 backdrop-blur-xl lg:fixed lg:inset-y-0 lg:left-0 lg:flex lg:h-screen lg:w-72 lg:flex-col lg:overflow-hidden lg:border-b-0 lg:border-r lg:border-sky-100">
        <!-- Branding -->
        <div class="relative flex-none overflow-hidden border-b border-sky-100 px-5 py-6 lg:py-7">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,rgba(56,189,248,0.22),transparent_46%),linear-gradient(135deg,rgba(20,184,166,0.16),transparent)]"></div>
            <div class="relative flex items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <div class="flex h-12 w-12 items-center justify-center overflow-hidden rounded-2xl bg-white p-2 shadow-lg shadow-cyan-700/14 ring-1 ring-sky-100">
                    <img src="{{ asset('images/logo-fakultas-farmasi-ubp.png') }}" alt="Logo Fakultas Farmasi UBP" class="h-full w-full object-contain">
                </div>
                <div class="hidden lg:block">
                    <p class="text-sm font-black tracking-widest uppercase text-slate-950">SI-KP</p>
                    <p class="mt-0.5 text-[11px] font-bold text-cyan-700">Farmasi UBP</p>
                </div>
            </div>
            <a href="/" class="hidden rounded-xl p-2 text-slate-400 transition hover:bg-sky-50 hover:text-cyan-700 lg:block">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
            </a>
            </div>
        </div>
        
        <!-- Navigation Menu -->
        <nav class="flex gap-2 overflow-x-auto px-4 py-4 lg:block lg:min-h-0 lg:flex-1 lg:space-y-1.5 lg:overflow-x-hidden lg:overflow-y-auto lg:overscroll-contain lg:p-4 lg:pr-3 si-sidebar-scroll">
            <p class="mb-3 hidden px-3 text-[11px] font-black uppercase tracking-widest text-sky-700/70 lg:block">Menu Kerja Praktek</p>
            @foreach(($roleData['menu'] ?? ['Dashboard', 'Profil Saya']) as $item)
                @php
                    $isDashboard = $item === 'Dashboard';
                    $isProfile = $item === 'Profil Saya';
                    $routeMap = [
                        'Manajemen User' => 'admin.users.index',
                        'Import User' => 'admin.import-users.index',
                        'Riwayat Import' => 'admin.import-users.history',
                        'Periode KP' => 'management.kp-periods.index',
                        'Tempat KP' => 'management.kp-places.index',
                        'Kuota Tempat KP' => 'management.kp-place-quotas.index',
                        'Log Kuota' => 'management.kp-quota-logs.index',
                        'Persyaratan Dokumen' => 'management.document-requirements.index',
                        'Verifikasi Pendaftaran' => 'management.kp-registrations.index',
                        'Pendaftaran KP' => 'student.kp-registrations.index',
                        'Berkas KP' => 'student.kp-documents.index',
                        'Pemilihan Tempat KP' => 'student.place-selections.index',
                        'Monitoring Pemilihan' => 'management.place-selections.index',
                        'Daftar Tunggu' => 'management.waiting-lists.index',
                        'Log Pemilihan' => 'management.selection-logs.index',
                        'Penempatan KP' => $activeRole === 'mahasiswa' ? 'student.assignments.show' : 'management.kp-assignments.index',
                        'Log Penempatan' => 'management.kp-assignment-logs.index',
                        'Panduan Kompetensi' => 'management.competencies.index',
                        'Mahasiswa Bimbingan' => 'internal-supervisor.assignments.index',
                        'Capaian Kompetensi' => 'internal-supervisor.competencies.index',
                        'Mahasiswa KP' => 'field-supervisor.assignments.index',
                        'Checklist Kompetensi' => 'field-supervisor.competencies.index',
                        'Logbook KP' => 'student.logbooks.index',
                        'Validasi Logbook' => 'field-supervisor.logbooks.index',
                        'Logbook Mahasiswa' => 'internal-supervisor.logbooks.index',
                        'Monitoring Logbook' => 'management.logbooks.index',
                        'Log Aktivitas Logbook' => 'management.logbook-logs.index',
                        'Laporan Akhir' => 'student.final-reports.show',
                        'Review Laporan' => 'internal-supervisor.final-reports.index',
                        'Monitoring Laporan' => 'management.final-reports.index',
                        'Log Laporan' => 'management.final-report-logs.index',
                        'Sidang' => 'student.exams.index',
                        'Pre/Post Test' => 'student.orientation-tests.index',
                        'Pengajuan Sidang' => 'management.exam-requests.index',
                        'Jadwal Sidang' => $activeRole === 'pembimbing_dalam' ? 'internal-supervisor.exams.index' : ($activeRole === 'penguji' ? 'examiner.exams.index' : 'management.exams.index'),
                        'Log Sidang' => 'management.exam-logs.index',
                        'Komponen Penilaian' => 'management.assessment-components.index',
                        'Monitoring Nilai' => 'management.scores.index',
                        'Hasil Pre/Post Test' => 'management.orientation-tests.index',
                        'Log Nilai' => 'management.score-logs.index',
                        'Rekap KP' => 'management.recaps.index',
                        'Review Integrasi' => 'management.integration.tu-payload-preview',
                        'Penilaian Pembimbing' => 'internal-supervisor.assessments.index',
                        'Penilaian Lapangan' => 'field-supervisor.assessments.index',
                        'Penilaian Sidang' => 'examiner.assessments.index',
                        'Nilai' => 'student.scores.show',
                    ];
                    $activeMap = [
                        'Dashboard' => [$roleData['route'] ?? 'dashboard'],
                        'Profil Saya' => ['profile.show', 'profile.edit'],
                        'Pendaftaran KP' => ['student.kp-registrations.index', 'student.kp-registrations.create', 'student.kp-registrations.store'],
                        'Berkas KP' => ['student.kp-documents.*', 'student.kp-registrations.show', 'student.kp-registrations.documents.*', 'student.kp-registrations.submit', 'student.kp-registrations.cancel'],
                        'Pemilihan Tempat KP' => ['student.place-selections.*'],
                        'Penempatan KP' => ['student.assignments.*', 'management.kp-assignments.*'],
                        'Logbook KP' => ['student.logbooks.*'],
                        'Laporan Akhir' => ['student.final-reports.*'],
                        'Manajemen User' => ['admin.users.*'],
                        'Import User' => ['admin.import-users.index', 'admin.import-users.preview', 'admin.import-users.process', 'admin.import-users.template'],
                        'Riwayat Import' => ['admin.import-users.history', 'admin.import-users.history.*'],
                        'Periode KP' => ['management.kp-periods.*'],
                        'Tempat KP' => ['management.kp-places.*'],
                        'Kuota Tempat KP' => ['management.kp-place-quotas.*'],
                        'Log Kuota' => ['management.kp-quota-logs.*'],
                        'Persyaratan Dokumen' => ['management.document-requirements.*'],
                        'Verifikasi Pendaftaran' => ['management.kp-registrations.*'],
                        'Monitoring Pemilihan' => ['management.place-selections.*'],
                        'Daftar Tunggu' => ['management.waiting-lists.*'],
                        'Log Pemilihan' => ['management.selection-logs.*'],
                        'Log Penempatan' => ['management.kp-assignment-logs.*'],
                        'Panduan Kompetensi' => ['management.competencies.*'],
                        'Mahasiswa Bimbingan' => ['internal-supervisor.assignments.*'],
                        'Capaian Kompetensi' => ['internal-supervisor.competencies.*'],
                        'Mahasiswa KP' => ['field-supervisor.assignments.*'],
                        'Checklist Kompetensi' => ['field-supervisor.competencies.*'],
                        'Validasi Logbook' => ['field-supervisor.logbooks.*'],
                        'Logbook Mahasiswa' => ['internal-supervisor.logbooks.*'],
                        'Monitoring Logbook' => ['management.logbooks.*'],
                        'Log Aktivitas Logbook' => ['management.logbook-logs.*'],
                        'Review Laporan' => ['internal-supervisor.final-reports.*'],
                        'Monitoring Laporan' => ['management.final-reports.*'],
                        'Log Laporan' => ['management.final-report-logs.*'],
                        'Sidang' => ['student.exams.*'],
                        'Pre/Post Test' => ['student.orientation-tests.*'],
                        'Pengajuan Sidang' => ['management.exam-requests.*'],
                        'Jadwal Sidang' => ['management.exams.*', 'internal-supervisor.exams.*', 'examiner.exams.*'],
                        'Log Sidang' => ['management.exam-logs.*'],
                        'Komponen Penilaian' => ['management.assessment-components.*'],
                        'Monitoring Nilai' => ['management.scores.*', 'management.final-scores.*'],
                        'Hasil Pre/Post Test' => ['management.orientation-tests.*'],
                        'Log Nilai' => ['management.score-logs.*'],
                        'Rekap KP' => ['management.recaps.*', 'management.exports.*'],
                        'Review Integrasi' => ['management.integration.*'],
                        'Penilaian Pembimbing' => ['internal-supervisor.assessments.*'],
                        'Penilaian Lapangan' => ['field-supervisor.assessments.*'],
                        'Penilaian Sidang' => ['examiner.assessments.*'],
                        'Nilai' => ['student.scores.*'],
                    ];
                    $mappedRoute = $routeMap[$item] ?? null;
                    $href = $isDashboard ? route($roleData['route'] ?? 'dashboard') : ($isProfile ? route('profile.show') : ($mappedRoute && Route::has($mappedRoute) ? route($mappedRoute) : '#'));
                    $isActive = collect($activeMap[$item] ?? [])->contains(fn ($pattern) => request()->routeIs($pattern));
                @endphp
                <a href="{{ $href }}" class="group flex min-w-max items-center justify-between rounded-2xl px-3 py-3 text-sm font-bold transition-all {{ $isActive ? 'bg-cyan-700 text-white shadow-lg shadow-cyan-700/20 ring-1 ring-cyan-600' : 'text-slate-600 hover:bg-sky-50 hover:text-cyan-800' }}">
                    <span class="flex min-w-0 items-center gap-3">
                        <span class="h-2 w-2 rounded-full {{ $isActive ? 'bg-cyan-100' : 'bg-sky-300 group-hover:bg-cyan-500' }}"></span>
                        <span class="truncate">{{ $item }}</span>
                    </span>
                    @unless($isDashboard || $isProfile || $mappedRoute)
                        <span class="ml-3 rounded-md {{ $isActive ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-500' }} px-2 py-0.5 text-[10px] font-black uppercase tracking-widest">Segera</span>
                    @endunless
                </a>
            @endforeach
        </nav>

        <!-- User Info (Mobile) -->
        <div class="flex-none border-t border-sky-100 px-4 py-4 lg:hidden">
            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Pengguna</p>
            <div class="flex items-center gap-3">
                <x-ui.avatar :user="$currentUser" size="sm" />
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold text-slate-900">{{ $currentUserDisplayName }}</p>
                    <p class="mt-1 truncate text-xs text-cyan-700">{{ $roleLabel }}</p>
                </div>
            </div>
        </div>
    </aside>

    <!-- Main Content Area -->
    <div class="flex min-h-screen min-w-0 flex-1 flex-col overflow-x-hidden lg:pl-72">
        <!-- Header -->
        <header class="sticky top-0 z-20 flex-none overflow-hidden border-b border-sky-100/90 bg-white/88 shadow-sm shadow-sky-900/5 backdrop-blur-xl">
            <div class="mx-auto flex w-full max-w-screen-2xl min-w-0 flex-col gap-4 px-4 py-4 md:flex-row md:items-center md:justify-between md:gap-3 md:px-6 lg:px-8">
                <!-- Page Title -->
                <div class="min-w-0">
                    <p class="text-xs font-black uppercase tracking-widest text-cyan-700 mb-1">{{ config('app.name') }}</p>
                    <h1 class="truncate text-2xl font-black tracking-tight text-slate-950">@yield('page_title', 'Dashboard')</h1>
                </div>
                
                <!-- Header Actions -->
                <div class="flex w-full min-w-0 flex-wrap items-center justify-start gap-2 md:w-auto md:flex-nowrap md:justify-end md:gap-3">
                    <!-- User Pill -->
                    <div class="flex min-w-0 max-w-[220px] items-center gap-2 rounded-2xl bg-white px-3 py-2 text-xs shadow-sm ring-1 ring-sky-100 sm:max-w-[260px]">
                        <x-ui.avatar :user="$currentUser" size="sm" />
                        <span class="min-w-0 leading-tight">
                            <span class="block truncate font-black text-slate-800">{{ $currentUserDisplayName }}</span>
                            <span class="block truncate text-[11px] font-bold text-cyan-700">{{ $topbarRoleLabel }}</span>
                        </span>
                    </div>
                    
                    <!-- Role Switcher -->
                    @if($ownedRoles->count() > 1)
                        <a href="{{ route('role.select') }}" class="flex flex-none items-center gap-2 rounded-2xl border border-cyan-200 bg-white px-3 py-2 text-xs font-bold text-cyan-700 shadow-sm transition-all hover:bg-cyan-50">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                            <span class="hidden sm:inline">Ganti Peran</span>
                        </a>
                    @endif
                    
                    <!-- Logout Button -->
                    <form method="POST" action="{{ route('logout') }}" class="inline flex-none">
                        @csrf
                        <button type="submit" class="flex flex-none items-center gap-2 rounded-2xl bg-cyan-900 px-3 py-2 text-xs font-bold text-white shadow-lg shadow-cyan-900/20 transition-all hover:bg-cyan-800 ring-1 ring-cyan-800">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                            </svg>
                            <span class="hidden sm:inline">Keluar</span>
                        </button>
                    </form>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="mx-auto w-full max-w-screen-2xl min-w-0 flex-1 overflow-x-hidden px-4 py-6 md:px-6 lg:px-8">
            <!-- Status Message -->
            @if(session('status'))
                <div class="mb-6 rounded-2xl border border-emerald-200 bg-linear-to-r from-emerald-50 to-cyan-50 px-5 py-4 text-sm font-medium text-emerald-800 shadow-sm">
                    <div class="flex gap-3">
                        <svg class="w-5 h-5 shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        <span>{{ session('status') }}</span>
                    </div>
                </div>
            @endif
            
            <!-- Page Content -->
            @yield('content')
        </main>

        <!-- Footer -->
        <footer class="border-t border-sky-100 bg-white/70 px-5 py-5 text-center text-xs text-slate-500 md:px-8">
            <p class="font-bold text-slate-700">SI-KP Farmasi UBP</p>
            <p class="mt-1">Sistem Informasi Portal Akademik Kerja Praktek Farmasi Universitas Buana Perjuangan Karawang</p>
        </footer>
    </div>
</div>
</body>
</html>
