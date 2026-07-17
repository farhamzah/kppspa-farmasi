<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::updateOrCreate(
            ['email' => 'admin@sikp.test'],
            [
                'name' => 'Administrator',
                'password' => Hash::make('password'),
                'status' => 'active',
                'must_change_password' => true,
                'profile_completed' => false,
            ]
        );

        $adminRole = Role::where('name', 'admin')->firstOrFail();
        $admin->roles()->syncWithoutDetaching([$adminRole->id]);

        $testingSuperAdminEmail = 'farhamzah@ubpkarawang.ac.id';
        $testingSuperAdmin = User::where('email', $testingSuperAdminEmail)->first();
        $testingSuperAdminData = [
            'name' => 'Farhamzah',
            'status' => 'active',
            'must_change_password' => false,
            'profile_completed' => true,
        ];

        $configuredTestingPassword = env('MY_PSPA_TEST_SUPERADMIN_PASSWORD');
        if (filled($configuredTestingPassword)) {
            $testingSuperAdminData['password'] = Hash::make($configuredTestingPassword);
        } elseif (! $testingSuperAdmin) {
            $testingSuperAdminData['password'] = Hash::make('password');
        }

        $testingSuperAdmin = User::updateOrCreate(
            ['email' => $testingSuperAdminEmail],
            $testingSuperAdminData
        );

        $testingRoleIds = Role::whereIn('name', [
            'admin',
            'koordinator_kp',
            'pembimbing_dalam',
            'pembimbing_lapangan',
            'penguji',
            'mahasiswa',
        ])->pluck('id');

        $testingSuperAdmin->roles()->syncWithoutDetaching($testingRoleIds);

        $testingSuperAdmin->student()->updateOrCreate([], [
            'nim' => 'PSPA-TEST-001',
            'study_program' => 'Farmasi',
            'semester' => 1,
            'class_name' => 'Testing',
        ]);

        $testingSuperAdmin->lecturer()->updateOrCreate([], [
            'nidn_nip' => 'UBP-PSPA-TEST',
            'employee_number' => 'PSPA-ADMIN-001',
            'study_program' => 'Farmasi',
            'department' => 'Program Studi Profesi Apoteker',
            'expertise' => 'Administrasi PKPA',
        ]);

        $testingSuperAdmin->fieldSupervisor()->updateOrCreate([], [
            'institution_name' => 'Wahana Testing PKPA',
            'position' => 'Pembimbing Lapangan Testing',
        ]);
    }
}
