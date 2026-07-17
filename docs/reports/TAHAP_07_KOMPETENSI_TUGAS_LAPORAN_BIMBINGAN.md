# TAHAP 07 - Kompetensi, Tugas, Laporan, Bimbingan, dan Readiness Akademik

## Ringkasan

Tahap 07 menambahkan modul akademik rotasi di atas runtime PKPA: master kompetensi, checklist snapshot, evidence, review Pembimbing Lapangan, monitoring Pembimbing Dalam, template tugas khusus, submission berversi, laporan rotasi berversi, bimbingan, readiness akademik, record notifikasi akademik, export, route, menu, UI, dan test.

## Database Baru

- `pkpa_competency_sets`, `pkpa_competency_categories`, `pkpa_competency_items`
- `pkpa_rotation_competency_records`, `pkpa_rotation_competency_evidences`, `pkpa_rotation_competency_reviews`
- `pkpa_special_task_templates`, `pkpa_rotation_special_tasks`, `pkpa_special_task_submissions`, `pkpa_special_task_reviews`
- `pkpa_rotation_report_templates`, `pkpa_rotation_reports`, `pkpa_rotation_report_versions`
- `pkpa_rotation_guidance_sessions`
- `pkpa_rotation_academic_readiness_reviews`

## Notifikasi dan Audit

Event akademik utama seperti submit kompetensi, review kompetensi, penugasan, submit tugas, review tugas, submit laporan, konfirmasi laporan, review laporan, bimbingan, dan readiness membuat record idempotent di `pkpa_notification_deliveries` sesuai konfigurasi `PKPA_ACADEMIC_DATABASE_NOTIFICATIONS_ENABLED` dan `PKPA_ACADEMIC_EMAIL_NOTIFICATIONS_ENABLED`.

## QA Awal

- `php artisan test --filter=Tahap07PkpaAcademicRotationTest --stop-on-failure`: passed, 4 tests, 24 assertions.

## Batasan

Belum ada skor numerik, rubrik, bobot, nilai akhir, huruf mutu, sertifikat, tanda tangan digital, plagiarism checker, WhatsApp, pembayaran, war, atau migrasi legacy `kp_*`.
