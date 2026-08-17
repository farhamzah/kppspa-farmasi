# MY PKPA Deployment Guide

Checklist teknis:
- PHP 8.2+, ekstensi PDO, OpenSSL, mbstring, fileinfo, zip.
- `composer install --no-dev --optimize-autoloader`.
- `npm ci` lalu `npm run build`.
- Set `APP_KEY`, `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://...`.
- Set database, cache, session, queue, mail, dan Core Farmasi tanpa credential di repository.
- Jalankan backup database dan private storage sebelum migrate.
- Jalankan `php artisan optimize:clear`, `php artisan migrate --force`, lalu smoke test.
- Scheduler: `php artisan schedule:run` setiap menit.
- Queue worker sesuai driver production.
- Private disk harus writable dan tidak dipublish sebagai public URL.
- Health: `/health` public minimal, `/admin/health` protected.

Rollback: aktifkan maintenance mode, restore DB/file backup bila migration/data bermasalah, deploy build sebelumnya, lalu ulang smoke test.
# Catatan Tahap 11A

Sebelum deployment staging/production, jalankan ulang command gate berikut pada environment target:

```bash
php artisan optimize:clear
php artisan migrate --seed --force
php artisan test --stop-on-failure
npm.cmd run build
php artisan pkpa:queue-health --json
php artisan pkpa:document-orphan-audit --json
php artisan pkpa:integrity-audit --json
```

Tahap 11A bukan approval production. Human UAT dan approval owner tetap diperlukan.

## Catatan Tahap 11B

Sebelum staging Human UAT:
- Jalankan Playwright E2E dengan `E2E_BASE_URL` staging.
- Gunakan akun Core test nyata melalui environment `E2E_*`.
- Pastikan `APP_ENV=staging`, `APP_DEBUG=false`, HTTPS aktif, cookie secure, queue worker aktif, scheduler cron aktif, dan mailer memakai sandbox.
- Jangan memakai akun demo lokal untuk production.
