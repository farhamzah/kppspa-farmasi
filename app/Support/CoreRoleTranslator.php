<?php

namespace App\Support;

use Illuminate\Support\Str;

class CoreRoleTranslator
{
    public const CORE_TO_KP = [
        'admin-kppspa' => 'admin',
        'admin-kp' => 'admin',
        'mahasiswa' => 'mahasiswa',
        'dosen' => 'pembimbing_dalam',
        'koordinator-kppspa' => 'koordinator_kp',
        'koordinator-kp' => 'koordinator_kp',
        'pembimbing' => 'pembimbing_dalam',
        'pembimbing-dalam' => 'pembimbing_dalam',
        'pembimbing-lapangan' => 'pembimbing_lapangan',
        'penguji' => 'penguji',
    ];

    public const KP_TO_CORE = [
        'admin' => 'admin-kp',
        'mahasiswa' => 'mahasiswa',
        'koordinator_kp' => 'koordinator-kp',
        'pembimbing_dalam' => 'pembimbing-dalam',
        'pembimbing_lapangan' => 'pembimbing-lapangan',
        'penguji' => 'penguji',
    ];

    public const DENIED_CORE_ROLES = [
        'admin-core',
    ];

    public static function toKp(?string $coreRole): ?string
    {
        $normalized = self::normalizeCore($coreRole);

        if ($normalized === '' || in_array($normalized, self::DENIED_CORE_ROLES, true)) {
            return null;
        }

        return self::CORE_TO_KP[$normalized] ?? null;
    }

    public static function toCore(?string $kpRole): ?string
    {
        $normalized = self::normalizeKp($kpRole);

        return self::KP_TO_CORE[$normalized] ?? null;
    }

    public static function coreRolesToKp(iterable $coreRoles): array
    {
        return collect($coreRoles)
            ->map(fn ($role) => self::toKp(is_array($role) ? ($role['slug'] ?? $role['name'] ?? null) : $role))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public static function kpRolesToCore(iterable $kpRoles): array
    {
        return collect($kpRoles)
            ->map(fn ($role) => self::toCore(is_array($role) ? ($role['name'] ?? $role['slug'] ?? null) : $role))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public static function supportedCoreRoles(): array
    {
        return array_keys(self::CORE_TO_KP);
    }

    public static function supportedKpRoles(): array
    {
        return array_keys(self::KP_TO_CORE);
    }

    public static function normalizeCore(?string $role): string
    {
        return Str::of((string) $role)->trim()->lower()->replace('_', '-')->toString();
    }

    public static function normalizeKp(?string $role): string
    {
        return Str::of((string) $role)->trim()->lower()->replace('-', '_')->toString();
    }
}
