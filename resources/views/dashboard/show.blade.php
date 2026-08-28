@extends('layouts.app')

@section('title', 'Dashboard '.$roleData['label'].' - '.config('app.name'))
@section('page_title', 'Dashboard '.$roleData['label'])

@section('content')
@php
    $user = auth()->user();
    $displayName = user_display_name($user, session('active_role'));
    $firstName = $displayName ?: $user->name;
    $activeRole = session('active_role');

    $formatLabel = fn (string $label): string => str($label)->replace('_', ' ')->headline()->toString();
    $formatValue = fn ($value): string => is_numeric($value) ? number_format((int) $value, 0, ',', '.') : (string) ($value ?: '-');
    $dashboardValue = function ($value) use ($formatValue): string {
        $formatted = $formatValue($value);

        return match (strtolower($formatted)) {
            'published' => 'Dipublikasi',
            'verified' => 'Terverifikasi',
            'approved' => 'Disetujui',
            'submitted' => 'Terkirim',
            'belum tersedia' => 'Belum ada',
            default => $formatted,
        };
    };

    $featureDescriptions = [
        'Pendaftaran PKPA' => 'Pengajuan dan status verifikasi pendaftaran PKPA.',
        'Berkas Persyaratan' => 'Kelengkapan dokumen awal peserta PKPA.',
        'Berkas PKPA' => 'Berkas administrasi PKPA.',
        'Pembekalan' => 'Pretest, posttest, dan hasil pembekalan sebelum PKPA berjalan.',
        'PKPA Saya' => 'Jadwal resmi PKPA yang sudah diterbitkan ke portal.',
        'Penempatan PKPA' => 'Ringkasan penempatan resmi per wahana dan tempat praktik.',
        'Logbook' => 'Catatan aktivitas harian PKPA per wahana.',
        'Akademik Rotasi' => 'Kompetensi, tugas, dan keluaran akademik tiap rotasi.',
        'Laporan Akhir' => 'Dokumen laporan atau keluaran akhir yang sedang diperiksa.',
        'Portofolio PKPA' => 'Penyusunan portofolio PKPA per wahana secara bertahap.',
        'Ujian' => 'Pengajuan, jadwal, dan pelaksanaan ujian PKPA.',
        'Nilai PKPA' => 'Rekap nilai PKPA dari pembimbing dalam, preseptor, dan ujian.',
        'Hasil Akhir PKPA' => 'Status kelulusan dan penyelesaian akhir program.',
        'Mahasiswa Bimbingan' => 'Daftar mahasiswa yang menjadi tanggung jawab pembimbing dalam.',
        'Mahasiswa PKPA' => 'Daftar mahasiswa yang terhubung ke preseptor.',
        'Pemantauan PKPA' => 'Pantau jadwal, progres, dan aktivitas mahasiswa bimbingan.',
        'Operasional PKPA' => 'Jadwal operasional rotasi yang berjalan di tempat praktik.',
        'Logbook Mahasiswa' => 'Pemantauan logbook mahasiswa yang dibimbing.',
        'Validasi Logbook' => 'Validasi aktivitas harian mahasiswa PKPA.',
        'Akademik PKPA' => 'Kompetensi, tugas, dan capaian akademik yang perlu dipantau.',
        'Capaian Kompetensi' => 'Pantau capaian kompetensi mahasiswa per rotasi.',
        'Daftar Kompetensi' => 'Daftar kompetensi PKPA yang perlu dipenuhi mahasiswa.',
        'Pemeriksaan Laporan' => 'Pemeriksaan dokumen akademik dan laporan mahasiswa.',
        'Review Portofolio' => 'Pemeriksaan portofolio PKPA yang diajukan mahasiswa.',
        'Jadwal Sidang' => 'Agenda ujian PKPA yang terkait dengan peran Anda.',
        'Jadwal Ujian' => 'Agenda ujian PKPA yang terkait dengan peran Anda.',
        'Penilaian Pembimbing' => 'Input nilai pembimbing dalam.',
        'Penilaian Preseptor' => 'Input nilai preseptor.',
        'Penilaian PKPA' => 'Rekap dan tindak lanjut penilaian PKPA.',
        'Detail Mahasiswa Sidang' => 'Data mahasiswa yang mengikuti ujian PKPA.',
        'Detail Mahasiswa Ujian' => 'Data mahasiswa yang mengikuti ujian PKPA.',
        'Penilaian Sidang' => 'Input nilai penguji ujian PKPA.',
        'Penilaian Ujian' => 'Input nilai penguji ujian PKPA.',
        'Manajemen Pengguna' => 'Kelola akun dan peran pengguna PKPA.',
        'Impor Excel' => 'Impor data pengguna secara terstruktur.',
        'Program PKPA' => 'Master program tahunan, periode, dan konfigurasi inti.',
        'Wahana PKPA' => 'Kelola lima wahana utama dan sub wahana pemerintahan.',
        'Tempat Praktik' => 'Master mitra, lokasi, dan identitas tempat praktik.',
        'Peserta PKPA' => 'Kepesertaan mahasiswa dari Core Farmasi.',
        'Kelompok PKPA' => 'Kelompok mahasiswa untuk persiapan penempatan.',
        'Tempat Tersedia' => 'Ketersediaan tempat, kapasitas, dan preseptor per program.',
        'Kapasitas Tempat' => 'Kuota operasional per tempat dan per periode rotasi.',
        'Log Kapasitas' => 'Riwayat perubahan kapasitas tempat praktik.',
        'Pembimbing Dalam' => 'Pool dosen pembimbing dalam yang berlaku untuk program aktif.',
        'Preseptor' => 'Koneksi preseptor dari Core pada tempat tersedia.',
        'Kesiapan Penempatan' => 'Daftar kesiapan sebelum penyusunan penempatan PKPA.',
        'Penyusunan Penempatan' => 'Matriks dan rancangan draf penempatan PKPA.',
        'Publikasi Penempatan' => 'Penguncian rancangan dan penayangan jadwal resmi ke portal.',
        'Jadwal PKPA' => 'Jadwal bimbingan PKPA resmi.',
        'Persyaratan Dokumen' => 'Aturan berkas yang wajib dipenuhi peserta.',
        'Verifikasi Pendaftaran' => 'Pemeriksaan administrasi pendaftaran peserta PKPA.',
        'Rekap PKPA' => 'Rekap operasional dan ekspor data PKPA.',
        'Hasil Pembekalan' => 'Monitoring hasil pembekalan seluruh peserta.',
        'Panduan Kompetensi' => 'Master kompetensi, capaian, dan penugasan wahana.',
        'Pemantauan Logbook' => 'Monitoring logbook lintas peserta dan wahana.',
        'Pemantauan Laporan' => 'Monitoring dokumen laporan dan revisinya.',
        'Komponen Penilaian' => 'Pengaturan komponen nilai untuk pembimbing dan ujian.',
        'Pemantauan Nilai' => 'Monitoring progres input nilai PKPA.',
        'Penyelesaian PKPA' => 'Tahap akhir kelulusan, remedial, dan penutupan program.',
        'Dokumen PKPA' => 'Arsip dokumen resmi PKPA yang dapat diunduh.',
        'Pelaporan Analitik' => 'Ringkasan kinerja program dan data analitik PKPA.',
        'Pemeriksaan Integrasi' => 'Pemeriksaan integrasi data PKPA ke sistem lain.',
    ];

    $heroDescriptions = [
        'admin' => 'Kelola fondasi PKPA dari master program, wahana, tempat, pembimbing, hingga publikasi akhir agar semua role menerima jadwal yang sama.',
        'koordinator_kp' => 'Pantau alur PKPA dari pembekalan, penempatan, sampai publikasi jadwal agar mahasiswa, pembimbing dalam, dan preseptor bergerak sinkron.',
        'mahasiswa' => 'Pantau administrasi, jadwal wahana, logbook, portofolio, ujian, dan hasil akhir PKPA dari satu dashboard yang ringkas.',
        'pembimbing_dalam' => 'Lihat jadwal bimbingan, pantau kemajuan akademik mahasiswa, dan tuntaskan review portofolio serta penilaian pembimbing.',
        'pembimbing_lapangan' => 'Pantau jadwal mahasiswa di tempat praktik, validasi logbook, dan selesaikan penilaian preseptor sesuai periode rotasi.',
        'penguji' => 'Fokus pada agenda ujian PKPA, detail mahasiswa yang diuji, dan penyelesaian penilaian ujian.',
    ];

    $flowStepsByRole = [
        'admin' => [
            ['01', 'Master Program', 'Program, wahana, tempat, dan pembimbing disiapkan'],
            ['02', 'Peserta', 'Peserta, dokumen, dan pembekalan diverifikasi'],
            ['03', 'Penempatan', 'Rancangan, publikasi, dan rotasi dijalankan'],
            ['04', 'Akhir Program', 'Portofolio, ujian, penilaian, dan hasil akhir ditutup'],
        ],
        'koordinator_kp' => [
            ['01', 'Persiapan', 'Program tahunan, wahana, tempat, dan pembimbing ditetapkan'],
            ['02', 'Administrasi', 'Peserta, kelompok, berkas, dan pembekalan dipantau'],
            ['03', 'Rotasi', 'Penempatan disusun dan dipublikasikan per periode wahana'],
            ['04', 'Evaluasi', 'Portofolio, nilai, ujian, dan penyelesaian akhir dikawal'],
        ],
        'mahasiswa' => [
            ['01', 'Administrasi', 'Lengkapi berkas dan ikuti pembekalan'],
            ['02', 'Jadwal Resmi', 'Cek penempatan dan periode wahana yang dipublikasikan'],
            ['03', 'Pelaksanaan', 'Isi logbook, tugas akademik, dan portofolio tiap wahana'],
            ['04', 'Hasil Akhir', 'Ikuti ujian dan pantau nilai hingga dinyatakan selesai'],
        ],
        'pembimbing_dalam' => [
            ['01', 'Jadwal', 'Terima daftar mahasiswa dan jadwal bimbingan resmi'],
            ['02', 'Pemantauan', 'Pantau aktivitas, logbook, dan capaian akademik'],
            ['03', 'Review', 'Periksa laporan serta portofolio mahasiswa'],
            ['04', 'Penilaian', 'Selesaikan penilaian pembimbing dan dukung ujian'],
        ],
        'pembimbing_lapangan' => [
            ['01', 'Penugasan', 'Lihat mahasiswa yang ditempatkan pada lokasi praktik'],
            ['02', 'Operasional', 'Pantau kehadiran dan kegiatan harian di lapangan'],
            ['03', 'Validasi', 'Periksa logbook serta dokumen yang perlu konfirmasi'],
            ['04', 'Penilaian', 'Isi penilaian preseptor sesuai periode yang berjalan'],
        ],
        'penguji' => [
            ['01', 'Agenda', 'Lihat jadwal ujian yang menjadi tanggung jawab Anda'],
            ['02', 'Peserta', 'Pelajari data mahasiswa dan dokumen penunjang ujian'],
            ['03', 'Penilaian', 'Isi penilaian ujian sesuai komponen yang disediakan'],
            ['04', 'Final', 'Pastikan seluruh nilai penguji terkirim'],
        ],
    ];

    $heroDescription = $heroDescriptions[$activeRole] ?? 'Pantau modul PKPA sesuai peran aktif Anda dari satu dashboard yang ringkas.';
    $flowSteps = $flowStepsByRole[$activeRole] ?? [
        ['01', 'Administrasi', 'Pendaftaran, berkas, dan pembekalan peserta'],
        ['02', 'Penjadwalan', 'Program, wahana, tempat, dan pembimbing disiapkan'],
        ['03', 'Pelaksanaan', 'Rotasi wahana, logbook, dan portofolio berjalan'],
        ['04', 'Evaluasi', 'Penilaian, ujian, dan hasil akhir program'],
    ];

    $featureRoutes = [
        'Pendaftaran PKPA' => 'student.pkpa-registrations.index',
        'Berkas Persyaratan' => 'student.pkpa-registration-documents.index',
        'Berkas PKPA' => 'student.pkpa-registration-documents.index',
        'Logbook' => 'student.pkpa-journals.index',
        'Akademik Rotasi' => 'student.pkpa-academics.index',
        'Laporan Akhir' => 'student.pkpa-final-report.show',
        'Portofolio PKPA' => $activeRole === 'mahasiswa' ? 'student.pkpa-portfolios.index' : 'management.pkpa-portfolios.index',
        'Ujian' => 'student.pkpa-exams.index',
        'Nilai PKPA' => 'student.pkpa-grades.index',
        'Hasil Akhir PKPA' => 'student.pkpa-final-results.index',
        'Mahasiswa Bimbingan' => 'internal-supervisor.pkpa-students.index',
        'Pemantauan PKPA' => 'internal-supervisor.pkpa-operations.index',
        'Logbook Mahasiswa' => 'internal-supervisor.pkpa-journals.index',
        'Akademik PKPA' => $activeRole === 'pembimbing_lapangan' ? 'field-supervisor.pkpa-academics.index' : 'internal-supervisor.pkpa-academics.index',
        'Pemeriksaan Laporan' => $activeRole === 'pembimbing_lapangan' ? 'field-supervisor.pkpa-final-reports.index' : 'internal-supervisor.pkpa-final-reports.index',
        'Jadwal Sidang' => $activeRole === 'penguji' ? 'examiner.pkpa-exams.index' : ($activeRole === 'pembimbing_dalam' ? 'internal-supervisor.pkpa-exams.index' : 'management.pkpa-exams.index'),
        'Jadwal Ujian' => $activeRole === 'penguji' ? 'examiner.pkpa-exams.index' : ($activeRole === 'pembimbing_dalam' ? 'internal-supervisor.pkpa-exams.index' : 'management.pkpa-exams.index'),
        'Penilaian Pembimbing' => 'internal-supervisor.assessments.index',
        'Penilaian PKPA' => match ($activeRole) {
            'pembimbing_lapangan' => 'field-supervisor.pkpa-assessments.index',
            'pembimbing_dalam' => 'internal-supervisor.pkpa-assessments.index',
            default => 'management.pkpa-assessments.index',
        },
        'Mahasiswa PKPA' => 'field-supervisor.pkpa-students.index',
        'Validasi Logbook' => 'field-supervisor.pkpa-journals.index',
        'Penilaian Preseptor' => 'field-supervisor.assessments.index',
        'Penilaian Sidang' => 'examiner.assessments.index',
        'Penilaian Ujian' => 'examiner.assessments.index',
        'Manajemen Pengguna' => 'admin.users.index',
        'Impor Excel' => 'admin.import-users.index',
        'Program PKPA' => 'management.pkpa-programs.index',
        'Wahana PKPA' => 'management.pkpa-practice-domains.index',
        'Tempat Praktik' => 'management.pkpa-practice-sites.index',
        'Tempat Tersedia' => 'management.pkpa-program-sites.index',
        'Kapasitas Tempat' => 'management.kp-place-quotas.index',
        'Log Kapasitas' => 'management.kp-quota-logs.index',
        'Pembimbing Dalam' => 'management.pkpa-internal-supervisors.index',
        'Preseptor' => 'management.pkpa-program-sites.index',
        'Peserta PKPA' => 'management.pkpa-enrollments.index',
        'Kelompok PKPA' => 'management.pkpa-student-groups.index',
        'Kesiapan Penempatan' => 'management.pkpa-placement-readiness.index',
        'Penyusunan Penempatan' => 'management.pkpa-placement-planner.index',
        'Publikasi Penempatan' => 'management.pkpa-publications.index',
        'PKPA Saya' => 'student.pkpa-schedule.index',
        'Jadwal PKPA' => $activeRole === 'pembimbing_lapangan' ? 'field-supervisor.pkpa-schedule.index' : 'internal-supervisor.pkpa-schedule.index',
        'Pembekalan' => in_array($activeRole, ['admin', 'koordinator_kp'], true) ? 'management.orientation-tests.index' : 'student.orientation-tests.index',
        'Hasil Pembekalan' => 'management.orientation-tests.index',
        'Persyaratan Dokumen' => 'management.document-requirements.index',
        'Verifikasi Pendaftaran' => 'management.pkpa-registrations.index',
        'Operasional PKPA' => 'field-supervisor.pkpa-operations.index',
        'Operasional Rotasi' => 'management.pkpa-operations.index',
        'Panduan Kompetensi' => 'management.competencies.index',
        'Pemantauan Logbook' => 'management.pkpa-logbook-monitoring.index',
        'Pemantauan Laporan' => 'management.pkpa-final-reports.index',
        'Komponen Penilaian' => 'management.assessment-components.index',
        'Pemantauan Nilai' => 'management.pkpa-score-monitoring.index',
        'Penyelesaian PKPA' => 'management.pkpa-final-program.index',
        'Dokumen PKPA' => $activeRole === 'mahasiswa' ? 'student.pkpa-documents.index' : 'management.pkpa-documents.index',
        'Rekap PKPA' => 'management.recaps.index',
        'Pelaporan Analitik' => 'management.pkpa-analytics.index',
        'Pemeriksaan Integrasi' => 'management.integration.tu-payload-preview',
    ];

    $summarySections = collect([
        ['title' => 'Ringkasan Master PKPA', 'description' => 'Program, wahana, dan tempat praktik berdasarkan data aktual.', 'stats' => $pkpaMasterStats ?? null, 'tone' => 'cyan'],
        ['title' => 'Ringkasan Peserta PKPA', 'description' => 'Peserta, kelompok, sinkronisasi Core, dan kelengkapan persyaratan.', 'stats' => $pkpaEnrollmentStats ?? null, 'tone' => 'teal'],
        ['title' => 'Ringkasan Kesiapan PKPA', 'description' => 'Kapasitas tempat, ketersediaan, dan pembimbing dari Core.', 'stats' => $pkpaPlacementReadinessStats ?? null, 'tone' => 'emerald'],
        ['title' => 'Ringkasan Penyusunan Penempatan', 'description' => 'Rencana aktif, draf penempatan, validasi, dan masalah aktif.', 'stats' => $pkpaPlacementPlannerStats ?? null, 'tone' => 'amber'],
        ['title' => 'Ringkasan Publikasi PKPA', 'description' => 'Publikasi aktif, snapshot resmi, konfirmasi baca, dan pengiriman notifikasi.', 'stats' => $pkpaPublicationStats ?? null, 'tone' => 'emerald'],
        ['title' => 'Ringkasan Jadwal PKPA Saya', 'description' => 'Jadwal resmi dan konfirmasi baca mahasiswa.', 'stats' => $studentPkpaScheduleStats ?? null, 'tone' => 'cyan'],
        ['title' => 'Ringkasan Jadwal Bimbingan PKPA', 'description' => 'Jadwal resmi yang terhubung ke akun pembimbing.', 'stats' => $supervisorPkpaScheduleStats ?? null, 'tone' => 'cyan'],
        ['title' => 'Ringkasan Administrasi PKPA', 'description' => 'Status pendaftaran, verifikasi berkas, dan kesiapan peserta.', 'stats' => $registrationStats, 'tone' => 'indigo'],
        ['title' => 'Ringkasan Penempatan PKPA', 'description' => 'Status penempatan aktif dan keterhubungan pembimbing PKPA.', 'stats' => $assignmentStats, 'tone' => 'teal'],
        ['title' => 'Ringkasan Logbook PKPA', 'description' => 'Aktivitas harian mahasiswa dan status validasi logbook.', 'stats' => $logbookStats, 'tone' => 'emerald'],
        ['title' => 'Ringkasan Laporan dan Portofolio', 'description' => 'Dokumen akademik, revisi, dan persetujuan keluaran PKPA.', 'stats' => $finalReportStats, 'tone' => 'amber'],
        ['title' => 'Ringkasan Ujian PKPA', 'description' => 'Pengajuan, jadwal, dan status pelaksanaan ujian PKPA.', 'stats' => $examStats, 'tone' => 'violet'],
        ['title' => 'Ringkasan Nilai PKPA', 'description' => 'Kelengkapan input, finalisasi, dan publikasi nilai PKPA.', 'stats' => $scoreStats, 'tone' => 'rose'],
    ])->filter(fn ($section) => filled($section['stats']))->values();

    $primaryStats = $summarySections
        ->flatMap(fn ($section) => collect($section['stats'])->map(fn ($value, $key) => [
            'label' => $formatLabel($key),
            'value' => $value,
            'section' => $section['title'],
            'tone' => $section['tone'],
        ]))
        ->filter(fn ($stat) => is_numeric($stat['value']))
        ->sortByDesc(fn ($stat) => (int) $stat['value'])
        ->take(4)
        ->values();

    if ($activeRole === 'mahasiswa') {
        $primaryStats = collect([
            [
                'label' => 'Program PKPA',
                'value' => $studentPkpaEnrollment ? 'Terdaftar' : 'Belum terdaftar',
                'section' => $studentPkpaEnrollment?->program?->name ?? 'Hubungi pengelola program',
                'tone' => $studentPkpaEnrollment ? 'emerald' : 'amber',
            ],
            [
                'label' => 'Kelompok',
                'value' => $studentPkpaEnrollment?->activeGroupMembership?->group?->code ?? 'Belum',
                'section' => 'Kelompok PKPA',
                'tone' => $studentPkpaEnrollment?->activeGroupMembership ? 'cyan' : 'amber',
            ],
            [
                'label' => 'Kewajiban Wahana',
                'value' => $studentPkpaEnrollment ? $studentPkpaEnrollment->requirementSummary() : '0 dari 0 selesai',
                'section' => 'Penempatan belum dipublikasikan',
                'tone' => 'teal',
            ],
            [
                'label' => 'Jadwal Resmi',
                'value' => $studentPkpaScheduleStats['jadwal_resmi'] ?? 0,
                'section' => 'PKPA Saya',
                'tone' => ((int) ($studentPkpaScheduleStats['jadwal_resmi'] ?? 0)) > 0 ? 'emerald' : 'amber',
            ],
        ]);
    }

    if ($primaryStats->isEmpty()) {
        $primaryStats = collect([
            ['label' => 'Status akun', 'value' => 'Aktif', 'section' => 'Akun', 'tone' => 'emerald'],
            ['label' => 'Peran aktif', 'value' => $roleData['label'], 'section' => 'Peran', 'tone' => 'sky'],
            ['label' => 'Profil', 'value' => $user->profile_completed ? 'Lengkap' : 'Belum lengkap', 'section' => 'Profil', 'tone' => $user->profile_completed ? 'emerald' : 'amber'],
        ]);
    }

    $priorityItems = collect([
        [
            'label' => 'Masalah rancangan penempatan',
            'value' => (int) (($pkpaPlacementPlannerStats['error'] ?? 0) + ($pkpaPlacementPlannerStats['warning'] ?? 0)),
            'route' => 'management.pkpa-placement-planner.index',
            'visible' => in_array($role, ['admin', 'koordinator_kp'], true),
        ],
        [
            'label' => 'Permintaan perubahan publikasi aktif',
            'value' => (int) ($pkpaPublicationStats['change_request_aktif'] ?? 0),
            'route' => 'management.pkpa-publications.index',
            'visible' => in_array($role, ['admin', 'koordinator_kp'], true),
        ],
        [
            'label' => 'Notifikasi publikasi pending',
            'value' => (int) ($pkpaPublicationStats['notifikasi_pending'] ?? 0),
            'route' => 'management.pkpa-publications.index',
            'visible' => in_array($role, ['admin', 'koordinator_kp'], true),
        ],
        [
            'label' => 'Peserta belum berkelompok',
            'value' => (int) ($pkpaEnrollmentStats['peserta_belum_berkelompok'] ?? 0),
            'route' => 'management.pkpa-enrollments.index',
            'visible' => in_array($role, ['admin', 'koordinator_kp'], true),
        ],
        [
            'label' => 'Sinkronisasi Core peserta bermasalah',
            'value' => (int) ($pkpaEnrollmentStats['sync_core_bermasalah'] ?? 0),
            'route' => 'management.pkpa-enrollments.index',
            'visible' => in_array($role, ['admin', 'koordinator_kp'], true),
        ],
        [
            'label' => 'Tempat PKPA tanpa ketersediaan',
            'value' => (int) ($pkpaPlacementReadinessStats['tempat_tanpa_availability'] ?? 0),
            'route' => 'management.pkpa-program-sites.index',
            'visible' => in_array($role, ['admin', 'koordinator_kp'], true),
        ],
        [
            'label' => 'Pembimbing PKPA perlu sinkronisasi',
            'value' => (int) ($pkpaPlacementReadinessStats['pembimbing_perlu_sync'] ?? 0),
            'route' => 'management.pkpa-internal-supervisors.index',
            'visible' => in_array($role, ['admin', 'koordinator_kp'], true),
        ],
        [
            'label' => 'Pendaftaran menunggu verifikasi',
            'value' => (int) ($registrationStats['pending'] ?? 0),
            'route' => 'management.pkpa-registrations.index',
            'visible' => in_array($role, ['admin', 'koordinator_kp'], true),
        ],
        [
            'label' => 'Penempatan menunggu pembimbing',
            'value' => (int) ($assignmentStats['waiting'] ?? 0),
            'route' => 'management.pkpa-assignments.index',
            'visible' => in_array($role, ['admin', 'koordinator_kp'], true),
        ],
        [
            'label' => 'Logbook menunggu validasi',
            'value' => (int) ($logbookStats['menunggu_validasi'] ?? 0),
            'route' => $role === 'pembimbing_lapangan' ? 'field-supervisor.pkpa-journals.index' : 'management.pkpa-logbook-monitoring.index',
            'visible' => in_array($role, ['admin', 'koordinator_kp', 'pembimbing_lapangan'], true),
        ],
        [
            'label' => 'Laporan menunggu pemeriksaan',
            'value' => (int) ($finalReportStats['menunggu_review'] ?? 0),
            'route' => $role === 'pembimbing_dalam' ? 'internal-supervisor.pkpa-final-reports.index' : 'management.pkpa-final-reports.index',
            'visible' => in_array($role, ['admin', 'koordinator_kp', 'pembimbing_dalam'], true),
        ],
        [
            'label' => 'Ujian terjadwal',
            'value' => (int) ($examStats['sidang_terjadwal'] ?? $examStats['sidang_mendatang'] ?? $examStats['dijadwalkan'] ?? 0),
            'route' => $activeRole === 'penguji' ? 'examiner.pkpa-exams.index' : ($activeRole === 'pembimbing_dalam' ? 'internal-supervisor.pkpa-exams.index' : 'management.pkpa-exams.index'),
            'visible' => in_array($role, ['admin', 'koordinator_kp', 'pembimbing_dalam', 'penguji'], true),
        ],
        [
            'label' => 'Nilai belum dikirim',
            'value' => (int) ($scoreStats['belum_submit'] ?? $scoreStats['sidang_belum_submit'] ?? 0),
            'route' => $role === 'penguji' ? 'examiner.assessments.index' : ($role === 'pembimbing_lapangan' ? 'field-supervisor.assessments.index' : 'internal-supervisor.assessments.index'),
            'visible' => in_array($role, ['pembimbing_dalam', 'pembimbing_lapangan', 'penguji'], true),
        ],
    ])->filter(fn ($item) => $item['visible'])->values();

    $urgentItems = $priorityItems->filter(fn ($item) => $item['value'] > 0)->values();

    $toneClasses = [
        'sky' => ['text' => 'text-sky-700', 'bg' => 'bg-sky-50', 'ring' => 'ring-sky-100'],
        'indigo' => ['text' => 'text-indigo-700', 'bg' => 'bg-indigo-50', 'ring' => 'ring-indigo-100'],
        'cyan' => ['text' => 'text-cyan-700', 'bg' => 'bg-cyan-50', 'ring' => 'ring-cyan-100'],
        'teal' => ['text' => 'text-teal-700', 'bg' => 'bg-teal-50', 'ring' => 'ring-teal-100'],
        'emerald' => ['text' => 'text-emerald-700', 'bg' => 'bg-emerald-50', 'ring' => 'ring-emerald-100'],
        'amber' => ['text' => 'text-amber-700', 'bg' => 'bg-amber-50', 'ring' => 'ring-amber-100'],
        'violet' => ['text' => 'text-violet-700', 'bg' => 'bg-violet-50', 'ring' => 'ring-violet-100'],
        'rose' => ['text' => 'text-rose-700', 'bg' => 'bg-rose-50', 'ring' => 'ring-rose-100'],
    ];
