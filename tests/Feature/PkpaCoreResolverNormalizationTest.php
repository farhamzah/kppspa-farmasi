<?php

namespace Tests\Feature;

use App\Services\PkpaCoreStudentResolver;
use App\Services\PkpaSupervisorCoreResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PkpaCoreResolverNormalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_internal_supervisor_normalize_prefers_nested_core_user_identity(): void
    {
        $resolver = app(PkpaSupervisorCoreResolver::class);

        $normalized = $resolver->normalize([
            'id' => 11,
            'user_id' => 11,
            'name' => 'Profil Dosen Lokal Core',
            'user' => [
                'id' => 2,
                'name' => 'Farhamzah',
                'email' => 'farhamzah@ubpkarawang.ac.id',
                'active' => true,
                'roles' => [
                    ['slug' => 'pembimbing_dalam'],
                ],
            ],
        ]);

        $this->assertSame('2', $normalized['core_user_id']);
        $this->assertSame('Farhamzah', $normalized['name']);
        $this->assertSame('farhamzah@ubpkarawang.ac.id', $normalized['email']);
    }

    public function test_student_normalize_prefers_nested_core_user_identity(): void
    {
        $resolver = app(PkpaCoreStudentResolver::class);

        $normalized = $resolver->normalizeStudent([
            'id' => 91,
            'user_id' => 91,
            'student_number' => '231001',
            'name' => 'Profil Mahasiswa Lokal Core',
            'user' => [
                'id' => 7,
                'name' => 'Andi Farmasi',
                'email' => 'andi@ubpkarawang.ac.id',
                'active' => true,
                'roles' => [
                    ['slug' => 'mahasiswa'],
                ],
            ],
        ]);

        $this->assertSame('7', $normalized['core_user_id']);
        $this->assertSame('Andi Farmasi', $normalized['name']);
        $this->assertSame('andi@ubpkarawang.ac.id', $normalized['email']);
        $this->assertSame('231001', $normalized['student_number']);
    }
}
