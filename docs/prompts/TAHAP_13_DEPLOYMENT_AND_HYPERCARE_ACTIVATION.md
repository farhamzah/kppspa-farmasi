# Prompt Tahap 13 - Deployment and Hypercare Activation

Tahap 13 meminta audit Git, pemeriksaan file sensitif, commit/push terarah jika diizinkan, deployment target jika diizinkan, smoke test pascadeploy, dan aktivasi hypercare.

Gate wajib:

- Commit dan push hanya boleh dilakukan jika Project Manager secara eksplisit mengizinkan commit dan push MY PSPA ke repository GitHub.
- Deployment hanya boleh dilakukan jika Project Manager secara eksplisit mengizinkan deployment MY PSPA ke server target dan seluruh detail target tersedia.
- Jangan mengubah `apps/core-farmasi`.
- Jangan mengubah remote tanpa konfirmasi Project Manager.
- Jangan membuat tag, merge, release, force push, atau deployment tanpa izin.
- Jangan mengklaim deployment, controlled go-live, smoke test target, atau hypercare aktif jika belum benar-benar dilakukan.

Remote tujuan yang diminta:

```text
https://github.com/farhamzah/kppspa-farmasi.git
```

QA sebelum commit/deployment:

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
php artisan pkpa:hypercare-status --json
npx playwright test
composer validate
```

Status Tahap 13 pada eksekusi ini: Git preparation selesai, belum push. Deployment belum dilakukan.
