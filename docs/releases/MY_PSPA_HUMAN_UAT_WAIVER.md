# MY PSPA Human UAT Waiver

Status:
- Human UAT: Waived by Project Owner
- Release approach: Controlled Go-Live
- Post-release mode: Intensive Hypercare

Keputusan:
Human UAT formal dilewati berdasarkan keputusan Project Owner. Risiko diterima dengan syarat controlled go-live, monitoring ketat, backup, rollback, dan hypercare.

Alasan:
- Aplikasi perlu segera digunakan secara terkontrol.
- Baseline teknis Tahap 11B lulus tanpa Critical/High defect terbuka.
- Validasi operasional nyata akan dilakukan melalui intensive hypercare.

Risiko:
- Feedback pengguna formal belum dikumpulkan sebelum penggunaan awal.
- Akun Core nyata seluruh role belum diuji melalui E2E pada server target.
- Server HTTPS target, queue worker target, scheduler target, dan backup target belum diverifikasi dalam sesi lokal ini.
- Email production dapat tetap nonaktif sampai sandbox/SMTP diverifikasi.

Kondisi go-live:
- Tidak ada Critical/High defect terbuka.
- Backup target tersedia dan dapat diverifikasi.
- Rollback plan tersedia.
- Core Farmasi target dapat diakses.
- Login role utama berhasil pada target.
- Health, queue, storage, dan audit command lulus.
- Monitoring harian dan incident log aktif selama 14 hari kalender.

Monitoring:
- `php artisan pkpa:hypercare-status --json`
- `php artisan pkpa:queue-health --json`
- `php artisan pkpa:document-orphan-audit --json`
- `php artisan pkpa:integrity-audit --json`
- `php artisan queue:failed`
- `php artisan schedule:list`

Rollback:
- Ikuti `docs/releases/MY_PSPA_ROLLBACK_PLAN.md`.
- Trigger P0/P1 wajib dievaluasi untuk maintenance mode, feature flag, rollback aplikasi, atau restore backup.

Tanggung jawab:
- Project Owner: menerima risiko waiver dan menyetujui controlled go-live.
- Tim IT/DevOps: environment, backup, queue, scheduler, monitoring.
- Admin/Koordinator: validasi operasional awal dan pelaporan incident.
- Developer: hotfix terkontrol dengan regression test.

Known limitations:
- Lihat `docs/releases/MY_PSPA_KNOWN_LIMITATIONS.md`.

Persetujuan Project Owner:
| Nama | Jabatan | Keputusan | Tanggal | Tanda tangan/Catatan |
|---|---|---|---|---|
| | | | | |

Catatan:
Dokumen ini belum berisi tanda tangan atau persetujuan final karena belum diberikan di repository.
