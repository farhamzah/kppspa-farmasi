# TAHAP 01 - Master Program, Wahana, dan Tempat Praktik

## Ringkasan

Tahap 01 menambahkan fondasi master data PKPA paralel terhadap struktur legacy KP. Modul baru mencakup Program PKPA, Wahana PKPA, pilihan Pemerintahan, konfigurasi durasi per program-wahana, Tempat Praktik, audit master, route, UI, seeder, dan automated test.

## Folder Kerja

`farmasi-ubp-workspace/apps/kppspa-farmasi`

## Status Awal

- Branch: `main`
- Baseline sebelumnya: 250 tests passed, 1554 assertions; frontend build berhasil.
- `.env` lokal memakai SQLite, sehingga migrasi destruktif dijalankan hanya pada database lokal aman.
- Perubahan lokal lama dari tahap sebelumnya dipertahankan, termasuk perubahan report/export legacy dan dokumen manual.

## Database Baru

- `pkpa_programs`: program PKPA, unique `code`, status string, tanggal program, actor Core, soft delete.
- `pkpa_practice_domains`: master wahana, unique `code`, `is_system`, `is_active`, soft delete.
- `pkpa_practice_domain_options`: option/subjenis, foreign key domain, unique `practice_domain_id + code`, soft delete.
- `pkpa_program_domains`: konfigurasi program-wahana, unique `pkpa_program_id + practice_domain_id`, durasi, unit, hari efektif, jam praktik, bobot, instruksi.
- `pkpa_practice_sites`: tempat praktik, foreign key domain dan nullable option, unique `code`, data alamat/kontak/kerja sama, soft delete.
- `pkpa_master_audits`: audit actor Core, action, entity, old/new values.

## Seeder Default

`PkpaMasterSeeder` membuat enam wahana sistem (`APT`, `PKM`, `PBF`, `RS`, `IND`, `PEM`) dan dua option Pemerintahan (`LOKAPOM`, `DINKES`) secara idempotent. Seeder tidak membuat tempat praktik palsu dan tidak mengisi durasi resmi.

## Program PKPA

Admin/Koordinator dapat membuat program. Sistem otomatis membuat enam konfigurasi wahana. Durasi setiap wahana diisi pada halaman konfigurasi. Program draft boleh kosong durasi. Status `ready` dan `active` ditahan oleh checklist kesiapan.

## Wahana dan Pilihan

Builder Wahana PKPA mendukung wahana tambahan. Wahana sistem diberi badge dan tidak dapat dihapus. Detail Pemerintahan menampilkan Loka POM dan Dinas Kesehatan serta mendukung penambahan, pengubahan, aktivasi/nonaktif, dan penghapusan option non-system.

## Tempat Praktik

Builder tempat praktik mendukung domain, option, alamat, kontak, periode kerja sama, status, dan filter. Tempat Pemerintahan wajib memakai option dari domain Pemerintahan. Option domain lain ditolak.

## Authorization Core

Route master dilindungi `auth`, `active`, `role.selected`, dan `role:admin,koordinator_kp`. Actor Core ID dicatat dari `users.core_user_id` bila tersedia. Tidak ada akun, password, atau role lokal baru.

## Audit Trail

Audit mencatat program dibuat/diperbarui/diaktifkan, konfigurasi wahana diubah, wahana dibuat/diperbarui/dihapus, option dibuat/diperbarui/dihapus, dan tempat dibuat/diperbarui/dihapus. Field sensitif seperti password, token, dan secret difilter.

## Kompatibilitas Lama

Tabel `kp_periods`, `kp_places`, `kp_place_quotas`, `kp_place_selections`, `kp_waiting_lists`, dan `kp_assignments` tidak dihapus dan belum dimigrasikan. Mapping awal hanya terdokumentasi di migration map.

## UI

Menu Admin/Koordinator kini menampilkan Program PKPA, Wahana PKPA, dan Tempat Praktik. UI memakai table wrapper responsive, card mobile-friendly, form stacked, badge status, empty state, dan action button konsisten.

## File Penting yang Berubah

Migration:

- `database/migrations/2026_07_16_010001_create_pkpa_master_data_tables.php`

Model:

