# MY PSPA Controlled Go-Live Checklist

Status lokal: preparation complete, deployment not performed.

Application gate:
- [x] PHPUnit hijau
- [x] Playwright hijau
- [x] Build hijau
- [x] Integrity audit issue 0
- [x] Document orphan audit missing 0
- [x] Route list berhasil
- [ ] Config cache target production dibuat
- [ ] Debug tool target dipastikan nonaktif

Environment gate:
- [ ] Target `APP_ENV=production` atau staging disepakati
- [ ] `APP_DEBUG=false`
- [ ] `APP_URL` benar
- [ ] `APP_KEY` tersedia
- [ ] HTTPS aktif
- [ ] trusted proxy benar
- [ ] secure cookie aktif
- [ ] private storage writable

Database gate:
- [ ] target database teridentifikasi
- [ ] backup target tersedia
- [ ] checksum backup tersedia
- [ ] migration status target diperiksa
- [ ] privilege DB minimum diverifikasi

Core Farmasi gate:
- [ ] URL Core target benar
- [ ] client credential target tersedia via env/secret
- [ ] connectivity berhasil
- [ ] login pengguna nyata berhasil
- [ ] role dan application access dibaca
- [ ] logout bekerja

Queue/Scheduler:
- [ ] queue target tersedia
- [ ] worker target berjalan
- [ ] failed jobs dimonitor
- [ ] scheduler task nyata dikonfigurasi bila ada

Monitoring:
- [x] command hypercare tersedia
- [ ] `/health` target berhasil
- [ ] `/admin/health` target protected
- [ ] log target writable
- [ ] disk target cukup

Decision:
- [ ] Project Owner menyetujui controlled go-live
- [x] Project Manager mengizinkan commit/push ke remote MY PSPA
- [ ] Project Manager mengizinkan deployment ke server target
- [x] Remote Git dikonfirmasi sesuai `https://github.com/farhamzah/kppspa-farmasi.git`
- [ ] Backup selesai
- [ ] Rollback siap
- [ ] Hypercare dimulai
