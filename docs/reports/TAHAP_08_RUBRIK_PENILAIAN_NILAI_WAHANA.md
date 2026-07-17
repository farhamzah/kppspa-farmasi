# TAHAP 08 - Rubrik Penilaian dan Nilai Wahana PKPA

## Ringkasan

Tahap 08 menambahkan fondasi penilaian akademik per rotasi/wahana PKPA: skema penilaian, komponen, rubrik, assessor assignment, score component, moderasi, finalisasi nilai wahana, release nilai mahasiswa, grade change request, export rekap, route, menu, UI role, notifikasi, dan test.

## Batasan

Modul ini belum menghitung agregasi enam wahana, huruf mutu akhir PKPA, yudisium, sertifikat, OSCE, ujian komprehensif, atau integrasi SIAKAD/PDDikti. Finalisasi nilai wahana tidak mengubah requirement menjadi completed.

## Validasi Awal

- `php artisan test --filter=Tahap08PkpaAssessmentTest --stop-on-failure`: passed, 4 tests, 28 assertions.

## Catatan Implementasi

- Bobot aktif wajib 100% sebelum skema aktif.
- Assessment membutuhkan academic readiness `ready_for_assessment`.
- Pembimbing hanya menilai assignment miliknya.
- Perhitungan nilai menggunakan integer scaling empat desimal.
- Release nilai kepada mahasiswa dipisah dari finalisasi.
- Portal mahasiswa hanya membaca release miliknya dan menampilkan label bahwa nilai adalah nilai wahana.
