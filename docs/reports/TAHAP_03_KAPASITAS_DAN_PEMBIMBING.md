# TAHAP 03 - Kapasitas, Pembimbing, dan Readiness PKPA

## Ringkasan

Tahap 03 menambahkan fondasi sebelum penyusunan penempatan: tempat tersedia per program, availability period, kapasitas, Preseptor dari Core, Pembimbing Dalam dari Core, unavailability pembimbing, sync log, dashboard, dan checklist readiness.

## File Penting

- Migration: `2026_07_17_030001_create_pkpa_site_capacity_supervisor_tables.php`
- Model: `PkpaProgramSite`, `PkpaSiteAvailabilityPeriod`, `PkpaSiteFieldSupervisor`, `PkpaInternalSupervisorEligibility`, `PkpaSupervisorUnavailabilityPeriod`, `PkpaSupervisorSyncLog`
- Service: `PkpaProgramSiteService`, `PkpaSiteAvailabilityService`, `PkpaFieldSupervisorService`, `PkpaInternalSupervisorService`, `PkpaSupervisorAvailabilityService`, `PkpaSupervisorCoreResolver`, `PkpaSupervisorCoreSyncService`, `PkpaPlacementReadinessService`
- Controller: `PkpaProgramSiteController`, `PkpaInternalSupervisorController`, `PkpaPlacementReadinessController`
- View: `management/pkpa-program-sites`, `management/pkpa-internal-supervisors`, `management/pkpa-placement-readiness`
- Test: `Tahap03PkpaCapacitySupervisorTest`

## Guardrail

- Tidak membuat akun lokal, password, atau role lokal pembimbing.
- Tidak memakai Core production pada test.
- Tidak membuat penempatan mahasiswa atau matrix rotasi.
- Tidak mengubah tabel `kp_*`.
- Snapshot lama tidak dikosongkan saat Core gagal sync.

## QA

- `php -l` file Tahap 03: lulus.
- `php artisan test --filter=Tahap03PkpaCapacitySupervisorTest`: 5 passed, 81 assertions.
- `php artisan optimize:clear`: lulus.
- `php artisan migrate --seed --force`: lulus, hanya migrasi biasa untuk menerapkan tabel Tahap 03 tanpa wipe data.
- `php artisan route:list --path=management/pkpa`: lulus, 69 route PKPA terdaftar.
- `php artisan test`: 271 passed, 1784 assertions.
- `npm.cmd run build`: lulus.
- Browser QA lokal `http://127.0.0.1:8000`: halaman `pkpa-program-sites`, `pkpa-internal-supervisors`, dan `pkpa-placement-readiness` terbuka pada desktop dan mobile tanpa server error, console error, atau overflow body.

## Catatan QA

`php artisan migrate:fresh --seed --force` tidak dijalankan karena `.env` lokal mengarah ke database SQLite `docs/manuals/manual.sqlite`, bukan database disposable/testing yang dikonfirmasi aman untuk wipe. Sebagai gantinya dijalankan `php artisan migrate --seed --force` agar tabel Tahap 03 masuk tanpa menghapus data.

