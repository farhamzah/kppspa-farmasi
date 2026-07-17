<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => 'mahasiswa', 'label' => 'Mahasiswa', 'description' => 'Akses mahasiswa peserta PKPA.'],
            ['name' => 'admin', 'label' => 'Admin', 'description' => 'Akses administrasi sistem dan data master.'],
            ['name' => 'koordinator_kp', 'label' => 'Koordinator PKPA', 'description' => 'Akses koordinasi program, wahana, tempat praktik, pembimbing, penguji, dan nilai PKPA.'],
            ['name' => 'pembimbing_dalam', 'label' => 'Pembimbing Dalam / Dosen', 'description' => 'Akses dosen pembimbing internal.'],
            ['name' => 'pembimbing_lapangan', 'label' => 'Pembimbing Luar / Lapangan', 'description' => 'Akses pembimbing dari tempat kerja praktek.'],
            ['name' => 'penguji', 'label' => 'Penguji', 'description' => 'Akses penguji sidang kerja praktek.'],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(['name' => $role['name']], $role);
        }
    }
}
