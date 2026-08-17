# MY PKPA Backup and Restore

Backup scope:
- database aplikasi;
- private storage `storage/app/private`;
- template dokumen;
- generated document output;
- file akademik;
- konfigurasi environment di secret manager.

Rekomendasi:
- enkripsi backup;
- checksum SHA-256;
- offsite backup;
- akses terbatas;
- restore test berkala;
- catat RPO/RTO setelah disepakati institusi.

Jangan hardcode password. Tahap 10 tidak menjalankan backup production nyata.
# Catatan Simulasi Tahap 11A

Simulasi backup-restore aman dilakukan pada SQLite lokal:
- source: `C:\tmp\my_pkpa_tahap11a_clean.sqlite`
- backup: `C:\tmp\my_pkpa_tahap11a_clean_backup.sqlite`
- restore: `C:\tmp\my_pkpa_tahap11a_restore.sqlite`
- SHA256 sama: `25208F6F567DFE875C6DAC39FD2D44133D595AFFC258AF4BA69BD70316AFA7D3`
- `php artisan pkpa:integrity-audit --json` pada restore: passed.

Simulasi ini tidak menggantikan prosedur backup/restore MySQL production dan storage file production.

## Catatan MySQL Disposable Tahap 11B

Karena `mysql.exe` dan `mysqldump.exe` tidak tersedia di PATH, simulasi dilakukan lewat Laravel/PDO:
- database target disposable: `sikp_farmasi_ubp_restore_test`
- metode: clone table dengan `CREATE TABLE target LIKE source` dan `INSERT INTO target SELECT * FROM source`
- count tabel kunci cocok
- `migrate:status` pada target lulus
- `pkpa:integrity-audit --json` pada target lulus
- `pkpa:document-orphan-audit --json` pada target lulus
- database disposable dihapus setelah hasil dicatat
