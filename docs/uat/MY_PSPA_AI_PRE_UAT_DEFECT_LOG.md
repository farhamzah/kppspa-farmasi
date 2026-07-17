# MY PSPA AI Pre-UAT Defect Log

| ID | Severity | Status | Area | Ringkasan | Resolusi |
|---|---|---|---|---|---|
| DEF-11A-001 | High | Closed | Data integrity | Command `pkpa:integrity-audit --json` belum tersedia sebelum Tahap 11A. | Ditambahkan command dry-run dengan 25 check dan regression test. |
| DEF-11A-002 | Low | Open note | Browser tooling | Browser plugin tidak menerapkan viewport 390x844/768x1024 secara presisi pada sesi ini. | Rerun mobile/tablet dengan Playwright mandiri atau browser manual saat human UAT. |
| DEF-11A-003 | Low | Open note | Email | Email produksi tidak dikirim pada environment lokal. | Validasi SMTP/log mailer di staging sebelum production. |
| DEF-11A-004 | Low | Open note | Scheduler | Belum ada scheduled task aktif. | Tambahkan monitor scheduler ketika task production resmi tersedia. |

Tidak ada Critical/High open defect pada akhir Tahap 11A.
