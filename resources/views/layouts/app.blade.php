<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name', 'MY PKPA'))</title>
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
        <div class="relative flex-none overflow-hidden border-b border-sky-100 px-4 py-4 sm:px-5 lg:py-6">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,rgba(56,189,248,0.22),transparent_46%),linear-gradient(135deg,rgba(20,184,166,0.16),transparent)]"></div>
            <div class="relative flex items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <div class="flex h-12 w-12 items-center justify-center overflow-hidden rounded-2xl bg-white p-2 shadow-lg shadow-cyan-700/14 ring-1 ring-sky-100">
                    <img src="{{ asset('images/logo-fakultas-farmasi-ubp.png') }}" alt="Logo Fakultas Farmasi UBP" class="h-full w-full object-contain">
                </div>
                <div class="hidden lg:block">
                    <p class="text-sm font-black tracking-widest uppercase text-slate-950">MY PKPA</p>
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
        <nav class="flex gap-2 overflow-x-auto px-3 py-3 sm:px-4 sm:py-4 lg:block lg:min-h-0 lg:flex-1 lg:space-y-1.5 lg:overflow-x-hidden lg:overflow-y-auto lg:overscroll-contain lg:p-4 lg:pr-3 si-sidebar-scroll">
            <p class="mb-3 hidden px-3 text-[11px] font-black uppercase tracking-widest text-sky-700/70 lg:block">Menu PKPA</p>
            @php
                $menuGroups = [
                    'Dashboard' => 'Awal',
                    'Profil Saya' => 'Awal',
                    'Program PKPA' => 'Master PKPA',
                    'Wahana PKPA' => 'Master PKPA',
                    'Tempat Praktik' => 'Master PKPA',
                    'Tempat Tersedia' => 'Penjadwalan',
                    'Kapasitas Tempat' => 'Penjadwalan',
                    'Log Kapasitas' => 'Penjadwalan',
                    'Pembimbing Dalam' => 'Penjadwalan',
                    'Preseptor' => 'Penjadwalan',
                    'Kesiapan Penempatan' => 'Penjadwalan',
                    'Persyaratan Dokumen' => 'Peserta & Administrasi',
                    'Pendaftaran PKPA' => 'Peserta & Administrasi',
                    'Berkas PKPA' => 'Peserta & Administrasi',
                    'Peserta PKPA' => 'Peserta & Administrasi',
                    'Kelompok PKPA' => 'Peserta & Administrasi',
                    'Verifikasi Pendaftaran' => 'Peserta & Administrasi',
                    'Pembekalan' => 'Peserta & Administrasi',
                    'Hasil Pembekalan' => 'Peserta & Administrasi',
                    'PKPA Saya' => 'Penempatan',
                    'Penyusunan Penempatan' => 'Penempatan',
                    'Publikasi Penempatan' => 'Penempatan',
                    'Penempatan PKPA' => 'Penempatan',
                    'Log Penempatan' => 'Penempatan',
                    'Jadwal PKPA' => 'Penempatan',
                    'Mahasiswa Bimbingan' => 'Penempatan',
                    'Mahasiswa PKPA' => 'Penempatan',
                    'Rotasi PKPA' => 'Pelaksanaan',
                    'Operasional Rotasi' => 'Pelaksanaan',
                    'Operasional PKPA' => 'Pelaksanaan',
                    'Pemantauan PKPA' => 'Pelaksanaan',
                    'Logbook PKPA' => 'Pelaksanaan',
                    'Validasi Logbook' => 'Pelaksanaan',
                    'Logbook Mahasiswa' => 'Pelaksanaan',
                    'Pemantauan Logbook' => 'Pelaksanaan',
                    'Log Aktivitas Logbook' => 'Pelaksanaan',
                    'Panduan Kompetensi' => 'Akademik & Portofolio',
                    'Akademik Rotasi' => 'Akademik & Portofolio',
                    'Akademik PKPA' => 'Akademik & Portofolio',
                    'Capaian Kompetensi' => 'Akademik & Portofolio',
                    'Daftar Kompetensi' => 'Akademik & Portofolio',
                    'Laporan Akhir' => 'Akademik & Portofolio',
                    'Pemeriksaan Laporan' => 'Akademik & Portofolio',
                    'Pemantauan Laporan' => 'Akademik & Portofolio',
                    'Log Laporan' => 'Akademik & Portofolio',
                    'Portofolio PKPA' => 'Akademik & Portofolio',
                    'Review Portofolio' => 'Akademik & Portofolio',
                    'Ujian' => 'Evaluasi',
                    'Pengajuan Ujian' => 'Evaluasi',
                    'Jadwal Ujian' => 'Evaluasi',
                    'Jadwal Sidang' => 'Evaluasi',
                    'Log Ujian' => 'Evaluasi',
                    'Komponen Penilaian' => 'Evaluasi',
                    'Penilaian PKPA' => 'Evaluasi',
                    'Penilaian Pembimbing' => 'Evaluasi',
                    'Penilaian Lapangan' => 'Evaluasi',
                    'Penilaian Sidang' => 'Evaluasi',
                    'Nilai PKPA' => 'Evaluasi',
                    'Pemantauan Nilai' => 'Evaluasi',
                    'Log Nilai' => 'Evaluasi',
                    'Nilai' => 'Evaluasi',
                    'Penyelesaian PKPA' => 'Pelaporan & Akhir',
                    'Hasil Akhir PKPA' => 'Pelaporan & Akhir',
                    'Dokumen PKPA' => 'Pelaporan & Akhir',
                    'Rekap PKPA' => 'Pelaporan & Akhir',
                    'Pelaporan Analitik' => 'Pelaporan & Akhir',
                    'Pemeriksaan Integrasi' => 'Pelaporan & Akhir',
                ];
                $currentMenuGroup = null;
            @endphp
            @foreach(($roleData['menu'] ?? ['Dashboard', 'Profil Saya']) as $item)
                @php
                    $itemGroup = $menuGroups[$item] ?? 'Lainnya';
                    $isDashboard = $item === 'Dashboard';
                    $isProfile = $item === 'Profil Saya';
                    $routeMap = [
                        'Manajemen Pengguna' => 'admin.users.index',
                        'Manajemen User' => 'admin.users.index',
                        'Impor Pengguna' => 'admin.import-users.index',
                        'Import User' => 'admin.import-users.index',
                        'Riwayat Impor' => 'admin.import-users.history',
                        'Riwayat Import' => 'admin.import-users.history',
                        'Periode KP' => 'management.kp-periods.index',
                        'Program PKPA' => 'management.pkpa-programs.index',
                        'Wahana PKPA' => 'management.pkpa-practice-domains.index',
                        'Peserta PKPA' => 'management.pkpa-enrollments.index',
                        'Kelompok PKPA' => 'management.pkpa-student-groups.index',
                        'Tempat KP' => 'management.kp-places.index',
                        'Tempat Praktik' => 'management.pkpa-practice-sites.index',
                        'Tempat Tersedia' => 'management.pkpa-program-sites.index',
                        'Pembimbing Dalam' => 'management.pkpa-internal-supervisors.index',
                        'Preseptor' => 'management.pkpa-program-sites.index',
                        'Kesiapan Penempatan' => 'management.pkpa-placement-readiness.index',
                        'Penyusunan Penempatan' => 'management.pkpa-placement-planner.index',
                        'Publikasi Penempatan' => 'management.pkpa-publications.index',
                        'Kuota Tempat KP' => 'management.kp-place-quotas.index',
                        'Kapasitas Tempat' => 'management.kp-place-quotas.index',
                        'Log Kuota' => 'management.kp-quota-logs.index',
                        'Log Kapasitas' => 'management.kp-quota-logs.index',
                        'Persyaratan Dokumen' => 'management.document-requirements.index',
                        'Verifikasi Pendaftaran' => 'management.pkpa-registrations.index',
                        'Pendaftaran KP' => 'student.pkpa-registrations.index',
                        'Pendaftaran PKPA' => 'student.pkpa-registrations.index',
                        'Berkas KP' => 'student.pkpa-registration-documents.index',
                        'Berkas PKPA' => 'student.pkpa-registration-documents.index',
                        'Pemilihan Tempat KP' => 'student.place-selections.index',
                        'PKPA Saya' => 'student.pkpa-schedule.index',
                        'Rotasi PKPA' => 'student.pkpa-operations.index',
                        'Akademik Rotasi' => $activeRole === 'mahasiswa' ? 'student.pkpa-academics.index' : 'management.pkpa-academics.index',
                        'Nilai PKPA' => 'student.pkpa-grades.index',
                        'Hasil Akhir PKPA' => 'student.pkpa-final-results.index',
                        'Dokumen PKPA' => $activeRole === 'mahasiswa' ? 'student.pkpa-documents.index' : 'management.pkpa-documents.index',
                        'Portofolio PKPA' => $activeRole === 'mahasiswa' ? 'student.pkpa-portfolios.index' : 'management.pkpa-portfolios.index',
                        'Review Portofolio' => $activeRole === 'pembimbing_lapangan' ? 'field-supervisor.pkpa-portfolios.index' : 'internal-supervisor.pkpa-portfolios.index',
                        'Penyelesaian PKPA' => 'management.pkpa-final-program.index',
                        'Pelaporan Analitik' => 'management.pkpa-analytics.index',
                        'Pelaporan Analytics' => 'management.pkpa-analytics.index',
                        'Penilaian PKPA' => match ($activeRole) {
                            'pembimbing_lapangan' => 'field-supervisor.pkpa-assessments.index',
                            'pembimbing_dalam' => 'internal-supervisor.pkpa-assessments.index',
                            default => 'management.pkpa-assessments.index',
                        },
                        'Operasional Rotasi' => 'management.pkpa-operations.index',
                        'Operasional PKPA' => 'field-supervisor.pkpa-operations.index',
                        'Pemantauan PKPA' => 'internal-supervisor.pkpa-operations.index',
                        'Monitoring PKPA' => 'internal-supervisor.pkpa-operations.index',
                        'Akademik PKPA' => $activeRole === 'pembimbing_lapangan' ? 'field-supervisor.pkpa-academics.index' : 'internal-supervisor.pkpa-academics.index',
                        'Pemantauan Pemilihan' => 'management.place-selections.index',
                        'Monitoring Pemilihan' => 'management.place-selections.index',
                        'Daftar Tunggu' => 'management.waiting-lists.index',
                        'Log Pemilihan' => 'management.selection-logs.index',
                        'Penempatan KP' => $activeRole === 'mahasiswa' ? 'student.pkpa-placement.show' : 'management.pkpa-assignments.index',
                        'Penempatan PKPA' => $activeRole === 'mahasiswa' ? 'student.pkpa-placement.show' : 'management.pkpa-assignments.index',
                        'Log Penempatan' => 'management.pkpa-assignment-logs.index',
                        'Panduan Kompetensi' => 'management.competencies.index',
                        'Jadwal PKPA' => $activeRole === 'pembimbing_lapangan' ? 'field-supervisor.pkpa-schedule.index' : 'internal-supervisor.pkpa-schedule.index',
                        'Mahasiswa Bimbingan' => 'internal-supervisor.pkpa-students.index',
                        'Capaian Kompetensi' => 'internal-supervisor.competencies.index',
                        'Mahasiswa KP' => 'field-supervisor.pkpa-students.index',
                        'Mahasiswa PKPA' => 'field-supervisor.pkpa-students.index',
                        'Daftar Kompetensi' => 'field-supervisor.competencies.index',
                        'Checklist Kompetensi' => 'field-supervisor.competencies.index',
                        'Logbook KP' => 'student.pkpa-journals.index',
                        'Logbook PKPA' => 'student.pkpa-journals.index',
                        'Validasi Logbook' => 'field-supervisor.pkpa-journals.index',
                        'Logbook Mahasiswa' => 'internal-supervisor.pkpa-journals.index',
                        'Pemantauan Logbook' => 'management.pkpa-logbook-monitoring.index',
                        'Monitoring Logbook' => 'management.pkpa-logbook-monitoring.index',
                        'Log Aktivitas Logbook' => 'management.pkpa-logbook-logs.index',
                        'Laporan Akhir' => 'student.pkpa-final-report.show',
                        'Pemeriksaan Laporan' => $activeRole === 'pembimbing_lapangan' ? 'field-supervisor.pkpa-final-reports.index' : 'internal-supervisor.pkpa-final-reports.index',
                        'Review Laporan' => $activeRole === 'pembimbing_lapangan' ? 'field-supervisor.pkpa-final-reports.index' : 'internal-supervisor.pkpa-final-reports.index',
                        'Pemantauan Laporan' => 'management.pkpa-final-reports.index',
                        'Monitoring Laporan' => 'management.pkpa-final-reports.index',
                        'Log Laporan' => 'management.pkpa-final-report-logs.index',
                        'Sidang' => 'student.pkpa-exams.index',
                        'Ujian' => 'student.pkpa-exams.index',
                        'Pre/Post Test' => 'student.orientation-tests.index',
                        'Pembekalan' => in_array($activeRole, ['admin', 'koordinator_kp'], true)
                            ? 'management.orientation-tests.index'
                            : 'student.orientation-tests.index',
                        'Pengajuan Sidang' => 'management.exam-requests.index',
                        'Pengajuan Ujian' => 'management.exam-requests.index',
                        'Jadwal Sidang' => $activeRole === 'pembimbing_dalam' ? 'internal-supervisor.pkpa-exams.index' : ($activeRole === 'penguji' ? 'examiner.pkpa-exams.index' : 'management.pkpa-exams.index'),
                        'Jadwal Ujian' => $activeRole === 'pembimbing_dalam' ? 'internal-supervisor.pkpa-exams.index' : ($activeRole === 'penguji' ? 'examiner.pkpa-exams.index' : 'management.pkpa-exams.index'),
                        'Log Sidang' => 'management.exam-logs.index',
                        'Log Ujian' => 'management.exam-logs.index',
                        'Komponen Penilaian' => 'management.assessment-components.index',
                        'Pemantauan Nilai' => 'management.pkpa-score-monitoring.index',
                        'Monitoring Nilai' => 'management.pkpa-score-monitoring.index',
                        'Hasil Pre/Post Test' => 'management.orientation-tests.index',
                        'Hasil Pembekalan' => 'management.orientation-tests.index',
                        'Log Nilai' => 'management.pkpa-score-logs.index',
                        'Rekap KP' => 'management.recaps.index',
                        'Rekap PKPA' => 'management.recaps.index',
                        'Pemeriksaan Integrasi' => 'management.integration.tu-payload-preview',
                        'Review Integrasi' => 'management.integration.tu-payload-preview',
                        'Penilaian Pembimbing' => 'internal-supervisor.assessments.index',
                        'Penilaian Lapangan' => 'field-supervisor.assessments.index',
                        'Penilaian Sidang' => 'examiner.assessments.index',
                        'Nilai' => 'student.scores.show',
                    ];
                    $activeMap = [
                        'Dashboard' => [$roleData['route'] ?? 'dashboard'],
                        'Profil Saya' => ['profile.show', 'profile.edit'],
                        'Pendaftaran KP' => ['student.pkpa-registrations.*'],
                        'Pendaftaran PKPA' => ['student.pkpa-registrations.*'],
                        'Berkas KP' => ['student.pkpa-registration-documents.index', 'student.pkpa-registrations.show', 'student.pkpa-registrations.documents.*', 'student.pkpa-registrations.submit', 'student.pkpa-registrations.cancel'],
                        'Berkas PKPA' => ['student.pkpa-registration-documents.index', 'student.pkpa-registrations.show', 'student.pkpa-registrations.documents.*', 'student.pkpa-registrations.submit', 'student.pkpa-registrations.cancel'],
                        'Pemilihan Tempat KP' => ['student.place-selections.*'],
                        'PKPA Saya' => ['student.pkpa-schedule.*'],
                        'Rotasi PKPA' => ['student.pkpa-operations.*', 'student.pkpa-attendance.*', 'student.pkpa-logbooks.*'],
                        'Akademik Rotasi' => ['student.pkpa-academics.*', 'student.pkpa-competencies.*', 'student.pkpa-special-tasks.*', 'student.pkpa-rotation-reports.*', 'student.pkpa-guidance.*', 'management.pkpa-academics.*', 'management.pkpa-competency-*', 'management.pkpa-special-task-*', 'management.pkpa-rotation-report-*'],
                        'Nilai PKPA' => ['student.pkpa-grades.*'],
                        'Hasil Akhir PKPA' => ['student.pkpa-final-results.*'],
                        'Dokumen PKPA' => ['student.pkpa-documents.*', 'management.pkpa-documents.*', 'management.pkpa-document-*'],
                        'Portofolio PKPA' => ['student.pkpa-portfolios.*', 'management.pkpa-portfolios.*'],
                        'Review Portofolio' => ['internal-supervisor.pkpa-portfolios.*', 'field-supervisor.pkpa-portfolios.*'],
                        'Penyelesaian PKPA' => ['management.pkpa-final-*', 'management.pkpa-requirements.completion.*', 'management.pkpa-graduation-*', 'management.pkpa-remedials.*'],
                        'Pelaporan Analitik' => ['management.pkpa-analytics.*'],
                        'Pelaporan Analytics' => ['management.pkpa-analytics.*'],
                        'Penilaian PKPA' => ['management.pkpa-assessments.*', 'management.pkpa-assessment-*', 'management.pkpa-rotation-assessments.*', 'management.pkpa-grade-*', 'field-supervisor.pkpa-assessments.*', 'internal-supervisor.pkpa-assessments.*'],
                        'Operasional Rotasi' => ['management.pkpa-operations.*', 'management.pkpa-rotation-runs.*'],
                        'Operasional PKPA' => ['field-supervisor.pkpa-operations.*', 'field-supervisor.pkpa-attendance.*', 'field-supervisor.pkpa-logbooks.*'],
                        'Pemantauan PKPA' => ['internal-supervisor.pkpa-operations.*', 'internal-supervisor.pkpa-logbooks.*'],
                        'Monitoring PKPA' => ['internal-supervisor.pkpa-operations.*', 'internal-supervisor.pkpa-logbooks.*'],
                        'Akademik PKPA' => ['field-supervisor.pkpa-academics.*', 'field-supervisor.pkpa-competencies.*', 'field-supervisor.pkpa-special-tasks.*', 'field-supervisor.pkpa-rotation-reports.*', 'internal-supervisor.pkpa-academics.*', 'internal-supervisor.pkpa-competencies.*', 'internal-supervisor.pkpa-special-tasks.*', 'internal-supervisor.pkpa-rotation-reports.*', 'internal-supervisor.pkpa-guidance.*'],
                        'Penempatan KP' => ['student.pkpa-placement.*', 'management.pkpa-assignments.*'],
                        'Penempatan PKPA' => ['student.pkpa-placement.*', 'management.pkpa-assignments.*'],
                        'Logbook KP' => ['student.pkpa-journals.*'],
                        'Logbook PKPA' => ['student.pkpa-journals.*'],
                        'Laporan Akhir' => ['student.pkpa-final-report.*'],
                        'Manajemen Pengguna' => ['admin.users.*'],
                        'Manajemen User' => ['admin.users.*'],
                        'Impor Pengguna' => ['admin.import-users.index', 'admin.import-users.preview', 'admin.import-users.process', 'admin.import-users.template'],
                        'Import User' => ['admin.import-users.index', 'admin.import-users.preview', 'admin.import-users.process', 'admin.import-users.template'],
                        'Riwayat Impor' => ['admin.import-users.history', 'admin.import-users.history.*'],
                        'Riwayat Import' => ['admin.import-users.history', 'admin.import-users.history.*'],
                        'Periode KP' => ['management.kp-periods.*'],
                        'Program PKPA' => ['management.pkpa-programs.*'],
                        'Wahana PKPA' => ['management.pkpa-practice-domains.*'],
                        'Peserta PKPA' => ['management.pkpa-enrollments.*', 'management.pkpa-enrollment-imports.*'],
                        'Kelompok PKPA' => ['management.pkpa-student-groups.*'],
                        'Tempat KP' => ['management.kp-places.*'],
                        'Tempat Praktik' => ['management.pkpa-practice-sites.*'],
                        'Tempat Tersedia' => ['management.pkpa-program-sites.*'],
                        'Pembimbing Dalam' => ['management.pkpa-internal-supervisors.*'],
                        'Preseptor' => ['management.pkpa-program-sites.*'],
                        'Kesiapan Penempatan' => ['management.pkpa-placement-readiness.*'],
                        'Penyusunan Penempatan' => ['management.pkpa-placement-planner.*', 'management.pkpa-placement-plans.*', 'management.pkpa-placement-batches.*', 'management.pkpa-rotation-assignments.*'],
                        'Publikasi Penempatan' => ['management.pkpa-publications.*', 'management.pkpa-change-requests.*', 'management.pkpa-notifications.*'],
                        'Kuota Tempat KP' => ['management.kp-place-quotas.*'],
                        'Kapasitas Tempat' => ['management.kp-place-quotas.*'],
                        'Log Kuota' => ['management.kp-quota-logs.*'],
                        'Log Kapasitas' => ['management.kp-quota-logs.*'],
                        'Persyaratan Dokumen' => ['management.document-requirements.*'],
                        'Verifikasi Pendaftaran' => ['management.pkpa-registrations.*'],
                        'Pemantauan Pemilihan' => ['management.place-selections.*'],
                        'Monitoring Pemilihan' => ['management.place-selections.*'],
                        'Daftar Tunggu' => ['management.waiting-lists.*'],
                        'Log Pemilihan' => ['management.selection-logs.*'],
                        'Log Penempatan' => ['management.pkpa-assignment-logs.*'],
                        'Panduan Kompetensi' => ['management.competencies.*'],
                        'Jadwal PKPA' => ['internal-supervisor.pkpa-schedule.*', 'field-supervisor.pkpa-schedule.*'],
                        'Mahasiswa Bimbingan' => ['internal-supervisor.pkpa-students.*'],
                        'Capaian Kompetensi' => ['internal-supervisor.competencies.*'],
                        'Mahasiswa KP' => ['field-supervisor.pkpa-students.*'],
                        'Mahasiswa PKPA' => ['field-supervisor.pkpa-students.*'],
                        'Daftar Kompetensi' => ['field-supervisor.competencies.*'],
                        'Checklist Kompetensi' => ['field-supervisor.competencies.*'],
                        'Validasi Logbook' => ['field-supervisor.pkpa-journals.*'],
                        'Logbook Mahasiswa' => ['internal-supervisor.pkpa-journals.*'],
                        'Pemantauan Logbook' => ['management.pkpa-logbook-monitoring.*'],
                        'Monitoring Logbook' => ['management.pkpa-logbook-monitoring.*'],
                        'Log Aktivitas Logbook' => ['management.pkpa-logbook-logs.*'],
                        'Pemeriksaan Laporan' => ['internal-supervisor.pkpa-final-reports.*', 'field-supervisor.pkpa-final-reports.*'],
                        'Review Laporan' => ['internal-supervisor.pkpa-final-reports.*', 'field-supervisor.pkpa-final-reports.*'],
                        'Pemantauan Laporan' => ['management.pkpa-final-reports.*'],
                        'Monitoring Laporan' => ['management.pkpa-final-reports.*'],
                        'Log Laporan' => ['management.pkpa-final-report-logs.*'],
                        'Sidang' => ['student.pkpa-exams.*'],
                        'Ujian' => ['student.pkpa-exams.*'],
                        'Pre/Post Test' => ['student.orientation-tests.*'],
                        'Pembekalan' => ['student.orientation-tests.*', 'management.orientation-tests.*'],
                        'Pengajuan Sidang' => ['management.exam-requests.*'],
                        'Pengajuan Ujian' => ['management.exam-requests.*'],
                        'Jadwal Sidang' => ['management.pkpa-exams.*', 'internal-supervisor.pkpa-exams.*', 'examiner.pkpa-exams.*'],
                        'Jadwal Ujian' => ['management.pkpa-exams.*', 'internal-supervisor.pkpa-exams.*', 'examiner.pkpa-exams.*'],
                        'Log Sidang' => ['management.exam-logs.*'],
                        'Log Ujian' => ['management.exam-logs.*'],
                        'Komponen Penilaian' => ['management.assessment-components.*'],
                        'Pemantauan Nilai' => ['management.pkpa-score-monitoring.*', 'management.pkpa-final-scores.*'],
                        'Monitoring Nilai' => ['management.pkpa-score-monitoring.*', 'management.pkpa-final-scores.*'],
                        'Hasil Pre/Post Test' => ['management.orientation-tests.*'],
                        'Hasil Pembekalan' => ['management.orientation-tests.*'],
                        'Log Nilai' => ['management.pkpa-score-logs.*'],
                        'Rekap KP' => ['management.recaps.*', 'management.exports.*'],
                        'Rekap PKPA' => ['management.recaps.*', 'management.exports.*'],
                        'Pemeriksaan Integrasi' => ['management.integration.*'],
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
                @if($itemGroup !== $currentMenuGroup)
                    @php($currentMenuGroup = $itemGroup)
                    <p class="{{ $loop->first ? '' : 'mt-5' }} hidden px-3 pb-1 pt-2 text-[10px] font-black uppercase tracking-widest text-slate-400 lg:block">{{ $itemGroup }}</p>
                @endif
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
            <div class="mx-auto flex w-full max-w-screen-2xl min-w-0 flex-col gap-3 px-3 py-3 sm:px-4 sm:py-4 md:flex-row md:items-center md:justify-between md:gap-3 md:px-5 lg:px-6 xl:px-8">
                <!-- Page Title -->
                <div class="min-w-0">
                    <p class="text-xs font-black uppercase tracking-widest text-cyan-700 mb-1">{{ config('app.name') }}</p>
                    <h1 class="truncate text-xl font-black leading-tight tracking-tight text-slate-950 sm:text-2xl">@yield('page_title', 'Dashboard')</h1>
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
                        <button type="submit" class="flex flex-none items-center gap-2 rounded-2xl bg-cyan-900 px-3 py-2 text-xs font-bold text-white shadow-lg shadow-cyan-900/20 ring-1 ring-cyan-800 transition-all hover:bg-cyan-800">
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
        <main class="mx-auto w-full max-w-screen-2xl min-w-0 flex-1 overflow-x-hidden px-3 py-4 sm:px-4 sm:py-5 md:px-5 lg:px-6 xl:px-8">
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
            <p class="font-bold text-slate-700">MY PKPA</p>
            <p class="mt-1">Sistem Informasi Praktik Kerja Profesi Apoteker Universitas Buana Perjuangan Karawang</p>
        </footer>
    </div>
</div>
@stack('scripts')
</body>
</html>
