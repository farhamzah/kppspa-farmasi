# KP Orientation Pre/Post Test

Tanggal: 2026-07-03

## Ringkasan

Modul pre-test dan post-test pembekalan KP ditambahkan ke aplikasi KP Farmasi. Soal awal diambil dari contoh Google Form post-test yang diberikan user, lalu dibuat sebagai master data lokal KP melalui migration.

## Fitur

- Mahasiswa melihat menu `Pre/Post Test`.
- Mahasiswa mengerjakan pre-test dan post-test aktif.
- Sistem menghitung skor otomatis berdasarkan kunci jawaban.
- Mahasiswa dapat melihat skor, jawaban yang dipilih, jawaban benar, dan pembahasan setelah submit.
- Admin dan Koordinator KP melihat menu `Hasil Pre/Post Test`.
- Admin dan Koordinator KP dapat memfilter hasil berdasarkan nama/NIM/email dan tipe test.

## File Penting

- `database/migrations/2026_07_03_000001_create_kp_orientation_test_tables.php`
- `app/Models/KpOrientationTest.php`
- `app/Models/KpOrientationTestQuestion.php`
- `app/Models/KpOrientationTestAttempt.php`
- `app/Models/KpOrientationTestAnswer.php`
- `app/Http/Controllers/Student/OrientationTestController.php`
- `app/Http/Controllers/Management/OrientationTestResultController.php`
- `resources/views/student/orientation-tests/*`
- `resources/views/management/orientation-tests/*`
- `tests/Feature/KpOrientationTestFeatureTest.php`

## Guardrails

- Tidak ada write ke Core/TU/SAFA.
- Tidak ada copy password Core.
- Jawaban mahasiswa tersimpan di database KP lokal.
- Mahasiswa hanya dapat melihat hasil miliknya sendiri.
- Admin/Koordinator dapat memonitor semua hasil.

## Catatan Lanjutan

Tahap berikutnya dapat menambahkan builder soal dari UI, export hasil, batas jadwal pengerjaan, dan opsi reset attempt oleh admin bila diperlukan.
