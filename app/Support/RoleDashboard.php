<?php

namespace App\Support;

class RoleDashboard
{
    public const ROLES = [
        'mahasiswa' => [
            'label' => 'Mahasiswa',
            'route' => 'mahasiswa.dashboard',
            'path' => '/mahasiswa/dashboard',
            'menu' => ['Dashboard', 'Profil Saya', 'Pendaftaran KP', 'Berkas KP', 'Pemilihan Tempat KP', 'Penempatan KP', 'Logbook KP', 'Laporan Akhir', 'Sidang', 'Pre/Post Test', 'Nilai'],
            'features' => ['Pendaftaran KP', 'Berkas Persyaratan', 'Pemilihan Tempat KP', 'Logbook', 'Laporan Akhir', 'Sidang', 'Pre/Post Test', 'Nilai'],
        ],
        'admin' => [
            'label' => 'Admin',
            'route' => 'admin.dashboard',
            'path' => '/admin/dashboard',
            'menu' => ['Dashboard', 'Profil Saya', 'Manajemen User', 'Import User', 'Riwayat Import', 'Periode KP', 'Tempat KP', 'Kuota Tempat KP', 'Log Kuota', 'Persyaratan Dokumen', 'Verifikasi Pendaftaran', 'Monitoring Pemilihan', 'Daftar Tunggu', 'Log Pemilihan', 'Penempatan KP', 'Log Penempatan', 'Panduan Kompetensi', 'Monitoring Logbook', 'Log Aktivitas Logbook', 'Monitoring Laporan', 'Log Laporan', 'Pengajuan Sidang', 'Jadwal Sidang', 'Log Sidang', 'Komponen Penilaian', 'Monitoring Nilai', 'Hasil Pre/Post Test', 'Log Nilai', 'Rekap KP', 'Review Integrasi'],
            'features' => ['Manajemen User', 'Import Excel', 'Periode KP', 'Tempat KP', 'Kuota Tempat KP', 'Verifikasi Berkas', 'Rekap'],
        ],
        'koordinator_kp' => [
            'label' => 'Koordinator KP',
            'route' => 'koordinator.dashboard',
            'path' => '/koordinator/dashboard',
            'menu' => ['Dashboard', 'Profil Saya', 'Periode KP', 'Tempat KP', 'Kuota Tempat KP', 'Log Kuota', 'Persyaratan Dokumen', 'Verifikasi Pendaftaran', 'Monitoring Pemilihan', 'Daftar Tunggu', 'Log Pemilihan', 'Penempatan KP', 'Log Penempatan', 'Panduan Kompetensi', 'Monitoring Logbook', 'Log Aktivitas Logbook', 'Monitoring Laporan', 'Log Laporan', 'Pengajuan Sidang', 'Jadwal Sidang', 'Log Sidang', 'Komponen Penilaian', 'Monitoring Nilai', 'Hasil Pre/Post Test', 'Log Nilai', 'Rekap KP', 'Review Integrasi'],
            'features' => ['Periode KP', 'Tempat KP', 'Kuota Tempat KP', 'Monitoring Pemilihan Tempat', 'Penentuan Pembimbing', 'Penentuan Penguji', 'Finalisasi Nilai'],
        ],
        'pembimbing_dalam' => [
            'label' => 'Pembimbing Dalam / Dosen',
            'route' => 'pembimbing-dalam.dashboard',
            'path' => '/pembimbing-dalam/dashboard',
            'menu' => ['Dashboard', 'Profil Saya', 'Mahasiswa Bimbingan', 'Capaian Kompetensi', 'Logbook Mahasiswa', 'Review Laporan', 'Jadwal Sidang', 'Penilaian Pembimbing'],
            'features' => ['Mahasiswa Bimbingan', 'Capaian Kompetensi', 'Logbook Mahasiswa', 'Review Laporan', 'Jadwal Sidang', 'Penilaian Pembimbing'],
        ],
        'pembimbing_lapangan' => [
            'label' => 'Pembimbing Luar / Lapangan',
            'route' => 'pembimbing-lapangan.dashboard',
            'path' => '/pembimbing-lapangan/dashboard',
            'menu' => ['Dashboard', 'Profil Saya', 'Mahasiswa KP', 'Checklist Kompetensi', 'Validasi Logbook', 'Review Laporan', 'Penilaian Lapangan'],
            'features' => ['Mahasiswa KP', 'Checklist Kompetensi', 'Validasi Logbook', 'Review Laporan', 'Catatan Lapangan', 'Penilaian Lapangan'],
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
