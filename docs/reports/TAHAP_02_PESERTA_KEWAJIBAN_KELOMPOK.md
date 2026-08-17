# TAHAP 02 - Peserta, Kewajiban Wahana, Kelompok, dan Import Core

## Ringkasan

Tahap 02 menambahkan fondasi kepesertaan mahasiswa Program PKPA: enrollment dari Core Farmasi, enam kewajiban wahana otomatis, Pemerintahan `choose_one`, kelompok mahasiswa, histori membership, import CSV/XLSX dengan preview, sinkronisasi snapshot Core, dashboard ringkas, UI manajemen, audit trail, test, dan dokumentasi.

## Folder Kerja

`farmasi-ubp-workspace/apps/kppspa-farmasi`

## Status Awal

- Branch: `main`
- Baseline Tahap 01 terakhir: 261 tests passed, 1623 assertions, frontend build berhasil.
- Worktree sudah berisi perubahan lokal Tahap 00/01 dan perubahan user yang dipertahankan.
- `.env` lokal memakai SQLite development; MySQL sudah dijalankan oleh user tetapi `migrate:fresh` MySQL tidak dijalankan karena belum dikonfirmasi aman.

## Database Baru

- `pkpa_enrollments`: enrollment per program dan Core user, unique `pkpa_program_id + core_user_id`, snapshot Core, status, sync status, soft delete.
- `pkpa_enrollment_requirements`: requirement wahana per enrollment, unique `pkpa_enrollment_id + pkpa_program_domain_id`.
- `pkpa_student_groups`: kelompok per program, unique `pkpa_program_id + code`, kapasitas opsional, status, soft delete.
- `pkpa_student_group_members`: histori membership dengan `joined_at`, `left_at`, dan status.
- `pkpa_enrollment_import_batches`: metadata batch import dan ringkasan valid/invalid/imported.
- `pkpa_enrollment_import_rows`: payload aman dan hasil validasi per row.

## Integrasi Core

`PkpaCoreStudentResolver` memakai `CoreFarmasiClient` untuk mencari mahasiswa berdasarkan Core user ID atau NPM, memverifikasi akun aktif, role mahasiswa, dan app access MY PKPA. Snapshot identitas disimpan di enrollment. Sinkronisasi individual dan program memperbarui snapshot tanpa mengubah `core_user_id`.

Core unavailable atau data tidak ditemukan tidak menghapus snapshot lama dan dicatat sebagai status sync gagal/peringatan.

## Enam Kewajiban

`PkpaEnrollmentRequirementService` membuat requirement dari wahana aktif program dalam transaksi enrollment. Program standar menghasilkan lima requirement `direct` dan satu requirement Pemerintahan `choose_one` dengan selected option awal `null`.

## Kelompok

Kelompok dibuat oleh Admin/Koordinator. Membership menjaga histori perpindahan; membership lama ditutup sebelum membership baru dibuat. Peserta dan kelompok harus berada pada program yang sama dan kapasitas maksimum dihormati.

## Import

Template CSV tersedia dengan kolom `core_user_id`, `npm`, `group_code`, `notes`. Preview memvalidasi header, menolak password/role, resolve Core, mendeteksi duplikat file/database, memeriksa kelompok, dan tidak langsung membuat enrollment. Final import hanya memproses row valid.

## Authorization

Route manajemen peserta dan kelompok dilindungi `auth`, `active`, `role.selected`, dan `role:admin,koordinator_kp`. Mahasiswa mendapat 403 untuk aksi administratif.

## UI/UX

Halaman baru:

- daftar peserta;
- tambah peserta individual;
- detail peserta dan enam requirement;
- import peserta dan preview batch;
- daftar kelompok;
- tambah/edit/detail kelompok;
- tambah anggota dan tambah massal;
- dashboard Admin/Koordinator;
- kartu ringkas dashboard mahasiswa.

## Audit Trail

Audit dicatat untuk enrollment dibuat/dibatalkan/status, requirement dibuat, kelompok dibuat/diubah, anggota ditambahkan/dikeluarkan, import divalidasi/dijalankan, dan sync Core.

