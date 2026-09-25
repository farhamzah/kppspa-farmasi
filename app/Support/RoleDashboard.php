<?php

namespace App\Support;

class RoleDashboard
{
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
            'menu' => ['Dashboard', 'Profil Saya', 'Program PKPA', 'Wahana PKPA', 'Tempat Praktik', 'Tempat Tersedia', 'Kapasitas Tempat', 'Peserta PKPA', 'Kelompok PKPA', 'Pembimbing Dalam', 'Preseptor', 'Persyaratan Dokumen', 'Verifikasi Pendaftaran', 'Pembekalan', 'Hasil Pembekalan', 'Kesiapan Penempatan', 'Penyusunan Penempatan', 'Publikasi Penempatan', 'Penempatan PKPA', 'Pelaksanaan PKPA', 'Pengaturan Akademik', 'Panduan Kompetensi', 'Portofolio PKPA', 'Pengajuan Ujian', 'Jadwal Ujian', 'Komponen Penilaian', 'Penilaian PKPA', 'Pemantauan Nilai', 'Penyelesaian PKPA', 'Dokumen PKPA', 'Pelaporan Analitik', 'Rekap & Laporan'],
            'features' => ['Program PKPA', 'Wahana PKPA', 'Tempat Praktik', 'Tempat Tersedia', 'Peserta PKPA', 'Pembimbing Dalam', 'Preseptor', 'Kesiapan Penempatan', 'Penyusunan Penempatan', 'Publikasi Penempatan'],
        ],
        'koordinator_kp' => [
            'label' => 'Koordinator PKPA',
            'route' => 'koordinator.dashboard',
            'path' => '/koordinator/dashboard',
            'menu' => ['Dashboard', 'Profil Saya', 'Program PKPA', 'Wahana PKPA', 'Tempat Praktik', 'Tempat Tersedia', 'Kapasitas Tempat', 'Peserta PKPA', 'Kelompok PKPA', 'Pembimbing Dalam', 'Preseptor', 'Persyaratan Dokumen', 'Verifikasi Pendaftaran', 'Pembekalan', 'Hasil Pembekalan', 'Kesiapan Penempatan', 'Penyusunan Penempatan', 'Publikasi Penempatan', 'Penempatan PKPA', 'Pelaksanaan PKPA', 'Pengaturan Akademik', 'Panduan Kompetensi', 'Portofolio PKPA', 'Pengajuan Ujian', 'Jadwal Ujian', 'Komponen Penilaian', 'Penilaian PKPA', 'Pemantauan Nilai', 'Penyelesaian PKPA', 'Dokumen PKPA', 'Pelaporan Analitik', 'Rekap & Laporan'],
            'features' => ['Program PKPA', 'Wahana PKPA', 'Tempat Praktik', 'Tempat Tersedia', 'Peserta PKPA', 'Pembimbing Dalam', 'Preseptor', 'Kesiapan Penempatan', 'Penyusunan Penempatan', 'Publikasi Penempatan'],
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
