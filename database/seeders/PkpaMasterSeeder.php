<?php

namespace Database\Seeders;

use App\Models\PkpaPracticeDomain;
use App\Models\PkpaPracticeDomainOption;
use App\Models\PkpaDocumentType;
use Illuminate\Database\Seeder;

class PkpaMasterSeeder extends Seeder
{
    public function run(): void
    {
        $domains = [
            ['code' => 'APT', 'name' => 'Apotek', 'short_name' => 'Apotek', 'sort_order' => 10],
            ['code' => 'PKM', 'name' => 'Puskesmas', 'short_name' => 'Puskesmas', 'sort_order' => 20],
            ['code' => 'PBF', 'name' => 'Pedagang Besar Farmasi', 'short_name' => 'PBF', 'sort_order' => 30],
            ['code' => 'RS', 'name' => 'Rumah Sakit', 'short_name' => 'RS', 'sort_order' => 40],
            ['code' => 'IND', 'name' => 'Industri', 'short_name' => 'Industri', 'sort_order' => 50],
            ['code' => 'PEM', 'name' => 'Pemerintahan', 'short_name' => 'Pemerintahan', 'sort_order' => 60],
        ];

        foreach ($domains as $data) {
            $domain = PkpaPracticeDomain::withTrashed()->firstOrNew(['code' => $data['code']]);

            if (! $domain->exists) {
                $domain->fill([
                    'name' => $data['name'],
                    'short_name' => $data['short_name'],
                    'description' => null,
                    'is_active' => true,
                ]);
            }

            $domain->fill([
                'is_system' => true,
                'sort_order' => $domain->sort_order ?: $data['sort_order'],
            ]);
            $domain->deleted_at = null;
            $domain->save();
        }

        $government = PkpaPracticeDomain::where('code', 'PEM')->firstOrFail();

        foreach ([
            ['code' => 'LOKAPOM', 'name' => 'Loka POM', 'sort_order' => 10],
            ['code' => 'DINKES', 'name' => 'Dinas Kesehatan', 'sort_order' => 20],
        ] as $data) {
            $option = PkpaPracticeDomainOption::withTrashed()
                ->where('practice_domain_id', $government->id)
                ->where('code', $data['code'])
                ->first() ?? new PkpaPracticeDomainOption([
                    'practice_domain_id' => $government->id,
                    'code' => $data['code'],
                    'name' => $data['name'],
                    'is_active' => true,
                ]);

            $option->fill([
                'is_system' => true,
                'sort_order' => $option->sort_order ?: $data['sort_order'],
            ]);
            $option->deleted_at = null;
            $option->save();
        }

        $documentTypes = [
            ['code' => 'surat_penempatan_mahasiswa', 'name' => 'Surat Penempatan Mahasiswa', 'scope_type' => 'student', 'formats' => ['docx', 'pdf'], 'number' => true, 'signatory' => true, 'order' => 10],
            ['code' => 'surat_pengantar_mitra', 'name' => 'Surat Pengantar Mitra', 'scope_type' => 'site', 'formats' => ['docx', 'pdf'], 'number' => true, 'signatory' => true, 'order' => 20],
            ['code' => 'surat_tugas_pembimbing_dalam', 'name' => 'Surat Tugas Pembimbing Dalam', 'scope_type' => 'supervisor', 'formats' => ['docx', 'pdf'], 'number' => true, 'signatory' => true, 'order' => 30],
            ['code' => 'daftar_mahasiswa_mitra', 'name' => 'Daftar Mahasiswa Mitra', 'scope_type' => 'site', 'formats' => ['xlsx', 'csv', 'pdf'], 'number' => false, 'signatory' => false, 'order' => 40],
            ['code' => 'jadwal_rotasi_mahasiswa', 'name' => 'Rekap Jadwal Rotasi Mahasiswa', 'scope_type' => 'student', 'formats' => ['xlsx', 'csv', 'pdf'], 'number' => false, 'signatory' => false, 'order' => 50],
            ['code' => 'jadwal_rotasi_tempat', 'name' => 'Rekap Jadwal Rotasi Tempat', 'scope_type' => 'site', 'formats' => ['xlsx', 'csv', 'pdf'], 'number' => false, 'signatory' => false, 'order' => 60],
            ['code' => 'jadwal_rotasi_pembimbing', 'name' => 'Rekap Jadwal Rotasi Pembimbing', 'scope_type' => 'supervisor', 'formats' => ['xlsx', 'csv', 'pdf'], 'number' => false, 'signatory' => false, 'order' => 70],
            ['code' => 'surat_perubahan_penempatan', 'name' => 'Surat Perubahan Penempatan', 'scope_type' => 'program', 'formats' => ['docx', 'pdf'], 'number' => true, 'signatory' => true, 'order' => 80],
            ['code' => 'rekap_hasil_wahana', 'name' => 'Rekap Hasil Wahana', 'scope_type' => 'grade', 'formats' => ['xlsx', 'csv', 'pdf'], 'number' => false, 'signatory' => false, 'order' => 90],
            ['code' => 'hasil_akhir_pkpa_internal', 'name' => 'Rekap Hasil Akademik PKPA', 'scope_type' => 'graduation', 'formats' => ['docx', 'pdf', 'xlsx'], 'number' => true, 'signatory' => true, 'order' => 100],
            ['code' => 'transkrip_internal_pkpa', 'name' => 'Transkrip Internal PKPA', 'scope_type' => 'graduation', 'formats' => ['docx', 'pdf'], 'number' => true, 'signatory' => true, 'order' => 110],
        ];

        if (class_exists(PkpaDocumentType::class) && \Illuminate\Support\Facades\Schema::hasTable('pkpa_document_types')) {
            foreach ($documentTypes as $data) {
                PkpaDocumentType::withTrashed()->updateOrCreate(['code' => $data['code']], [
                    'name' => $data['name'],
                    'description' => 'Jenis dokumen internal MY PSPA. Template isi wajib dikonfigurasi sebelum digunakan untuk dokumen resmi internal.',
                    'scope_type' => $data['scope_type'],
                    'output_formats' => $data['formats'],
                    'requires_number' => $data['number'],
                    'requires_signatory' => $data['signatory'],
                    'requires_approval' => true,
                    'is_system' => true,
                    'is_active' => true,
                    'sort_order' => $data['order'],
                    'deleted_at' => null,
                ]);
            }
        }
    }
}
