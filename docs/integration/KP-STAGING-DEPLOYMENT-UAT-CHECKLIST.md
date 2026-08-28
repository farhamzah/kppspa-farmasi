# KP Staging Deployment & UAT Checklist

Tanggal: 2026-06-03

## Command Rehearsal

```bash
php artisan kp:staging-rehearsal-check
```

Report JSON opsional:

```bash
php artisan kp:staging-rehearsal-check --report-json
```

## Pre-Deploy Staging
- Pull branch release/main yang sudah disetujui.
- Buat `.env` VPS dari `.env.vps.example`; jangan commit `.env` server.
- Pastikan `.env` staging tidak memakai secret production bila belum go-live.
- Pastikan `APP_DEBUG=false`.
- Pastikan `APP_URL` HTTPS.
- Pastikan `KP_AUTH_MODE=core_bridge_with_legacy_fallback`.
- Pastikan `KP_MASTER_DATA_READ_MODE=core_preferred`.
- Pastikan `KP_CORE_HTTP_ENABLED=false` kecuali Core staging sudah memberi app-client credential resmi.
- Pastikan database KP staging sudah dibackup sebelum migration.
- Pastikan Core/TU/SAFA database tidak menjadi target write dari KP.
- Pastikan endpoint runtime TU/SAFA belum diisi sampai approval gate final.

## Deploy Steps
1. `composer install --no-dev --optimize-autoloader`
2. `npm ci`
3. `npm run build`
4. `php artisan migrate --force`
5. `php artisan optimize:clear`
6. `php artisan config:cache`
7. `php artisan route:cache`
8. `php artisan view:cache`
9. `php artisan kp:staging-rehearsal-check`
10. `php artisan kp:core-mode-preflight --auth-mode=core_bridge_with_legacy_fallback --master-data-mode=core_preferred --show-samples`
11. `php artisan kp:core-mapping-coverage`
12. `php artisan kp:production-readiness-gate`

Catatan:
- Untuk VPS staging, `kp:production-readiness-gate` boleh gagal bila email masih `log` atau data Core UAT belum lengkap, tetapi blocker environment HTTPS/debug/session/master-data harus diselesaikan.
- Jika `kp:core-mode-preflight` masih `WARN`, jalankan `php artisan kp:sync-core-mapping --dry-run --show-samples` dan koordinasikan profil student/lecturer yang belum tersedia di Core.

## UAT Scenarios
- Admin login, role selection, dashboard.
- Koordinator login, role selection, dashboard.
- Mahasiswa pendaftaran KP.
- Upload dan verifikasi berkas KP.
- Pemilihan tempat KP dan daftar tunggu.
- Penempatan KP dan assignment pembimbing.
- Logbook mahasiswa dan validasi preseptor.
- Laporan akhir dan review pembimbing dalam.
- Pengajuan sidang, jadwal sidang, berita acara, penilaian.
- Rekap dan export management.
- Review integrasi TU payload.
- Review SAFA public info preview.
- External document reference draft dan manual linking.
- Core mapping coverage.
- Core Bridge provisioning dry-run.

## Guardrails
- Tidak ada write ke Core.
- Tidak ada write ke TU.
- Tidak ada write ke SAFA.
- Tidak ada HTTP request nyata ke TU/SAFA.
- Tidak ada auto-sync.
- Tidak ada SSO/autologin/token URL.
- Tidak ada duplicate upload dokumen.
- Tidak menyimpan token/password/secret/signed URL/path internal.

## Sign-Off
Sebelum production:
- Admin akademik sign-off.
- Koordinator KP sign-off.
- TU sign-off untuk kontrak dokumen, tanpa auto-sync dulu.
- SAFA sign-off untuk whitelist public info.
- Tim teknis sign-off untuk backup/rollback.
- Tim teknis sign-off untuk readiness gate tanpa blocker.

## Production Handoff
Setelah UAT staging lulus, lanjutkan ke runbook production:

```text
docs/integration/KP-PRODUCTION-DEPLOYMENT-RUNBOOK.md
```

Gunakan `.env.production.example` sebagai template aman. Jangan commit `.env`, `.env.production`, password database, token, atau secret server.

