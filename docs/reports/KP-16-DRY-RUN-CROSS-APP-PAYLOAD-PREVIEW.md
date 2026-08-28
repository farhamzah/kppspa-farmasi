# KP-16 - Dry-Run Cross-App Payload Preview for TU and SAFA

Tanggal: 2026-06-01

## Ringkasan
KP-16 menambahkan preview payload dry-run untuk dua jalur integrasi masa depan:
- dokumen administrasi KP ke TU;
- informasi publik KP ke SAFA.

Tahap ini tidak membuat write bridge aktif, tidak mengirim HTTP request ke TU/SAFA, tidak membuat SSO/autologin/token URL, tidak membuat migration reference table, dan tidak menulis ke Core/TU/SAFA.

## File Dibuat/Diubah
Dibuat:
- `app/Services/Integration/KpTuDocumentPayloadPreviewService.php`
- `app/Services/Integration/KpSafaPublicInfoPreviewService.php`
- `app/Console/Commands/TuDocumentPayloadPreviewCommand.php`
- `app/Console/Commands/SafaPublicInfoPreviewCommand.php`
- `tests/Feature/TuDocumentPayloadPreviewCommandTest.php`
- `tests/Feature/SafaPublicInfoPreviewCommandTest.php`
- `docs/reports/KP-16-DRY-RUN-CROSS-APP-PAYLOAD-PREVIEW.md`
- `docs/prompts/PROMPT_KP_16_DRY_RUN_CROSS_APP_PAYLOAD_PREVIEW.md`
- `docs/integration/KP-TU-DRY-RUN-PAYLOAD-PREVIEW.md`
- `docs/integration/KP-SAFA-PUBLIC-INFO-PREVIEW.md`

Diubah:
- `bootstrap/app.php` untuk mendaftarkan command `kp:tu-document-payload-preview` dan `kp:safa-public-info-preview`.

## Struktur Payload TU
Command:

```bash
php artisan kp:tu-document-payload-preview
```

Root payload memuat:
- `source_app = kp-farmasi`
- `contract_version = kp-tu-doc-v1`
- `dry_run = true`
- `external_request_sent = false`
- `filters`
- `summary`
- `documents`
- `validation_warnings`
- `read_only_counts`

Document type yang dipreview:
- `placement_letter`
- `supervisor_assignment_letter`
- `examiner_assignment_letter`
- `exam_invitation`
- `exam_minutes`
- `score_recap`
- `final_report_archive`

Setiap item dokumen memuat snapshot student, period, placement, supervisor, examiner, exam schedule, grade, file reference placeholder, status, dan validation warnings. Path file privat tidak diekspos.

## Struktur Public Info SAFA
Command:

```bash
php artisan kp:safa-public-info-preview
```

Payload memuat:
- `source_app = kp-farmasi`
- `contract_version = kp-safa-public-v1`
- `dry_run = true`
- `external_request_sent = false`
- `public_visibility = public_safe_preview`
- `period`
- `timeline`
- `requirements`
- `announcements`
- `contact`
- `registration_status`
- `private_data_excluded = true`
- `read_only_counts`

## Data Yang Sengaja Dikecualikan dari SAFA
Preview SAFA tidak memuat:
- nilai mahasiswa;
- dokumen mahasiswa;
- logbook;
- laporan akhir;
- status individual mahasiswa;
- kontak privat preseptor;
- path file internal;
- token, signed URL, password, atau secret.

## Bukti Dry-run Tidak Write
Kedua command membawa:
- `dry_run = true`
- `external_request_sent = false`
- `read_only_counts.unchanged = true`

Test baru juga memeriksa count sebelum/sesudah command.

## Hasil Diagnostic Lokal
`php artisan kp:tu-document-payload-preview --limit=1`:
- assignments scanned: 1
- documents previewed: 7
- dry run true
- external request false
- read-only counts unchanged

`php artisan kp:safa-public-info-preview`:
- periode publik terpilih: `KP Farmasi Demo 2026`
- requirements publik: 4
- dry run true
- external request false
- read-only counts unchanged

## Rekomendasi KP-17
1. Tambahkan review human-readable untuk payload TU/SAFA agar admin bisa memvalidasi isi sebelum bridge runtime.
2. Buat approval checklist contract dengan TU dan SAFA.
3. Jika disetujui, rancang migration lokal KP untuk menyimpan external reference, bukan file duplicate.
4. Tetap buat mode dry-run terlebih dahulu untuk payload per assignment/per periode sebelum ada write bridge.
5. Tambahkan policy test agar SAFA preview tidak pernah mengandung field privat saat service berkembang.

## Status Validasi
Validasi akhir 2026-06-01:

- `php -l app\Services\Integration\KpTuDocumentPayloadPreviewService.php`: OK
- `php -l app\Services\Integration\KpSafaPublicInfoPreviewService.php`: OK
- `php -l app\Console\Commands\TuDocumentPayloadPreviewCommand.php`: OK
- `php -l app\Console\Commands\SafaPublicInfoPreviewCommand.php`: OK
- `php artisan test --filter=TuDocumentPayloadPreviewCommandTest`: passed
- `php artisan test --filter=SafaPublicInfoPreviewCommandTest`: passed
- `php artisan kp:integration-gap-check`: berhasil; semua aplikasi workspace terdeteksi dan count read-only unchanged.
- `php artisan kp:core-mapping-coverage`: berhasil; total user 8, mapped user 8, unmapped user 0, possible duplicate 0, role mismatch 0, missing identifier 0, count read-only unchanged.
- `php artisan kp:tu-document-payload-preview --limit=1`: berhasil; assignments scanned 1, documents previewed 7, `dry_run=true`, `external_request_sent=false`, count read-only unchanged.
- `php artisan kp:safa-public-info-preview`: berhasil; periode `KP Farmasi Demo 2026`, requirements 4, `dry_run=true`, `external_request_sent=false`, count read-only unchanged.
- `php artisan route:list`: berhasil, 205 routes.
- `php artisan test`: berhasil, 136 passed, 655 assertions.
- `npm run build`: berhasil, Vite production build selesai.
- `git status --short`: working tree berisi perubahan/untracked KP-14, KP-15, dan KP-16; tidak ada `.env`, `vendor`, `node_modules`, atau upload storage yang ikut masuk.

Catatan: perubahan KP-14 dan KP-15 yang belum commit tetap dipertahankan dan tidak direvert.

