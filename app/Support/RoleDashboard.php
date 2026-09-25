<?php

namespace App\Support;

class RoleDashboard
{
    private const MANAGEMENT_MENU = [
        'Dashboard',
        'Profil Saya',

        // 1. Siapkan program dan master tempat praktik.
        'Program PKPA',
        'Tempat Praktik',

        // 2. Siapkan peserta dan administrasinya.
        'Persyaratan Dokumen',
        'Peserta PKPA',
        'Verifikasi Pendaftaran',
        'Pembekalan',

        // 3. Siapkan kapasitas, pembimbing, dan kesiapan jadwal.
        'Tempat Tersedia',
        'Kapasitas Tempat',
        'Pembimbing Dalam',
        'Preseptor',
        'Kesiapan Penempatan',

        // 4. Susun, terbitkan, dan tinjau hasil penempatan.
        'Penyusunan Penempatan',
        'Publikasi Penempatan',
        'Penempatan PKPA',

        // 5. Pantau pelaksanaan dan hasil akademik.
        'Pelaksanaan PKPA',
        'Panduan Kompetensi',
        'Portofolio PKPA',

        // 6. Kelola evaluasi hingga nilai akhir.
        'Pengajuan Ujian',
        'Jadwal Ujian',
        'Penilaian PKPA',
        'Pemantauan Nilai',

        // 7. Selesaikan program dan keluarkan laporan.
        'Penyelesaian PKPA',
        'Dokumen PKPA',
        'Rekap & Laporan',
    ];

    private const MANAGEMENT_FEATURES = [
        'Peserta PKPA',
        'Kesiapan Penempatan',
        'Penyusunan Penempatan',
        'Pelaksanaan PKPA',
        'Portofolio PKPA',
        'Penilaian PKPA',
        'Rekap & Laporan',
    ];

    public const ROLES = [
        'mahasiswa' => [
            'label' => 'Mahasiswa',
            'route' => 'mahasiswa.dashboard',
            'path' => '/mahasiswa/dashboard',
            'menu' => ['Dashboard', 'Profil Saya', 'Pendaftaran PKPA', 'Berkas PKPA', 'Pembekalan', 'Jadwal & Wahana', 'Presensi Harian', 'Logbook Harian', 'Portofolio', 'Bimbingan & Kompetensi', 'Ujian', 'Nilai PKPA', 'Hasil Akhir PKPA', 'Dokumen PKPA'],
            'features' => ['Pembekalan', 'Jadwal & Wahana', 'Presensi Harian', 'Logbook Harian', 'Portofolio', 'Bimbingan & Kompetensi', 'Ujian', 'Nilai PKPA', 'Hasil Akhir PKPA'],
        ],
        'admin' => [
            'label' => 'Admin',
            'route' => 'admin.dashboard',
            'path' => '/admin/dashboard',
            'menu' => self::MANAGEMENT_MENU,
            'features' => self::MANAGEMENT_FEATURES,
        ],
        'koordinator_kp' => [
            'label' => 'Koordinator PKPA',
            'route' => 'koordinator.dashboard',
            'path' => '/koordinator/dashboard',
            'menu' => self::MANAGEMENT_MENU,
            'features' => self::MANAGEMENT_FEATURES,
        ],
        'pembimbing_dalam' => [
            'label' => 'Pembimbing Dalam',
            'route' => 'pembimbing-dalam.dashboard',
            'path' => '/pembimbing-dalam/dashboard',
            'menu' => ['Dashboard', 'Profil Saya', 'Jadwal Bimbingan', 'Mahasiswa Bimbingan', 'Pemantauan Mahasiswa', 'Bimbingan & Kompetensi', 'Review Portofolio', 'Review Laporan Akhir', 'Penilaian Mahasiswa', 'Jadwal Ujian'],
            'features' => ['Jadwal Bimbingan', 'Mahasiswa Bimbingan', 'Pemantauan Mahasiswa', 'Bimbingan & Kompetensi', 'Review Portofolio', 'Review Laporan Akhir', 'Penilaian Mahasiswa', 'Jadwal Ujian'],
        ],
        'pembimbing_lapangan' => [
            'label' => 'Preseptor',
            'route' => 'pembimbing-lapangan.dashboard',
            'path' => '/pembimbing-lapangan/dashboard',
            'menu' => ['Dashboard', 'Profil Saya', 'Jadwal Mahasiswa', 'Mahasiswa Bimbingan', 'Pemantauan Mahasiswa', 'Bimbingan & Kompetensi', 'Review Portofolio', 'Review Laporan Akhir', 'Penilaian Mahasiswa'],
            'features' => ['Jadwal Mahasiswa', 'Mahasiswa Bimbingan', 'Pemantauan Mahasiswa', 'Bimbingan & Kompetensi', 'Review Portofolio', 'Review Laporan Akhir', 'Penilaian Mahasiswa'],
        ],
        'penguji' => [
            'label' => 'Penguji',
            'route' => 'penguji.dashboard',
            'path' => '/penguji/dashboard',
            'menu' => ['Dashboard', 'Profil Saya', 'Jadwal Ujian', 'Penilaian Ujian'],
            'features' => ['Jadwal Ujian', 'Detail Mahasiswa Ujian', 'Penilaian Ujian'],
        ],
    ];

    public static function routeFor(string $role): string
    {
        return self::ROLES[$role]['route'] ?? 'role.select';
    }

    public static function labelFor(?string $role): string
    {
        return $role && isset(self::ROLES[$role]) ? self::ROLES[$role]['label'] : 'Belum memilih peran';
    }

    public static function dataFor(string $role): array
    {
        return self::ROLES[$role] ?? self::ROLES['mahasiswa'];
    }
}
