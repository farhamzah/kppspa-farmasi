<?php

namespace Tests\Feature;

use App\Models\PkpaEnrollment;
use App\Models\PkpaProgram;
use Database\Seeders\PkpaMasterSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Tahap11aAiPreUatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, PkpaMasterSeeder::class]);
    }

    public function test_integrity_audit_command_passes_on_clean_seeded_database(): void
    {
        $this->artisan('pkpa:integrity-audit --json')
            ->expectsOutputToContain('"status": "passed"')
            ->assertExitCode(0);
    }

    public function test_integrity_audit_fails_on_enrollment_without_requirements(): void
    {
        $program = PkpaProgram::create([
            'code' => 'PKPA-11A',
            'name' => 'Program Pre-UAT Testing',
            'academic_year' => '2026/2027',
            'cohort_name' => 'Pre-UAT',
            'start_date' => '2026-07-01',
            'end_date' => '2026-12-31',
            'status' => 'active',
            'is_active' => true,
        ]);

        PkpaEnrollment::create([
            'pkpa_program_id' => $program->id,
            'core_user_id' => 'PRE-UAT-NO-REQ',
            'student_number' => 'PREUAT001',
            'student_name_snapshot' => 'Mahasiswa Pre-UAT Tanpa Requirement',
            'student_email_snapshot' => 'preuat-no-req@example.test',
            'status' => 'active',
        ]);

        $this->artisan('pkpa:integrity-audit --json')
            ->expectsOutputToContain('"status": "failed"')
            ->assertExitCode(1);
    }
}
