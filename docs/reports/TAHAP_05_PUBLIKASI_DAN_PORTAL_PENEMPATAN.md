# TAHAP 05 - Publikasi dan Portal Penempatan PKPA

## Ringkasan

Tahap 05 menambahkan alur resmi dari placement plan terkunci ke publication snapshot, portal jadwal mahasiswa/PD/PL, acknowledgement, notifikasi delivery, withdrawal, change request, revision publication, dan export Excel resmi.

## File Penting

- Migration: `2026_07_17_050001_create_pkpa_publication_portal_tables.php`
- Services: `PkpaPlacementPublicationService`, `PkpaPlacementReviewService`, `PkpaPlacementChangeRequestService`, `PkpaPlacementNotificationService`, `PkpaScheduleAcknowledgementService`
- Controller: `Management\PkpaPlacementPublicationController`, `Student\PkpaScheduleController`, `InternalSupervisor\PkpaScheduleController`, `FieldSupervisor\PkpaScheduleController`
- Export: `PkpaOfficialScheduleExport`
- Test: `Tahap05PkpaPublicationPortalTest`

## QA Terakhir

- `php -l` file PHP baru/tersentuh: pass
- `php artisan optimize:clear`: pass
- `php artisan migrate --seed --force`: pass
- `php artisan route:list --path=management/pkpa`: route publikasi, change request, dan retry notifikasi terdaftar
- `php artisan route:list --path=mahasiswa/pkpa-saya`: 3 route portal mahasiswa terdaftar
- `php artisan route:list --path=pembimbing-dalam/jadwal-pkpa`: 3 route portal PD terdaftar
- `php artisan route:list --path=pembimbing-lapangan/jadwal-pkpa`: 3 route portal PL terdaftar
- `php artisan test --filter=Tahap05PkpaPublicationPortalTest --stop-on-failure`: 3 passed, 78 assertions
- `php artisan test`: 278 passed, 1909 assertions
- `npm.cmd run build`: pass
- SQLite clean migration `migrate:fresh --seed --force`: pass pada `C:\tmp\my_pspa_tahap05_clean.sqlite`
- Browser QA localhost port 3006: halaman publikasi desktop/mobile tidak overflow, portal pembimbing mobile menampilkan empty state bersih, portal mahasiswa mobile menampilkan empty state bersih

## Catatan

Tidak ada commit, push, tag, merge, atau perubahan pada `apps/core-farmasi`. Tabel legacy `kp_*` tidak dipakai untuk publication PKPA.

Browser QA menemukan kasus akun pembimbing demo tanpa `core_user_id`; scope published assignment dibuat defensif agar portal menampilkan empty state, bukan 500.
