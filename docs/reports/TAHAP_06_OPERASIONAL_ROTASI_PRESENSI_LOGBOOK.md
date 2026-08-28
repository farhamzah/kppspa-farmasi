# TAHAP 06 - Operasional Rotasi, Presensi, dan Logbook PKPA

## Ringkasan

Tahap ini menambahkan runtime operasional PKPA dari publikasi resmi, aturan operasional per wahana, presensi, koreksi presensi, logbook, lampiran privat, review Preseptor, monitoring Pembimbing Dalam, snapshot progress, sinkronisasi publikasi, route, menu, dan UI per role.

## File Penting

- Migration: `2026_07_17_060001_create_pkpa_rotation_operation_tables.php`
- Service: `PkpaRotationRunService`, `PkpaAttendanceService`, `PkpaLogbookService`, `PkpaRotationProgressService`, `PkpaRotationPublicationSyncService`
- Controller: `Management/Student/FieldSupervisor/InternalSupervisor/PkpaRotationOperationController`
- View: `resources/views/*/pkpa-operations`
- Test: `tests/Feature/Tahap06PkpaRotationOperationTest.php`

## QA

- `php artisan test --filter=Tahap06PkpaRotationOperationTest --stop-on-failure`: passed, 4 tests, 27 assertions.

## Batasan

Belum mencakup nilai, rubrik, sertifikat, GPS/QR/biometrik, WhatsApp, pembayaran, war, atau migrasi legacy `kp_*`.