- `app/Models/PkpaProgram.php`
- `app/Models/PkpaPracticeDomain.php`
- `app/Models/PkpaPracticeDomainOption.php`
- `app/Models/PkpaProgramDomain.php`
- `app/Models/PkpaPracticeSite.php`
- `app/Models/PkpaMasterAudit.php`

Service:

- `app/Services/PkpaProgramService.php`
- `app/Services/PkpaPracticeDomainService.php`
- `app/Services/PkpaPracticeSiteService.php`
- `app/Services/PkpaAuditService.php`

Controller dan request:

- `app/Http/Controllers/Management/PkpaProgramController.php`
- `app/Http/Controllers/Management/PkpaPracticeDomainController.php`
- `app/Http/Controllers/Management/PkpaPracticeSiteController.php`
- `app/Http/Requests/Management/Pkpa/*`

Route dan view:

- `routes/web.php`
- `resources/views/layouts/app.blade.php`
- `resources/views/dashboard/show.blade.php`
- `resources/views/management/pkpa-programs/*`
- `resources/views/management/pkpa-practice-domains/*`
- `resources/views/management/pkpa-practice-sites/*`

Seeder dan test:

- `database/seeders/PkpaMasterSeeder.php`
- `database/seeders/DatabaseSeeder.php`
- `tests/Feature/Tahap01PkpaMasterDataTest.php`

Dokumentasi:

- `AGENTS.md`
- `README.md`
- `docs/specs/MY_PKPA_PROGRAM_AND_MASTER_DATA_SPEC.md`
- `docs/specs/MY_PKPA_MASTER_SPEC.md`
- `docs/specs/MY_PKPA_DOMAIN_MIGRATION_MAP.md`
- `docs/prompts/TAHAP_01_MASTER_PROGRAM_WAHANA_TEMPAT.md`
- `docs/reports/TAHAP_01_MASTER_PROGRAM_WAHANA_TEMPAT.md`

## QA

Automated QA:

- `php artisan optimize:clear`: passed
- `php artisan migrate:fresh --seed --force`: passed pada SQLite lokal
- `php artisan test --filter Tahap01PkpaMasterDataTest`: 11 passed, 69 assertions
- `php artisan test`: 261 passed, 1623 assertions
- `npm.cmd run build`: passed, Vite build berhasil
- `php artisan route:list --path=management/pkpa`: 28 route PKPA terdaftar

Browser QA lokal:

- Login admin demo berhasil pada mode legacy lokal.
- Program PKPA dibuat melalui UI dan redirect ke konfigurasi durasi.
- Halaman yang dicek: daftar program, form program, konfigurasi enam wahana, daftar wahana, detail Pemerintahan, daftar tempat, form tempat.
- Viewport yang dicek: desktop 1365x768, tablet 900x1100, mobile 390x844.
- Hasil: tidak ada horizontal overflow, tidak ada console error, form label dan field terdeteksi, header/sidebar sticky tetap tampil, table berada dalam responsive wrapper, empty state dan action button tampil.
- `.env` lokal diselaraskan ke `APP_NAME="MY PKPA"` setelah ditemukan title browser masih memakai nama lama.

Runtime MySQL:

- Tidak dijalankan karena `.env` lokal memakai SQLite: `DB_CONNECTION=sqlite`.
- Kompatibilitas MySQL diperiksa secara kode: string pendek untuk indexed code/status, composite unique bernama eksplisit, nullable foreign key option, decimal untuk durasi/bobot, soft delete, dan tidak ada enum database.

## Risiko

- Runtime migrasi MySQL belum dijalankan karena `.env` lokal memakai SQLite. Struktur migration diperiksa secara kode untuk tipe string pendek, composite unique, nullable foreign key, decimal, dan soft delete yang kompatibel MySQL/MariaDB.
- Migrasi data lama belum dilakukan dan memang di luar scope.

## Status Git Akhir

- Tidak ada commit.
- Tidak ada push.
- Tidak ada tag.
- Tidak ada merge.
- Tidak ada release.
- `apps/core-farmasi` bersih/tidak berubah.
- Worktree `apps/kppspa-farmasi` masih berisi perubahan Tahap 00 yang belum dicommit dan perubahan Tahap 01 baru.

## Rekomendasi Tahap Berikutnya

Tahap 02 - Kepesertaan Mahasiswa, Enam Kewajiban Wahana Otomatis, Kelompok Rotasi, dan Import Referensi Mahasiswa dari Core Farmasi.