@endphp

<div class="space-y-4 sm:space-y-5">
    <section class="overflow-hidden rounded-[1.35rem] border border-cyan-100 bg-white shadow-xl shadow-cyan-950/10 sm:rounded-3xl">
        <div class="grid gap-0 xl:grid-cols-[minmax(0,1fr)_360px]">
            <div class="border-b border-cyan-100 bg-linear-to-br from-white via-white to-cyan-50/50 p-4 sm:p-5 md:p-6 lg:p-7 xl:border-b-0 xl:border-r">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                    <div class="flex min-w-0 items-start gap-4">
                        <x-ui.avatar :user="$user" size="lg" class="ring-4 ring-white shadow-sm" />
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-md bg-cyan-50 px-2.5 py-1 text-[11px] font-black uppercase tracking-widest text-cyan-700 ring-1 ring-cyan-100">{{ $roleData['label'] }}</span>
                                <span class="rounded-md {{ $user->profile_completed ? 'bg-emerald-50 text-emerald-700 ring-emerald-100' : 'bg-amber-50 text-amber-700 ring-amber-100' }} px-2.5 py-1 text-[11px] font-bold ring-1">
                                    {{ $user->profile_completed ? 'Profil lengkap' : 'Profil perlu dilengkapi' }}
                                </span>
                            </div>
                            <h2 class="mt-3 break-words text-[1.65rem] font-black leading-tight tracking-tight text-slate-950 sm:text-3xl">Selamat datang, {{ $firstName }}</h2>
                            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">{{ $heroDescription }}</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-2 sm:flex lg:shrink-0">
                        <a href="{{ route('profile.show') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-700 shadow-sm transition hover:bg-slate-50">Profil</a>
                        @if($features)
                            @php
                                $firstFeatureRoute = $featureRoutes[$features[0]] ?? null;
                            @endphp
                            @if($firstFeatureRoute && Route::has($firstFeatureRoute))
                                <a href="{{ route($firstFeatureRoute) }}" class="inline-flex items-center justify-center rounded-lg bg-cyan-700 px-3 py-2 text-xs font-bold text-white shadow-sm transition hover:bg-cyan-800">Buka Modul</a>
                            @endif
                        @endif
                    </div>
                </div>

                <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach($primaryStats as $stat)
                        @php
                            $tone = $toneClasses[$stat['tone']] ?? $toneClasses['sky'];
                        @endphp
                        <div class="rounded-2xl bg-white/90 p-4 shadow-sm ring-1 {{ $tone['ring'] }}">
                            <p class="text-[11px] font-black uppercase tracking-widest text-slate-500">{{ $stat['label'] }}</p>
                            <p class="mt-2 min-h-14 break-words text-[1.45rem] font-black leading-tight {{ $tone['text'] }} sm:text-2xl">{{ $dashboardValue($stat['value']) }}</p>
                            <p class="mt-1 line-clamp-2 text-xs leading-snug text-slate-500">{{ $stat['section'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>

            <aside class="bg-slate-50/90 p-4 sm:p-5 md:p-6 lg:p-7">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-[11px] font-black uppercase tracking-widest text-slate-500">Prioritas Hari Ini</p>
                        <h3 class="mt-1 text-lg font-black text-slate-950">Antrian kerja</h3>
                    </div>
                    <span class="rounded-md {{ $urgentItems->isEmpty() ? 'bg-emerald-50 text-emerald-700 ring-emerald-100' : 'bg-amber-50 text-amber-700 ring-amber-100' }} px-2.5 py-1 text-xs font-black ring-1">
                        {{ $urgentItems->isEmpty() ? 'Tenang' : $urgentItems->count().' aktif' }}
                    </span>
                </div>

                <div class="mt-4 space-y-2">
                    @forelse($urgentItems as $item)
                        @php
                            $href = Route::has($item['route']) ? route($item['route']) : '#';
                        @endphp
                        <a href="{{ $href }}" class="flex items-center justify-between gap-3 rounded-lg bg-white px-3 py-3 text-sm shadow-sm ring-1 ring-slate-200 transition hover:ring-cyan-200">
                            <span class="font-bold text-slate-700">{{ $item['label'] }}</span>
                            <span class="rounded-md bg-cyan-700 px-2 py-1 text-xs font-black text-white">{{ $item['value'] }}</span>
                        </a>
                    @empty
                        <div class="rounded-lg border border-dashed border-emerald-200 bg-white px-4 py-5">
                            <div class="flex gap-3">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700 ring-1 ring-emerald-100">
                                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 5.29a1 1 0 010 1.42l-7.25 7.25a1 1 0 01-1.42 0L3.29 9.216a1 1 0 111.42-1.42l4.034 4.035 6.54-6.54a1 1 0 011.42 0z" clip-rule="evenodd"/></svg>
                                </span>
                                <div>
                                    <p class="font-bold text-slate-800">Tidak ada antrian mendesak.</p>
                                    <p class="mt-1 text-xs leading-5 text-slate-500">Gunakan modul akademik di bawah untuk membuka pekerjaan rutin.</p>
                                </div>
                            </div>
                        </div>
                    @endforelse
                </div>
            </aside>
        </div>
    </section>

    @if(! $user->profile_completed || $user->must_change_password)
        <section class="grid gap-3 md:grid-cols-2">
            @if(! $user->profile_completed)
                <a href="{{ route('profile.edit') }}" class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 shadow-sm transition hover:bg-amber-100">
                    <span class="font-black">Profil belum lengkap.</span>
                    <span class="ml-1 underline">Lengkapi profil</span>
                </a>
            @endif
            @if($user->must_change_password)
                <div class="rounded-lg border border-cyan-200 bg-cyan-50 px-4 py-3 text-sm text-cyan-900 shadow-sm">
                    <span class="font-black">Password awal perlu diganti.</span>
                    <span class="ml-1">Buka menu profil untuk memperbarui password.</span>
                </div>
            @endif
        </section>
    @endif

    <section class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
        <div class="rounded-[1.35rem] border border-slate-200 bg-white p-4 shadow-sm sm:rounded-3xl sm:p-5">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-[11px] font-black uppercase tracking-widest text-cyan-700">Alur PKPA</p>
                    <h2 class="mt-1 text-lg font-black text-slate-950">Tahapan program</h2>
                </div>
                <p class="text-xs text-slate-500">Ringkasan alur kerja yang paling relevan untuk peran aktif Anda.</p>
            </div>
            <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                @foreach($flowSteps as [$number, $label, $description])
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-cyan-700 text-xs font-black text-white">{{ $number }}</span>
                        <p class="mt-3 font-black text-slate-900">{{ $label }}</p>
                        <p class="mt-1 text-xs leading-5 text-slate-500">{{ $description }}</p>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="rounded-[1.35rem] border border-slate-200 bg-white p-4 shadow-sm sm:rounded-3xl sm:p-5">
            <p class="text-[11px] font-black uppercase tracking-widest text-slate-500">Status Akun</p>
            <div class="mt-4 space-y-3 text-sm">
                <div class="flex min-w-0 items-center justify-between gap-3">
                    <span class="text-slate-600">Peran aktif</span>
                    <span class="min-w-0 break-words text-right font-black text-slate-950">{{ $roleData['label'] }}</span>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <span class="text-slate-600">Akun</span>
                    <span class="rounded-md bg-emerald-50 px-2 py-1 text-xs font-black text-emerald-700 ring-1 ring-emerald-100">Aktif</span>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <span class="text-slate-600">Profil akademik</span>
                    <span class="rounded-md {{ $user->profile_completed ? 'bg-emerald-50 text-emerald-700 ring-emerald-100' : 'bg-amber-50 text-amber-700 ring-amber-100' }} px-2 py-1 text-xs font-black ring-1">
                        {{ $user->profile_completed ? 'Lengkap' : 'Perlu cek' }}
                    </span>
                </div>
            </div>
        </div>
    </section>

    @if($studentRegistration)
        <section class="rounded-[1.35rem] border border-slate-200 bg-white p-4 shadow-sm sm:rounded-3xl sm:p-5">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="text-[11px] font-black uppercase tracking-widest text-cyan-700">Status Mahasiswa</p>
                    <h2 class="mt-1 text-lg font-black text-slate-950">Status Administrasi PKPA</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ $studentRegistration->period->name ?? '-' }}</p>
                </div>
                <span class="rounded-md {{ $studentRegistration->statusBadgeClass() }} px-3 py-1 text-xs font-black">{{ $studentRegistration->statusLabel() }}</span>
            </div>
            <div class="mt-5 grid gap-3 md:grid-cols-3">
                <div class="rounded-2xl bg-slate-50 p-4 ring-1 ring-slate-100"><p class="text-xs font-bold text-slate-500">Kemajuan Berkas</p><p class="mt-2 text-2xl font-black text-slate-950">{{ $studentRegistration->progressPercentage() }}%</p></div>
                <div class="rounded-2xl bg-slate-50 p-4 ring-1 ring-slate-100"><p class="text-xs font-bold text-slate-500">Verifikasi</p><p class="mt-2 font-black text-slate-950">{{ $studentRegistration->isVerified() ? 'Terverifikasi' : 'Belum selesai' }}</p></div>
                <div class="rounded-2xl bg-slate-50 p-4 ring-1 ring-slate-100"><p class="text-xs font-bold text-slate-500">Status Penempatan</p><p class="mt-2 font-black text-slate-950">{{ $studentRegistration->selectionStatusLabel() }}</p>@if($studentRegistration->activePlaceSelection)<p class="mt-1 text-xs text-slate-500">{{ $studentRegistration->activePlaceSelection->place->name }}</p>@endif</div>
            </div>
        </section>
    @endif

    <section>
        <div class="mb-3 flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="text-lg font-black text-slate-950">Modul Akademik</h2>
                <p class="text-sm text-slate-500">Akses cepat sesuai peran aktif.</p>
            </div>
        </div>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($features as $feature)
                @php
                    $routeName = $featureRoutes[$feature] ?? null;
                    $href = $routeName && Route::has($routeName) ? route($routeName) : null;
                @endphp
                @if($href)
                    <a href="{{ $href }}" class="group rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-cyan-200 hover:shadow-md">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h3 class="font-black text-slate-950 group-hover:text-cyan-800">{{ $feature }}</h3>
                                <p class="mt-1 text-sm leading-6 text-slate-500">{{ $featureDescriptions[$feature] ?? 'Modul kerja sesuai peran Anda.' }}</p>
                            </div>
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-cyan-50 text-cyan-700 ring-1 ring-cyan-100 transition group-hover:bg-cyan-700 group-hover:text-white">
                                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5 10a1 1 0 011-1h5.586L9.293 6.707a1 1 0 111.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414-1.414L11.586 11H6a1 1 0 01-1-1z" clip-rule="evenodd"/></svg>
                            </span>
                        </div>
                    </a>
                @else
                    <div class="rounded-2xl border border-dashed border-slate-200 bg-white p-4">
                        <h3 class="font-black text-slate-700">{{ $feature }}</h3>
                        <p class="mt-1 text-sm leading-6 text-slate-500">{{ $featureDescriptions[$feature] ?? 'Modul ini sedang disiapkan.' }}</p>
                        <span class="mt-3 inline-flex rounded-md bg-slate-100 px-2.5 py-1 text-xs font-black text-slate-500">Disiapkan</span>
                    </div>
                @endif
            @endforeach
        </div>
    </section>

    <section class="space-y-3">
        @foreach($summarySections as $section)
            @php
                $tone = $toneClasses[$section['tone']] ?? $toneClasses['sky'];
            @endphp
            <div class="rounded-[1.35rem] border border-slate-200 bg-white p-4 shadow-sm sm:rounded-3xl sm:p-5">
                <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h2 class="text-lg font-black text-slate-950">{{ $section['title'] }}</h2>
                        <p class="text-sm text-slate-500">{{ $section['description'] }}</p>
                    </div>
                    <span class="rounded-md {{ $tone['bg'] }} {{ $tone['text'] }} px-2.5 py-1 text-[11px] font-black uppercase tracking-widest ring-1 {{ $tone['ring'] }}">Aktif</span>
                </div>
                <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-5">
                    @foreach($section['stats'] as $label => $value)
                        <div class="rounded-2xl bg-slate-50 p-4 ring-1 ring-slate-100">
                            <p class="text-[11px] font-black uppercase tracking-widest text-slate-500">{{ $formatLabel($label) }}</p>
                            <p class="mt-2 min-h-8 break-words text-xl font-black leading-tight {{ $tone['text'] }} sm:text-2xl">{{ $dashboardValue($value) }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </section>
</div>
@endsection
