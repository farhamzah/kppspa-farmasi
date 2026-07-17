# TAHAP 04 - Penyusun Penempatan Draft

## Ringkasan Implementasi

Tahap 04 menambahkan planner penempatan PKPA draft/tervalidasi untuk Admin dan Koordinator PKPA. Fitur utama: versioned placement plan, matriks mahasiswa x enam wahana, assignment individual, bulk preview/apply/undo, timeline, validasi full-plan, export internal, audit, dan dashboard stats.

## Batas Scope

Belum ada publikasi jadwal, notifikasi, surat penempatan, penerimaan mitra, logbook rotasi, presensi, laporan rotasi, nilai, atau migrasi `kp_assignments`.

## Database

Tabel baru: `pkpa_placement_plans`, `pkpa_rotation_assignments`, `pkpa_rotation_assignment_supervisors`, `pkpa_placement_action_batches`, `pkpa_placement_action_batch_items`, `pkpa_placement_validation_runs`, dan `pkpa_placement_validation_issues`.

## UI

Halaman `management/pkpa-placement-planner` menyediakan matriks desktop, kartu mobile, form assignment eksplisit, panel bulk, preview terakhir, issue list, timeline, dan export.

## QA

- `php artisan optimize:clear`: berhasil.
- `php artisan migrate --seed --force`: berhasil, migration Tahap 04 berjalan tanpa `migrate:fresh` pada database lokal.
- `php artisan route:list --path=management/pkpa`: berhasil, 83 route PKPA.
- `php artisan test --filter=Tahap04PkpaPlacementPlannerTest`: 4 test, 47 assertion, hijau.
- `php artisan test`: 275 test, 1831 assertion, hijau.
- `npm.cmd run build`: berhasil, Vite build menghasilkan `app-D6Y8mxHk.css` dan `app-UyRVujZY.js`.
- Clean migration SQLite temporary `C:\tmp\my_pspa_tahap04_clean.sqlite`: `migrate:fresh --seed --force` berhasil, file temporary dihapus setelah QA.
- Browser QA `127.0.0.1:3006`: login koordinator, halaman planner desktop/tablet/mobile, matriks desktop, kartu tablet/mobile, bulk panel, issue list, dan timeline dicek. Tidak ada body horizontal overflow dan console error kosong.

## Risiko

Planner Tahap 04 belum menjalankan optimasi otomatis penuh. Validasi Core real-time untuk perubahan role/status pembimbing mengikuti snapshot dan sync service Tahap 03; automated test memakai data/fake lokal.