## Kompatibilitas Legacy

Tabel `kp_periods`, `kp_places`, `kp_place_quotas`, `kp_place_selections`, `kp_waiting_lists`, dan `kp_assignments` tidak dihapus, tidak dimigrasikan, dan fitur war tetap dikunci feature flag.

## File Penting

Migration:

- `database/migrations/2026_07_16_020001_create_pkpa_enrollment_tables.php`

Model:

- `app/Models/PkpaEnrollment.php`
- `app/Models/PkpaEnrollmentRequirement.php`
- `app/Models/PkpaStudentGroup.php`
- `app/Models/PkpaStudentGroupMember.php`
- `app/Models/PkpaEnrollmentImportBatch.php`
- `app/Models/PkpaEnrollmentImportRow.php`

Service:

- `app/Services/PkpaCoreStudentResolver.php`
- `app/Services/PkpaEnrollmentService.php`
- `app/Services/PkpaEnrollmentRequirementService.php`
- `app/Services/PkpaStudentGroupService.php`
- `app/Services/PkpaEnrollmentImportService.php`
- `app/Services/PkpaEnrollmentCoreSyncService.php`

Controller, request, route, view:

- `app/Http/Controllers/Management/PkpaEnrollmentController.php`
- `app/Http/Controllers/Management/PkpaStudentGroupController.php`
- `app/Http/Requests/Management/Pkpa/*`
- `routes/web.php`
- `resources/views/management/pkpa-enrollments/*`
- `resources/views/management/pkpa-student-groups/*`
- `resources/views/dashboard/show.blade.php`
- `resources/views/layouts/app.blade.php`

Test dan dokumentasi:

- `tests/Feature/Tahap02PkpaEnrollmentTest.php`
- `docs/specs/MY_PKPA_ENROLLMENT_AND_GROUP_SPEC.md`
- `docs/specs/MY_PKPA_CORE_STUDENT_IMPORT_SPEC.md`
- `docs/prompts/TAHAP_02_PESERTA_KEWAJIBAN_KELOMPOK.md`
- `docs/reports/TAHAP_02_PESERTA_KEWAJIBAN_KELOMPOK.md`

## QA

- `php artisan optimize:clear`: passed
- `php artisan migrate:fresh --seed --force`: passed pada SQLite lokal
- `php artisan test --filter Tahap02PkpaEnrollmentTest`: 5 passed, 80 assertions
- `php artisan test`: 266 passed, 1703 assertions
- `npm.cmd run build`: passed, Vite build sukses
- `php artisan route:list --path=management/pkpa`: 50 route PKPA tampil
- Browser QA Edge/DevTools: desktop, tablet 768x1024, dan mobile 390x844

Browser QA mencakup:

- dashboard admin;
- daftar peserta;
- tambah peserta;
- detail peserta dan enam requirement;
- daftar kelompok;
- detail kelompok, anggota aktif, histori, bulk panel;
- import peserta;
- preview import valid/invalid.

Hasil browser QA: tidak ada horizontal overflow pada viewport yang dicek, halaman tidak menampilkan error framework, detail peserta memuat 6 requirement, dan Pemerintahan tampil sebagai satu requirement dengan pilihan awal "Belum ditentukan".

Status MySQL runtime: MySQL sudah dijalankan oleh user, tetapi `migrate:fresh` hanya dijalankan pada SQLite lokal karena operasi tersebut destruktif dan belum dikonfirmasi aman untuk database MySQL.

## Kendala dan Risiko

- Fake Core digunakan untuk automated test; jangan mengakses Core production saat test.
- Import XLSX membaca sheet aktif pertama dan formula diperlakukan sebagai nilai data.
- Dashboard requirement hilang bersifat indikator awal, bukan audit akademik final.
- MySQL runtime belum dijalankan `migrate:fresh` karena berisiko destruktif tanpa konfirmasi database aman.

## Rekomendasi Tahap Berikutnya

Tahap 03 - Kapasitas Tempat, Periode Ketersediaan Mitra, Pembimbing Dalam/Lapangan dari Core Farmasi, dan Persiapan Data Penempatan.
