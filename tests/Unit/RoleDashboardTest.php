<?php

namespace Tests\Unit;

use App\Support\RoleDashboard;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RoleDashboardTest extends TestCase
{
    public static function managementRoles(): array
    {
        return [
            'admin' => ['admin'],
            'koordinator' => ['koordinator_kp'],
        ];
    }

    #[DataProvider('managementRoles')]
    public function test_management_menu_follows_the_pkpa_process_and_hides_technical_entries(string $role): void
    {
        $menu = RoleDashboard::dataFor($role)['menu'];

        $this->assertSame([
            'Dashboard',
            'Profil Saya',
            'Program PKPA',
            'Tempat Praktik',
            'Persyaratan Dokumen',
            'Peserta PKPA',
            'Verifikasi Pendaftaran',
            'Pembekalan',
            'Tempat Tersedia',
            'Kapasitas Tempat',
            'Pembimbing Dalam',
            'Preseptor',
            'Kesiapan Penempatan',
            'Penyusunan Penempatan',
            'Publikasi Penempatan',
            'Penempatan PKPA',
            'Pelaksanaan PKPA',
            'Panduan Kompetensi',
            'Portofolio PKPA',
            'Pengajuan Ujian',
            'Jadwal Ujian',
            'Penilaian PKPA',
            'Pemantauan Nilai',
            'Penyelesaian PKPA',
            'Dokumen PKPA',
            'Rekap & Laporan',
        ], $menu);

        $this->assertEmpty(array_intersect($menu, [
            'Wahana PKPA',
            'Kelompok PKPA',
            'Hasil Pembekalan',
            'Pengaturan Akademik',
            'Komponen Penilaian',
            'Pelaporan Analitik',
        ]));
    }
}
