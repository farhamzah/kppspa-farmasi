# TAHAP 11A - AI-Assisted Pre-UAT

Ringkasan instruksi:
- Bertindak sebagai QA lead, security reviewer, data integrity auditor, dan release readiness reviewer untuk MY PKPA.
- Jalankan regression otomatis, browser E2E smoke, audit role access, audit integritas data, queue/document audit, build, clean install SQLite, dan simulasi backup-restore.
- Jangan mengubah Core Farmasi, jangan deploy, jangan commit/push/tag/merge/release.
- Jangan menjalankan `migrate:fresh` pada MySQL; clean install hanya di SQLite sementara.
- Dokumentasikan hasil dalam format yang siap diserahkan ke human UAT.
- Status akhir harus salah satu dari: "Layak masuk UAT pengguna", "Layak masuk UAT pengguna dengan catatan", atau "Belum layak masuk UAT pengguna".
