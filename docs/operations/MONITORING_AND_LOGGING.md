# MY PKPA Monitoring and Logging

Log perlu dipisahkan secara operasional:
- application log;
- security log;
- integration log;
- business audit;
- failed job.

Data yang harus di-redact:
- password;
- token;
- authorization header;
- cookie/session ID;
- raw Core response;
- file content;
- catatan sensitif.

Tahap 10 menambahkan redaction failure message job dokumen dan health endpoint aman. Integrasi SIEM/log aggregator production belum diuji.
# Catatan Monitoring Tahap 11A

Monitoring command yang wajib tersedia untuk PKPA:
- `php artisan pkpa:queue-health --json`
- `php artisan pkpa:document-orphan-audit --json`
- `php artisan pkpa:integrity-audit --json`

Health public `/health` boleh dipakai untuk uptime smoke, tetapi tidak boleh membocorkan nama database, host internal, path, token, secret, atau stack trace.

Untuk production, tambahkan monitor eksternal untuk failed jobs, scheduler last-run, storage usage, dan error log Laravel.
