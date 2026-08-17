# MY PKPA Environment Checklist

Wajib dicek:
- `APP_ENV`
- `APP_DEBUG=false`
- `APP_URL` HTTPS
- `APP_KEY`
- `DB_*`
- `CACHE_STORE`
- `SESSION_DRIVER`
- `QUEUE_CONNECTION`
- `MAIL_*`
- `CORE_FARMASI_*` dan `KP_CORE_*`
- `PKPA_*`
- `FILESYSTEM_DISK`
- `LOG_CHANNEL`
- `TRUSTED_PROXIES`

Production gate:
- secure cookie aktif pada HTTPS;
- queue dan scheduler berjalan;
- private storage writable;
- public storage tidak membuka file akademik;
- Core reachable;
- backup database dan private file tersedia;
- mail feature flag hanya aktif bila SMTP sudah diuji.
# Catatan Tahap 11B

Checklist minimum staging Human UAT:
- `APP_ENV=staging`
- `APP_DEBUG=false`
- `APP_URL=https://...`
- `SESSION_SECURE_COOKIE=true`
- `SESSION_SAME_SITE=lax`
- `QUEUE_CONNECTION=database` atau `redis`
- `MAIL_MAILER=sandbox` atau `log`
- `CORE_FARMASI_URL` mengarah ke Core staging/testing
- `CORE_FARMASI_CLIENT_ID` dan `CORE_FARMASI_CLIENT_SECRET` berasal dari secret manager/env, bukan repository
- `FILESYSTEM_DISK` private
- storage writable
- queue worker berjalan
- scheduler cron berjalan
- backup database tersedia
- log rotation tersedia
- health check tidak membocorkan secret
