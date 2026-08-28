# TAHAP 12A - Controlled Go-Live and Hypercare

## Ringkasan

Tahap 12A mencatat keputusan Project Owner untuk melewati Human UAT formal dan menyiapkan MY PKPA untuk controlled go-live dengan hypercare intensif. Dokumen release, rollback, backup, known limitations, incident matrix, hypercare log, dan quick start per role telah disiapkan.

Status akhir tahap ini: Persiapan selesai, deployment belum dilakukan.

## Status Resmi

- Human UAT: Waived by Project Owner
- Release approach: Controlled Go-Live
- Post-release mode: Intensive Hypercare

Human UAT formal dilewati berdasarkan keputusan Project Owner. Risiko diterima dengan syarat controlled go-live, monitoring ketat, backup, rollback, dan hypercare. Laporan ini tidak menyatakan klaim UAT diterima, selesai, atau disetujui pengguna.

## Target Environment

Target production/staging nyata belum diberikan di sesi ini.

- URL target: not tested
- HTTPS target: not tested
- Server target: not tested
- Database target: not tested
- Storage target: not tested
- Core Farmasi nyata target: not tested
- Queue target: not tested
- Scheduler target: not tested
- Mail production: not tested / direkomendasikan tetap off saat awal

## Pre-Deploy Gate

Gate lokal yang harus dijalankan sebelum deployment:

- Automated test: wajib pass.
- Playwright: wajib pass.
- Frontend build: wajib pass.
- Route list: wajib pass.
- Schedule list: wajib diperiksa.
- Queue health: wajib pass.
- Document orphan audit: wajib missing 0.
- Integrity audit: wajib issue 0.
- Hypercare status: wajib ok.

Gate target yang masih memblokir klaim go-live:

- Backup database dan storage target belum tersedia.
- HTTPS target belum diverifikasi.
- Core login akun nyata seluruh role belum diverifikasi.
- Queue worker target belum diverifikasi.
- Scheduler target belum dikonfigurasi sesuai task nyata.
- Post-deploy smoke test target belum dijalankan.

## Backup

Backup pre-go-live target belum dilakukan karena server target dan credential belum tersedia. Template pencatatan tersedia di `docs/releases/MY_PKPA_PRE_GO_LIVE_BACKUP_RECORD.md`.

Deployment tidak boleh dilanjutkan tanpa backup target yang dapat diverifikasi beserta checksum dan restore readiness.

## Deployment

Deployment belum dilakukan. Tidak ada commit, push, tag, merge, release, atau deployment server nyata pada Tahap 12A ini.

Remote yang harus dipakai bila Project Manager nanti mengizinkan push:

```text
https://github.com/farhamzah/kppspa-farmasi.git
```

## Core Login Nyata

Core login nyata target belum diuji pada Tahap 12A karena tidak ada URL/credential target. Role yang wajib diuji sebelum penggunaan awal:

- Admin PKPA
- Koordinator PKPA
- Mahasiswa
- Pembimbing Dalam
- Preseptor
- User multi-role bila ada

## Smoke Test

Smoke test target belum dilakukan. Checklist target tersedia di `docs/releases/MY_PKPA_POST_DEPLOY_SMOKE_RESULT.md`.

Smoke test lokal otomatis tetap dijalankan melalui PHPUnit dan Playwright sebelum status final tahap.

## Queue dan Scheduler

Queue dan scheduler target belum diverifikasi. Command monitoring yang tersedia:

```bash
php artisan pkpa:queue-health --json
php artisan pkpa:document-orphan-audit --json
php artisan pkpa:integrity-audit --json
php artisan pkpa:hypercare-status --json
php artisan queue:failed
php artisan schedule:list
```

Scheduler hanya perlu diaktifkan jika ada task nyata, idempotent, dan terdokumentasi.

## Monitoring

Hypercare awal direncanakan 14 hari kalender sejak go-live. Cadence:

- Hari 1-3: pagi, siang, sore.
- Hari 4-7: minimal dua kali sehari.
- Hari 8-14: harian.

