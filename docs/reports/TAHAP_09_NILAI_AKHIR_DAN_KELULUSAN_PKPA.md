# TAHAP 09 - Nilai Akhir dan Kelulusan PKPA

Tahap 09 menambahkan skema nilai akhir program, komponen final, completion requirement, remedial policy/case/attempt, kalkulasi nilai akhir, final grade result, graduation decision, final result release, portal mahasiswa, export rekap, feature flag, dan test.

Nilai akhir menggunakan source snapshot dari nilai wahana finalized. Keputusan kelulusan terpisah dari kalkulasi nilai dan release mahasiswa. Tahap ini tidak membuat dokumen resmi universitas, sertifikat, SIAKAD, PDDikti, atau transkrip resmi.

Validasi awal:

- `php artisan test --filter=Tahap09PkpaFinalProgramTest --stop-on-failure`: passed, 3 tests, 23 assertions.
