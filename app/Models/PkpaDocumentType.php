<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PkpaDocumentType extends Model
{
    use SoftDeletes;

    public const SYSTEM_TYPES = [
        'surat_penempatan_mahasiswa',
        'surat_pengantar_mitra',
        'surat_tugas_pembimbing_dalam',
        'daftar_mahasiswa_mitra',
        'jadwal_rotasi_mahasiswa',
        'jadwal_rotasi_tempat',
        'jadwal_rotasi_pembimbing',
        'surat_perubahan_penempatan',
        'rekap_hasil_wahana',
        'hasil_akhir_pkpa_internal',
        'transkrip_internal_pkpa',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'output_formats' => 'array',
            'requires_number' => 'boolean',
            'requires_signatory' => 'boolean',
            'requires_approval' => 'boolean',
            'is_system' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function templates(): HasMany
    {
        return $this->hasMany(PkpaDocumentTemplate::class);
    }

    public function numberingRules(): HasMany
    {
        return $this->hasMany(PkpaDocumentNumberingRule::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(PkpaGeneratedDocument::class);
    }
}
