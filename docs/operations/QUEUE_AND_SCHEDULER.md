# MY PSPA Queue and Scheduler

Job Tahap 10:
- `GeneratePkpaDocumentJob`

Command monitoring:
- `php artisan pkpa:queue-health`
- `php artisan pkpa:queue-health --json`
- `php artisan pkpa:document-orphan-audit`
- `php artisan pkpa:integrity-audit --json`

Prinsip:
- job idempotent berbasis `generation_key`;
- dispatch after commit;
- retry tidak membuat dokumen ganda;
- failure log disanitasi;
- email failure tidak rollback publication.

Scheduler production perlu mencatat last-run melalui mekanisme platform atau monitor eksternal. Belum ada claim scheduler production sudah diuji.

Catatan Tahap 11A:
- `queue_connection=sync` pada environment lokal.
- failed jobs 0.
- document jobs queued/running/failed/finished 0 saat audit.
- `schedule:list` belum menampilkan scheduled task aktif.

Catatan Tahap 11B:
- Queue async database diuji dengan `GeneratePkpaDocumentJob`.
- Worker nyata: `php artisan queue:work database --once --tries=3 --timeout=120`.
- Hasil: job selesai, failed jobs 0, document_jobs.finished 1.
- Scheduler command lulus, tetapi belum ada scheduled task aktif.
