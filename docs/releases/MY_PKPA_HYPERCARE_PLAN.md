# MY PKPA Hypercare Plan

Mode: Intensive Hypercare.

Durasi awal:
- 14 hari kalender sejak go-live.
- Dapat diperpanjang jika ada P0/P1 atau pola incident berulang.

Cadence:
- Hari 1-3: pagi, siang, sore.
- Hari 4-7: minimal dua kali sehari.
- Hari 8-14: harian.

Monitoring harian:
- `/health`
- `/admin/health`
- `php artisan pkpa:hypercare-status --json`
- `php artisan pkpa:queue-health --json`
- `php artisan pkpa:document-orphan-audit --json`
- `php artisan pkpa:integrity-audit --json`
- `php artisan queue:failed`
- `php artisan schedule:list`
- Laravel log
- disk usage
- database connectivity
- Core connectivity
- login failure
- upload/download failure
- document generation failure

Aktivitas bisnis kritis:
- peserta baru;
- placement;
- publication;
- runtime;
- presensi;
- logbook;
- kompetensi;
- laporan;
- nilai;
- kelulusan;
- dokumen.

Output:
- update `docs/hypercare/MY_PKPA_HYPERCARE_LOG.md`;
- hotfix hanya untuk defect terklasifikasi;
- closure report setelah periode selesai.
