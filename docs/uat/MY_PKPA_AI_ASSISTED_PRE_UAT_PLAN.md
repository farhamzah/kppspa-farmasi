# MY PKPA AI-Assisted Pre-UAT Plan

Tanggal eksekusi: 2026-07-17

Scope:
- Guest, Admin PKPA, Koordinator PKPA, Mahasiswa, Pembimbing Dalam, Pembimbing Lapangan, Penguji bila tersedia, multi-role, no-access, dan inactive account.
- Alur utama PKPA Tahap 00 sampai Tahap 10.
- Regression, browser smoke, role access, data integrity, queue/document audit, backup-restore simulation, UX/accessibility smoke.

Metode:
- Automated feature test: `php artisan test --stop-on-failure`.
- Build asset: `npm.cmd run build`.
- Route exposure: `php artisan route:list --name=pkpa`.
- Queue/document health: `php artisan pkpa:queue-health --json` dan `php artisan pkpa:document-orphan-audit --json`.
- Data integrity: `php artisan pkpa:integrity-audit --json`.
- Clean install: SQLite sementara `C:\tmp\my_pkpa_tahap11a_clean.sqlite`.
- Browser smoke: landing page, login page, admin session, protected route redirect, console error, dan overflow horizontal.

Kriteria blocker:
- Test suite gagal.
- Build gagal.
- Critical/High issue pada integrity audit.
- Protected route bocor ke role tidak berhak.
- Health route membocorkan detail sensitif.
- Dokumen private dapat diunduh tanpa ownership/role yang benar.
