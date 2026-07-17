# MY PSPA Async Queue Scheduler Report

Queue:
- Driver test: database.
- Worker: `php artisan queue:work database --once --tries=3 --timeout=120`.
- Job: `App\Jobs\GeneratePkpaDocumentJob`.
- Result: DONE.
- Failed jobs: 0.
- `pkpa:queue-health --json`: finished 1, failed 0.

Scheduler:
- `php artisan schedule:list`: no scheduled tasks defined.
- `php artisan schedule:run -v`: no scheduled commands ready.

Kesimpulan:
- Queue async lokal/staging-safe lulus.
- Scheduler command lulus, tetapi scheduler production belum bisa dinilai karena belum ada task aktif.