Incident matrix tersedia di `docs/operations/MY_PKPA_HYPERCARE_INCIDENT_MATRIX.md`. Hypercare log tersedia di `docs/hypercare/MY_PKPA_HYPERCARE_LOG.md`.

## Incident

Karena deployment belum dilakukan, incident produksi/hypercare belum ada.

- P0: 0
- P1: 0
- P2: 0
- P3: 0
- Open: 0
- Closed: 0

## Known Limitations

Known limitations tersedia di `docs/releases/MY_PKPA_KNOWN_LIMITATIONS.md`. Kondisi utama: Human UAT formal dilewati, akun Core nyata belum diuji E2E target, HTTPS target belum diverifikasi, queue/scheduler target belum diverifikasi, email production belum diaktifkan, antivirus file scanning belum dibuktikan, dan backup-restore target belum dibuktikan.

## Dokumen Tahap 12A

- `docs/releases/MY_PKPA_HUMAN_UAT_WAIVER.md`
- `docs/releases/MY_PKPA_RELEASE_FREEZE_POLICY.md`
- `docs/releases/MY_PKPA_PRE_GO_LIVE_BACKUP_RECORD.md`
- `docs/releases/MY_PKPA_ROLLBACK_PLAN.md`
- `docs/releases/MY_PKPA_CONTROLLED_GO_LIVE_CHECKLIST.md`
- `docs/releases/MY_PKPA_RELEASE_NOTES.md`
- `docs/releases/MY_PKPA_KNOWN_LIMITATIONS.md`
- `docs/releases/MY_PKPA_POST_DEPLOY_SMOKE_RESULT.md`
- `docs/releases/MY_PKPA_HYPERCARE_PLAN.md`
- `docs/releases/MY_PKPA_HYPERCARE_CLOSURE_REPORT.md`
- `docs/operations/MY_PKPA_HYPERCARE_INCIDENT_MATRIX.md`
- `docs/hypercare/MY_PKPA_HYPERCARE_LOG.md`
- `docs/guides/MY_PKPA_QUICK_START_ADMIN.md`
- `docs/guides/MY_PKPA_QUICK_START_COORDINATOR.md`
- `docs/guides/MY_PKPA_QUICK_START_STUDENT.md`
- `docs/guides/MY_PKPA_QUICK_START_INTERNAL_SUPERVISOR.md`
- `docs/guides/MY_PKPA_QUICK_START_FIELD_SUPERVISOR.md`

## QA

Hasil QA final Tahap 12A pada environment lokal:

```text
php artisan optimize:clear: pass
php artisan migrate --seed --force: pass, nothing to migrate, seeder idempotent
php artisan test --stop-on-failure: pass, 298 passed, 2062 assertions
npm.cmd run build: pass
php artisan route:list --name=pkpa: pass, 215 routes
php artisan schedule:list: pass, no scheduled tasks defined
php artisan pkpa:queue-health --json: pass, failed_jobs 0, document_jobs.failed 0
php artisan pkpa:document-orphan-audit --json: pass, checked_versions 1, missing_count 0
php artisan pkpa:integrity-audit --json: pass, 25 checks, issue_count 0
php artisan pkpa:hypercare-status --json: pass, status ok
npx.cmd playwright test --reporter=list: pass, 16 passed
```

Clean installation menggunakan SQLite temporary `C:\tmp\my_pkpa_tahap12a_clean.sqlite` dan berhasil menjalankan migration plus seeder. `migrate:fresh` tidak dijalankan pada MySQL.

## Git dan Deployment Status

- Commit: tidak dilakukan.
- Push: tidak dilakukan.
- Tag: tidak dibuat.
- Merge: tidak dilakukan.
- Deployment: tidak dilakukan.
- Server lokal E2E: sudah dihentikan setelah Playwright selesai.
- `apps/core-farmasi`: tidak diubah.

## Rekomendasi

Lanjutkan deployment hanya setelah Project Manager memberi izin eksplisit, target server/credential tersedia, backup target valid, HTTPS aktif, Core login nyata lulus, dan smoke test target siap dijalankan. Jika setelah go-live muncul P0/P1, perpanjang hypercare atau rollback sesuai dampak.

