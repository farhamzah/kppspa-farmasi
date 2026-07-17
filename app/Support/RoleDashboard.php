<?php

namespace App\Support;

class RoleDashboard
{
    public const ROLES = [
        'mahasiswa' => [
            'label' => 'Mahasiswa',
            'route' => 'mahasiswa.dashboard',
            'path' => '/mahasiswa/dashboard',
            'menu' => ['Dashboard', 'Profil Saya', 'Pendaftaran PKPA', 'Berkas PKPA', 'PKPA Saya', 'Rotasi PKPA', 'Akademik Rotasi', 'Nilai PKPA', 'Hasil Akhir PKPA', 'Dokumen PKPA', 'Penempatan PKPA', 'Logbook PKPA', 'Laporan Akhir', 'Ujian', 'Pembekalan', 'Nilai'],
            'features' => ['Pendaftaran PKPA', 'Berkas Persyaratan', 'PKPA Saya', 'Penempatan PKPA', 'Logbook', 'Laporan Akhir', 'Ujian', 'Pembekalan', 'Nilai'],
        ],
        'admin' => [
            'label' => 'Admin',
            'route' => 'admin.dashboard',
            'path' => '/admin/dashboard',
            'menu' => ['Dashboard', 'Profil Saya', 'Program PKPA', 'Wahana PKPA', 'Tempat Praktik', 'Tempat Tersedia', 'Pembimbing Dalam', 'Kesiapan Penempatan', 'Peserta PKPA', 'Kelompok PKPA', 'Penyusunan Penempatan', 'Publikasi Penempatan', 'Operasional Rotasi', 'Akademik Rotasi', 'Penilaian PKPA', 'Penyelesaian PKPA', 'Dokumen PKPA', 'Pelaporan Analytics', 'Kapasitas Tempat', 'Log Kapasitas', 'Persyaratan Dokumen', 'Verifikasi Pendaftaran', 'Penempatan PKPA', 'Log Penempatan', 'Panduan Kompetensi', 'Monitoring Logbook', 'Log Aktivitas Logbook', 'Monitoring Laporan', 'Log Laporan', 'Pengajuan Ujian', 'Jadwal Ujian', 'Log Ujian', 'Komponen Penilaian', 'Monitoring Nilai', 'Hasil Pembekalan', 'Log Nilai', 'Rekap PKPA', 'Review Integrasi'],
            'features' => ['Program PKPA', 'Peserta PKPA', 'Kelompok PKPA', 'Tempat Praktik', 'Tempat Tersedia', 'Kesiapan Penempatan', 'Rekap'],
        ],
        'koordinator_kp' => [
            'label' => 'Koordinator PKPA',
            'route' => 'koordinator.dashboard',
            'path' => '/koordinator/dashboard',
            'menu' => ['Dashboard', 'Profil Saya', 'Program PKPA', 'Wahana PKPA', 'Tempat Praktik', 'Tempat Tersedia', 'Pembimbing Dalam', 'Kesiapan Penempatan', 'Peserta PKPA', 'Kelompok PKPA', 'Penyusunan Penempatan', 'Publikasi Penempatan', 'Operasional Rotasi', 'Akademik Rotasi', 'Penilaian PKPA', 'Penyelesaian PKPA', 'Dokumen PKPA', 'Pelaporan Analytics', 'Kapasitas Tempat', 'Log Kapasitas', 'Persyaratan Dokumen', 'Verifikasi Pendaftaran', 'Penempatan PKPA', 'Log Penempatan', 'Panduan Kompetensi', 'Monitoring Logbook', 'Log Aktivitas Logbook', 'Monitoring Laporan', 'Log Laporan', 'Pengajuan Ujian', 'Jadwal Ujian', 'Log Ujian', 'Komponen Penilaian', 'Monitoring Nilai', 'Hasil Pembekalan', 'Log Nilai', 'Rekap PKPA', 'Review Integrasi'],
            'features' => ['Program PKPA', 'Peserta PKPA', 'Kelompok PKPA', 'Tempat Praktik', 'Tempat Tersedia', 'Kesiapan Penempatan', 'Finalisasi Nilai'],
        ],
        'pembimbing_dalam' => [
            'label' => 'Pembimbing Dalam / Dosen',
            'route' => 'pembimbing-dalam.dashboard',
            'path' => '/pembimbing-dalam/dashboard',
            'menu' => ['Dashboard', 'Profil Saya', 'Jadwal PKPA', 'Monitoring PKPA', 'Akademik PKPA', 'Penilaian PKPA', 'Mahasiswa Bimbingan', 'Capaian Kompetensi', 'Logbook Mahasiswa', 'Review Laporan', 'Jadwal Sidang', 'Penilaian Pembimbing'],
            'features' => ['Jadwal PKPA', 'Mahasiswa Bimbingan', 'Capaian Kompetensi', 'Logbook Mahasiswa', 'Review Laporan', 'Jadwal Sidang', 'Penilaian Pembimbing'],
        ],
        'pembimbing_lapangan' => [
            'label' => 'Pembimbing Luar / Lapangan',
            'route' => 'pembimbing-lapangan.dashboard',
            'path' => '/pembimbing-lapangan/dashboard',
            'menu' => ['Dashboard', 'Profil Saya', 'Jadwal PKPA', 'Operasional PKPA', 'Akademik PKPA', 'Penilaian PKPA', 'Mahasiswa KP', 'Checklist Kompetensi', 'Validasi Logbook', 'Review Laporan', 'Penilaian Lapangan'],
            'features' => ['Jadwal PKPA', 'Mahasiswa KP', 'Checklist Kompetensi', 'Validasi Logbook', 'Review Laporan', 'Catatan Lapangan', 'Penilaian Lapangan'],
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
        return $role && isset(self::ROLES[$role]) ? self::ROLES[$role]['label'] : 'Belum memilih role';
    }

    public static function dataFor(string $role): array
    {
        return self::ROLES[$role] ?? self::ROLES['mahasiswa'];
    }
}
