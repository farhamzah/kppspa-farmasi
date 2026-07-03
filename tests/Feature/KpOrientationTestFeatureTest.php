<?php

namespace Tests\Feature;

use App\Models\KpOrientationTest;
use App\Models\KpOrientationTestAttempt;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class KpOrientationTestFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_submit_post_test_and_see_score_with_correct_answers(): void
    {
        $this->seed(RoleSeeder::class);
        $studentUser = $this->user('Mahasiswa Test', 'student-orientation@test.local', ['mahasiswa']);
        $student = $studentUser->student()->create(['nim' => '2441624820999']);
        $postTest = KpOrientationTest::where('type', 'post')->with('questions')->firstOrFail();

        $payload = [
            'answers' => $postTest->questions->mapWithKeys(fn ($question) => [
                $question->id => $question->correct_choice_index,
            ])->all(),
        ];

        $response = $this->actingAs($studentUser)
            ->withSession(['active_role' => 'mahasiswa'])
            ->post(route('student.orientation-tests.submit', $postTest), $payload);

        $attempt = KpOrientationTestAttempt::where('student_id', $student->id)->where('kp_orientation_test_id', $postTest->id)->firstOrFail();

        $response->assertRedirect(route('student.orientation-tests.result', $attempt));
        $this->assertSame(100, $attempt->fresh()->score);
        $this->assertSame(10, $attempt->answers()->count());

        $this->actingAs($studentUser)
            ->withSession(['active_role' => 'mahasiswa'])
            ->get(route('student.orientation-tests.result', $attempt))
            ->assertOk()
            ->assertSee('100')
            ->assertSee('Jawaban Benar')
            ->assertSee('Mengutamakan keselamatan, etika, kepatuhan SOP, dan sikap profesional');
    }

    public function test_admin_and_coordinator_can_monitor_orientation_test_results(): void
    {
        $this->seed(RoleSeeder::class);
        $studentUser = $this->user('Mahasiswa Monitor', 'student-monitor@test.local', ['mahasiswa']);
        $student = $studentUser->student()->create(['nim' => '2441624820888']);
        $admin = $this->user('Admin', 'admin-monitor@test.local', ['admin']);
        $coordinator = $this->user('Koordinator', 'koordinator-monitor@test.local', ['koordinator_kp']);
        $test = KpOrientationTest::where('type', 'pre')->firstOrFail();
        $attempt = KpOrientationTestAttempt::create([
            'kp_orientation_test_id' => $test->id,
            'student_id' => $student->id,
            'user_id' => $studentUser->id,
            'score' => 80,
            'max_score' => 100,
            'percentage' => 80,
            'submitted_at' => now(),
        ]);

        $this->actingAs($admin)
            ->withSession(['active_role' => 'admin'])
            ->get(route('management.orientation-tests.index'))
            ->assertOk()
            ->assertSee('Mahasiswa Monitor')
            ->assertSee('80/100');

        $this->actingAs($coordinator)
            ->withSession(['active_role' => 'koordinator_kp'])
            ->get(route('management.orientation-tests.show', $attempt))
            ->assertOk()
            ->assertSee('Detail Hasil Test')
            ->assertSee('Mahasiswa Monitor');
    }

    private function user(string $name, string $email, array $roles): User
    {
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make('password'),
            'status' => 'active',
            'profile_completed' => true,
        ]);

        $user->roles()->sync(Role::whereIn('name', $roles)->pluck('id'));

        return $user;
    }
}
