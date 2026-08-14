<?php

namespace Tests\Feature;

use App\Support\CoreRoleTranslator;
use Tests\TestCase;

class CoreRoleTranslatorTest extends TestCase
{
    public function test_core_roles_translate_to_existing_kp_roles(): void
    {
        $this->assertSame('admin', CoreRoleTranslator::toKp('admin-kppspa'));
        $this->assertSame('admin', CoreRoleTranslator::toKp('admin-kp'));
        $this->assertSame('koordinator_kp', CoreRoleTranslator::toKp('koordinator-kppspa'));
        $this->assertSame('koordinator_kp', CoreRoleTranslator::toKp('koordinator-kp'));
        $this->assertSame('pembimbing_dalam', CoreRoleTranslator::toKp('pembimbing'));
        $this->assertSame('pembimbing_dalam', CoreRoleTranslator::toKp('pembimbing-dalam'));
        $this->assertSame('pembimbing_lapangan', CoreRoleTranslator::toKp('pembimbing-lapangan'));
        $this->assertSame('penguji', CoreRoleTranslator::toKp('penguji'));
        $this->assertSame('penguji', CoreRoleTranslator::toKp('penguji-luar'));
        $this->assertSame('mahasiswa', CoreRoleTranslator::toKp('mahasiswa'));
    }

    public function test_translation_normalizes_role_format_and_denies_admin_core(): void
    {
        $this->assertSame('pembimbing_dalam', CoreRoleTranslator::toKp(' Pembimbing_Dalam '));
        $this->assertSame('pembimbing-dalam', CoreRoleTranslator::toCore(' pembimbing-dalam '));
        $this->assertNull(CoreRoleTranslator::toKp('admin-core'));
        $this->assertNull(CoreRoleTranslator::toKp('unknown-role'));
    }

    public function test_role_lists_translate_uniquely(): void
    {
        $this->assertSame(
            ['koordinator_kp', 'pembimbing_dalam', 'penguji'],
            CoreRoleTranslator::coreRolesToKp(['koordinator-kppspa', 'pembimbing', 'penguji', 'admin-core', 'penguji'])
        );

        $this->assertSame(
            ['koordinator-kp', 'pembimbing-dalam'],
            CoreRoleTranslator::kpRolesToCore(['koordinator_kp', 'pembimbing_dalam', 'unknown'])
        );
    }
}
