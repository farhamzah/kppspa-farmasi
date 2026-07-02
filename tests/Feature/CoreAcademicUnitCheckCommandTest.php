<?php

namespace Tests\Feature;

use App\Models\Lecturer;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CoreAcademicUnitCheckCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.connections.core', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);

        DB::purge('core');

        foreach (['faculties', 'study_programs', 'departments'] as $tableName) {
            Schema::connection('core')->create($tableName, function ($table): void {
                $table->id();
                $table->string('name');
            });
        }
    }

    public function test_core_academic_unit_check_is_read_only_and_flags_faculty_as_department(): void
    {
        $studentUser = User::create([
            'name' => 'Mahasiswa KP',
            'email' => 'student@sikp.test',
            'password' => 'hash',
            'status' => 'active',
        ]);
        $lecturerUser = User::create([
            'name' => 'Dosen KP',
            'email' => 'lecturer@sikp.test',
            'password' => 'hash',
            'status' => 'active',
        ]);

        Student::create([
            'user_id' => $studentUser->id,
            'nim' => '221063120001',
            'study_program' => 'Farmasi',
            'semester' => 6,
            'status' => 'active',
        ]);
        Lecturer::create([
            'user_id' => $lecturerUser->id,
            'nidn_nip' => '0012345601',
            'study_program' => 'Farmasi S1',
            'department' => 'Fakultas Farmasi',
            'status' => 'active',
        ]);

        $before = [
            'students' => DB::table('students')->count(),
            'lecturers' => DB::table('lecturers')->count(),
        ];

        $this->artisan('kp:core-academic-unit-check --show-rows')
            ->expectsOutputToContain('KP Core academic unit alignment')
            ->expectsOutputToContain('Hierarchy: faculty > study_program > department')
            ->expectsOutputToContain('Study program unmapped: 0')
            ->expectsOutputToContain('Faculty label used as department: 1')
            ->expectsOutputToContain('Read-only counts unchanged: yes')
            ->assertSuccessful();

        $this->assertSame($before['students'], DB::table('students')->count());
        $this->assertSame($before['lecturers'], DB::table('lecturers')->count());
    }
}
