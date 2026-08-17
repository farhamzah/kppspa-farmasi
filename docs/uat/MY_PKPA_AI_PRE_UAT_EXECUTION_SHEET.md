# MY PKPA AI Pre-UAT Execution Sheet

| Area | Skenario | Metode | Hasil | Evidence |
|---|---|---|---|---|
| Regression | Semua feature test PKPA dan legacy terkait | `php artisan test --stop-on-failure` | Pass | 298 passed, 2062 assertions |
| Build | Asset frontend production | `npm.cmd run build` | Pass | Manifest dan asset Vite terbentuk |
| Migration | MySQL migrate dan seed | `php artisan migrate --seed --force` | Pass | Tidak ada migration error |
| Clean install | SQLite clean database | `migrate:fresh --seed --force` pada `C:\tmp\my_pkpa_tahap11a_clean.sqlite` | Pass | Semua migration dan seeder selesai |
| Role access | Admin/Koordinator/Mahasiswa/Pembimbing/Penguji route access | Feature tests | Pass | Forbidden dan OK sesuai role |
| Guest | Landing, login, protected redirect | Browser smoke | Pass | Login visible, no console error |
| Admin | Dashboard dan dokumen PKPA | Browser smoke | Pass | No server error, no overflow |
| Data integrity | 25 check integritas PKPA | `pkpa:integrity-audit --json` | Pass | issue_count 0 |
| Queue | Queue connection dan failed jobs | `pkpa:queue-health --json` | Pass | failed_jobs 0 |
| Document files | Orphan audit dokumen | `pkpa:document-orphan-audit --json` | Pass | missing_count 0 |
| Backup restore | Copy backup/restore SQLite dan hash | `Get-FileHash`, integrity audit restored DB | Pass | Hash sama, integrity pass |
| Scheduler | Daftar task | `php artisan schedule:list` | Pass with note | Belum ada scheduled task |
| Browser viewport | Desktop/tablet/mobile requested | Browser plugin smoke | Partial | Plugin tetap 1280px; perlu rerun Playwright mandiri |
