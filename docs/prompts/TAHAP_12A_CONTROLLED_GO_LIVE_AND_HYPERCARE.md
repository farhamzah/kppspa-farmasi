# Prompt Tahap 12A - Controlled Go-Live and Hypercare

Tahap 12A dijalankan dengan keputusan Project Owner:

```text
Human UAT: Waived by Project Owner
Release approach: Controlled Go-Live
Post-release mode: Intensive Hypercare
```

Human UAT formal tidak dilaksanakan sebelum penggunaan awal. Aplikasi disiapkan untuk controlled go-live dengan monitoring ketat, backup, rollback, release freeze, incident response, dan hypercare 14 hari kalender.

Batas penting:

- Jangan menyatakan klaim UAT diterima, selesai, atau disetujui pengguna.
- Jangan commit, push, tag, merge, release, atau deployment tanpa instruksi Project Manager.
- Jangan mengubah `apps/core-farmasi`.
- Jangan mengarang deployment, smoke test target, monitoring target, atau stabilitas hypercare.
- Jika server target atau credential tidak tersedia, selesaikan persiapan dan laporkan bahwa deployment belum dilakukan.

Gate QA yang wajib dijalankan sebelum go-live:

```text
php artisan optimize:clear
php artisan migrate --seed --force
php artisan test --stop-on-failure
npm.cmd run build
php artisan route:list --name=pkpa
php artisan schedule:list
php artisan pkpa:queue-health --json
php artisan pkpa:document-orphan-audit --json
php artisan pkpa:integrity-audit --json
npx playwright test
```

Clean installation hanya boleh memakai SQLite temporary. Jangan menjalankan `migrate:fresh` pada MySQL development atau target.
