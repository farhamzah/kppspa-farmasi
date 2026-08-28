<?php

namespace App\Support;

class RoleDashboard
{
    public const ROLES = [
        'mahasiswa' => [
            'label' => 'Mahasiswa',
            'route' => 'mahasiswa.dashboard',
            'path' => '/mahasiswa/dashboard',
            'menu' => ['Dashboard', 'Profil Saya', 'Pendaftaran PKPA', 'Berkas PKPA', 'Pembekalan', 'PKPA Saya', 'Penempatan PKPA', 'Rotasi PKPA', 'Logbook PKPA', 'Portofolio PKPA', 'Akademik Rotasi', 'Laporan Akhir', 'Ujian', 'Nilai PKPA', 'Hasil Akhir PKPA', 'Dokumen PKPA'],
            'features' => ['Pembekalan', 'PKPA Saya', 'Penempatan PKPA', 'Logbook', 'Portofolio PKPA', 'Akademik Rotasi', 'Ujian', 'Nilai PKPA', 'Hasil Akhir PKPA'],
        ],
        'admin' => [
            'label' => 'Admin',
            'route' => 'admin.dashboard',
            'path' => '/admin/dashboard',
            'menu' => ['Dashboard', 'Profil Saya', 'Program PKPA', 'Wahana PKPA', 'Tempat Praktik', 'Tempat Tersedia', 'Kapasitas Tempat', 'Peserta PKPA', 'Kelompok PKPA', 'Pembimbing Dalam', 'Preseptor', 'Persyaratan Dokumen', 'Verifikasi Pendaftaran', 'Pembekalan', 'Hasil Pembekalan', 'Kesiapan Penempatan', 'Penyusunan Penempatan', 'Publikasi Penempatan', 'Penempatan PKPA', 'Operasional Rotasi', 'Akademik Rotasi', 'Panduan Kompetensi', 'Portofolio PKPA', 'Pengajuan Ujian', 'Jadwal Ujian', 'Komponen Penilaian', 'Penilaian PKPA', 'Pemantauan Nilai', 'Penyelesaian PKPA', 'Dokumen PKPA', 'Rekap PKPA', 'Pelaporan Analitik'],
            'features' => ['Program PKPA', 'Wahana PKPA', 'Tempat Praktik', 'Tempat Tersedia', 'Peserta PKPA', 'Pembimbing Dalam', 'Preseptor', 'Kesiapan Penempatan', 'Penyusunan Penempatan', 'Publikasi Penempatan'],
        ],
        'koordinator_kp' => [
            'label' => 'Koordinator PKPA',
            'route' => 'koordinator.dashboard',
            'path' => '/koordinator/dashboard',
            'menu' => ['Dashboard', 'Profil Saya', 'Program PKPA', 'Wahana PKPA', 'Tempat Praktik', 'Tempat Tersedia', 'Kapasitas Tempat', 'Peserta PKPA', 'Kelompok PKPA', 'Pembimbing Dalam', 'Preseptor', 'Persyaratan Dokumen', 'Verifikasi Pendaftaran', 'Pembekalan', 'Hasil Pembekalan', 'Kesiapan Penempatan', 'Penyusunan Penempatan', 'Publikasi Penempatan', 'Penempatan PKPA', 'Operasional Rotasi', 'Akademik Rotasi', 'Panduan Kompetensi', 'Portofolio PKPA', 'Pengajuan Ujian', 'Jadwal Ujian', 'Komponen Penilaian', 'Penilaian PKPA', 'Pemantauan Nilai', 'Penyelesaian PKPA', 'Dokumen PKPA', 'Rekap PKPA', 'Pelaporan Analitik'],
            'features' => ['Program PKPA', 'Wahana PKPA', 'Tempat Praktik', 'Tempat Tersedia', 'Peserta PKPA', 'Pembimbing Dalam', 'Preseptor', 'Kesiapan Penempatan', 'Penyusunan Penempatan', 'Publikasi Penempatan'],
        ],
        'pembimbing_dalam' => [
            'label' => 'Pembimbing Dalam',
            'route' => 'pembimbing-dalam.dashboard',
            'path' => '/pembimbing-dalam/dashboard',
            'menu' => ['Dashboard', 'Profil Saya', 'Jadwal PKPA', 'Mahasiswa Bimbingan', 'Pemantauan PKPA', 'Logbook Mahasiswa', 'Akademik PKPA', 'Review Portofolio', 'Pemeriksaan Laporan', 'Penilaian PKPA', 'Jadwal Sidang'],
            'features' => ['Jadwal PKPA', 'Mahasiswa Bimbingan', 'Pemantauan PKPA', 'Logbook Mahasiswa', 'Akademik PKPA', 'Review Portofolio', 'Pemeriksaan Laporan', 'Penilaian PKPA', 'Jadwal Sidang'],
        ],
        'pembimbing_lapangan' => [
            'label' => 'Preseptor',
            'route' => 'pembimbing-lapangan.dashboard',
            'path' => '/pembimbing-lapangan/dashboard',
            'menu' => ['Dashboard', 'Profil Saya', 'Jadwal PKPA', 'Mahasiswa PKPA', 'Operasional PKPA', 'Validasi Logbook', 'Akademik PKPA', 'Review Portofolio', 'Pemeriksaan Laporan', 'Penilaian Preseptor'],
            'features' => ['Jadwal PKPA', 'Mahasiswa PKPA', 'Operasional PKPA', 'Validasi Logbook', 'Akademik PKPA', 'Review Portofolio', 'Pemeriksaan Laporan', 'Penilaian Preseptor'],
        ],
        'penguji' => [
            'label' => 'Penguji',
            'route' => 'penguji.dashboard',
            'path' => '/penguji/dashboard',
            'menu' => ['Dashboard', 'Profil Saya', 'Jadwal Sidang', 'Penilaian Sidang'],
            'features' => ['Jadwal Sidang', 'Detail Mahasiswa Sidang', 'Penilaian Sidang'],
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
