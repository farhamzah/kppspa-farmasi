# MY PKPA MySQL Restore Simulation Report

Tanggal: 2026-07-17

Tooling:
- `mysql.exe` dan `mysqldump.exe` tidak tersedia di PATH.
- Simulasi memakai Laravel/PDO tanpa menulis password ke command report.

Database:
- source: database development lokal.
- target disposable: `sikp_farmasi_ubp_restore_test`.

Metode:
- `CREATE DATABASE IF NOT EXISTS`.
- Clone table dengan `CREATE TABLE target.table LIKE source.table`.
- Copy data dengan `INSERT INTO target.table SELECT * FROM source.table`.
- Jalankan `migrate:status`, `pkpa:integrity-audit --json`, dan `pkpa:document-orphan-audit --json`.
- Drop database disposable setelah hasil dicatat.

Count comparison:
- users: 9 = 9
- pkpa_practice_domains: 6 = 6
- pkpa_document_types: 11 = 11
- pkpa_generated_documents: 1 = 1
- pkpa_notification_deliveries: 1 = 1

Result:
- migration status: pass
- integrity audit: passed, issue_count 0
- document orphan audit: missing_count 0

Batasan:
- Ini bukan restore production dan bukan pengganti backup policy production.
