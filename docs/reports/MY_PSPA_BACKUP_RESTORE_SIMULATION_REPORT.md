# MY PSPA Backup Restore Simulation Report

Tanggal: 2026-07-17

Metode:
- Clean install SQLite dibuat di `C:\tmp\my_pspa_tahap11a_clean.sqlite`.
- File disalin ke `C:\tmp\my_pspa_tahap11a_clean_backup.sqlite`.
- Backup disalin ke `C:\tmp\my_pspa_tahap11a_restore.sqlite`.
- SHA256 dibandingkan.
- Integrity audit dijalankan pada database restore.

Hasil:
- Hash clean, backup, dan restore sama: `25208F6F567DFE875C6DAC39FD2D44133D595AFFC258AF4BA69BD70316AFA7D3`.
- `php artisan pkpa:integrity-audit --json` pada restore: passed, issue_count 0.

Batasan:
- Simulasi ini tidak melakukan restore ke MySQL `sikp_farmasi_ubp`.
- Simulasi storage file production dan object storage belum dilakukan.
