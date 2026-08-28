<?php

namespace App\Support;

class RoleDashboard
{
    public const ROLES = [
        'mahasiswa' => [
            'label' => 'Mahasiswa',
            'route' => 'mahasiswa.dashboard',
            'path' => '/mahasiswa/dashboard',
            'menu' => ['Dashboard', 'Profil Saya', 'Pendaftaran PKPA', 'Berkas PKPA', 'Pembekalan', 'PKPA Saya', 'Penempatan PKPA', 'Rotasi PKPA', 'Logbook PKPA', 'Akademik Rotasi', 'Laporan Akhir', 'Portofolio PKPA', 'Ujian', 'Nilai PKPA', 'Hasil Akhir PKPA', 'Dokumen PKPA'],
            'features' => ['Pendaftaran PKPA', 'Berkas Persyaratan', 'Pembekalan', 'PKPA Saya', 'Logbook', 'Akademik Rotasi', 'Portofolio PKPA', 'Ujian', 'Nilai PKPA', 'Hasil Akhir PKPA'],
        ],
        'admin' => [
            'label' => 'Admin',
            'route' => 'admin.dashboard',
            'path' => '/admin/dashboard',
            'menu' => ['Dashboard', 'Profil Saya', 'Program PKPA', 'Wahana PKPA', 'Tempat Praktik', 'Tempat Tersedia', 'Kapasitas Tempat', 'Log Kapasitas', 'Pembimbing Dalam', 'Preseptor', 'Persyaratan Dokumen', 'Peserta PKPA', 'Kelompok PKPA', 'Verifikasi Pendaftaran', 'Pembekalan', 'Hasil Pembekalan', 'Kesiapan Penempatan', 'Penyusunan Penempatan', 'Publikasi Penempatan', 'Penempatan PKPA', 'Log Penempatan', 'Operasional Rotasi', 'Panduan Kompetensi', 'Akademik Rotasi', 'Pemantauan Logbook', 'Log Aktivitas Logbook', 'Pemantauan Laporan', 'Log Laporan', 'Portofolio PKPA', 'Pengajuan Ujian', 'Jadwal Ujian', 'Log Ujian', 'Komponen Penilaian', 'Penilaian PKPA', 'Pemantauan Nilai', 'Log Nilai', 'Penyelesaian PKPA', 'Dokumen PKPA', 'Rekap PKPA', 'Pelaporan Analitik', 'Pemeriksaan Integrasi'],
            'features' => ['Program PKPA', 'Wahana PKPA', 'Tempat Tersedia', 'Pembimbing Dalam', 'Preseptor', 'Peserta PKPA', 'Kelompok PKPA', 'Kesiapan Penempatan', 'Penyusunan Penempatan', 'Publikasi Penempatan'],
        ],
        'koordinator_kp' => [
            'label' => 'Koordinator PKPA',
            'route' => 'koordinator.dashboard',
            'path' => '/koordinator/dashboard',
            'menu' => ['Dashboard', 'Profil Saya', 'Program PKPA', 'Wahana PKPA', 'Tempat Praktik', 'Tempat Tersedia', 'Kapasitas Tempat', 'Log Kapasitas', 'Pembimbing Dalam', 'Preseptor', 'Persyaratan Dokumen', 'Peserta PKPA', 'Kelompok PKPA', 'Verifikasi Pendaftaran', 'Pembekalan', 'Hasil Pembekalan', 'Kesiapan Penempatan', 'Penyusunan Penempatan', 'Publikasi Penempatan', 'Penempatan PKPA', 'Log Penempatan', 'Operasional Rotasi', 'Panduan Kompetensi', 'Akademik Rotasi', 'Pemantauan Logbook', 'Log Aktivitas Logbook', 'Pemantauan Laporan', 'Log Laporan', 'Portofolio PKPA', 'Pengajuan Ujian', 'Jadwal Ujian', 'Log Ujian', 'Komponen Penilaian', 'Penilaian PKPA', 'Pemantauan Nilai', 'Log Nilai', 'Penyelesaian PKPA', 'Dokumen PKPA', 'Rekap PKPA', 'Pelaporan Analitik', 'Pemeriksaan Integrasi'],
            'features' => ['Program PKPA', 'Wahana PKPA', 'Tempat Tersedia', 'Pembimbing Dalam', 'Preseptor', 'Peserta PKPA', 'Kelompok PKPA', 'Kesiapan Penempatan', 'Penyusunan Penempatan', 'Publikasi Penempatan'],
        ],
        'pembimbing_dalam' => [
            'label' => 'Pembimbing Dalam / Dosen',
            'route' => 'pembimbing-dalam.dashboard',
            'path' => '/pembimbing-dalam/dashboard',
            'menu' => ['Dashboard', 'Profil Saya', 'Jadwal PKPA', 'Mahasiswa Bimbingan', 'Pemantauan PKPA', 'Logbook Mahasiswa', 'Akademik PKPA', 'Capaian Kompetensi', 'Pemeriksaan Laporan', 'Review Portofolio', 'Jadwal Sidang', 'Penilaian Pembimbing', 'Penilaian PKPA'],
            'features' => ['Jadwal PKPA', 'Mahasiswa Bimbingan', 'Pemantauan PKPA', 'Logbook Mahasiswa', 'Akademik PKPA', 'Review Portofolio', 'Jadwal Sidang', 'Penilaian Pembimbing', 'Penilaian PKPA'],
        ],
        'pembimbing_lapangan' => [
            'label' => 'Preseptor',
            'route' => 'pembimbing-lapangan.dashboard',
            'path' => '/pembimbing-lapangan/dashboard',
            'menu' => ['Dashboard', 'Profil Saya', 'Jadwal PKPA', 'Mahasiswa PKPA', 'Operasional PKPA', 'Validasi Logbook', 'Akademik PKPA', 'Daftar Kompetensi', 'Pemeriksaan Laporan', 'Review Portofolio', 'Penilaian Lapangan', 'Penilaian PKPA'],
            'features' => ['Jadwal PKPA', 'Mahasiswa PKPA', 'Operasional PKPA', 'Validasi Logbook', 'Akademik PKPA', 'Review Portofolio', 'Penilaian Lapangan', 'Penilaian PKPA'],
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
