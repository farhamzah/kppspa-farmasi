# KP-14 - Workspace Integration Gap Review & Cross-App Readiness

Tanggal: 2026-06-01

## Ringkasan
KP-14 melakukan audit kesiapan integrasi `apps/kp-farmasi` di dalam workspace besar `farmasi-ubp-workspace`, khususnya terhadap `core-farmasi`, `tu-farmasi`, dan `safa-ubp`.

Tahap ini tidak membuat integrasi write aktif, tidak mengubah database Core/TU/SAFA, tidak membuat SSO/autologin/token URL, dan tidak menambah fitur besar. Output utama adalah matrix gap, readiness plan, prompt lanjutan, dan command diagnostic read-only.

## Scope Audit
- KP sebagai aplikasi MVP end-to-end siap UAT/demo internal.
- Core sebagai master identity, role/app access, mahasiswa, dosen, prodi, dan profil terpusat.
- TU sebagai Document & Media Center untuk service request, template, generate dokumen, final upload, archive, dan document link/routing metadata.
- SAFA sebagai portal/link aplikasi dan pengumuman publik.

## Temuan Core
KP sudah punya fondasi integrasi Core yang cukup matang:
- mode auth `legacy`, `core_bridge`, dan `core_bridge_with_legacy_fallback`;
- mode master data `legacy`, `core_preferred`, dan `core_only`;
- model Core read-only;
- HTTP client Core default-off;
- preflight, health check, smoke test, auth bridge check, mapping sync lokal, dan display adapter check;
- mapping lokal `core_user_id`, `core_student_id`, `core_lecturer_id`, dan `core_user_id` untuk preseptor.

Gap utama Core:
- KP masih menyimpan profil lokal lengkap untuk user, mahasiswa, dosen, dan preseptor.
- Belum ada role translation contract formal antara role Core kebab-case dan role KP snake_case.
- Belum ada policy final field mana yang authoritative dari Core dan mana snapshot transaksi KP.
- Pembimbing lapangan masih berada di boundary khusus karena Core belum terlihat memiliki model external supervisor khusus.
- Jalur DB read-only vs HTTP API perlu diputuskan untuk staging/production.

Detail matrix ada di `docs/integration/KP-CORE-INTEGRATION-GAP-MATRIX.md`.

## Temuan TU
TU sudah memiliki fondasi yang cocok untuk bridge dokumen KP:
- e-Service Builder;
- Word template generation;
- generated document/version;
- final document upload;
- document archive;
- document link/routing metadata dengan `related_app=kp-farmasi`.

Gap utama TU:
- KP belum punya contract payload untuk surat penempatan, surat tugas pembimbing/penguji, undangan sidang, berita acara, rekap nilai, dan arsip final.
- KP belum punya field/reference lokal untuk menyimpan link metadata ke arsip TU.
- Belum ada keputusan service code TU untuk dokumen KP.
- Bridge harus dirancang sebagai reference/link ke arsip TU, bukan upload ganda, kecuali dokumen formal final memang harus diarsipkan TU.

Detail matrix ada di `docs/integration/KP-TU-DOCUMENT-BRIDGE-MATRIX.md`.

## Temuan SAFA
SAFA relevan sebagai portal publik dan kanal pengumuman:
- `PortalApplication` untuk card/link aplikasi;
- `Announcement` untuk pengumuman publik terjadwal;
- pola portal card yang tidak membawa auth, token, atau secret.

Gap utama SAFA:
- Belum ada card KP yang terdokumentasi seperti card Lab/TA.
- Belum ada public-info contract dari KP untuk periode, timeline, dan persyaratan.
- Belum ada whitelist field publik KP.
- Data mahasiswa, status pendaftaran individu, berkas, assignment, sidang personal, dan nilai harus tetap private.

Detail matrix ada di `docs/integration/KP-SAFA-PUBLIC-INFO-BRIDGE-MATRIX.md`.

## Command Diagnostic Read-only
Command baru:

```bash
php artisan kp:integration-gap-check
```

Tujuan:
- membaca mode KP/Core;
- mengecek keberadaan folder app Core/TU/SAFA;
- mengecek dokumen integrasi penting;
- membaca count tabel KP hanya jika diberi opsi `--check-kp-db`;
- membaca count tabel Core hanya jika diberi opsi `--check-core-db`;
- memastikan count sebelum/sesudah tidak berubah.

Command ini tidak menulis ke Core/TU/SAFA. Opsi `--report-json` hanya menulis file report lokal KP di `storage/app/reports`.

Test baru memastikan command registered dan read-only:
- `tests/Feature/IntegrationGapCheckCommandTest.php`

## File Dibuat/Diubah
Dibuat:
- `app/Console/Commands/IntegrationGapCheckCommand.php`
- `tests/Feature/IntegrationGapCheckCommandTest.php`
- `docs/reports/KP-14-WORKSPACE-INTEGRATION-GAP-REVIEW.md`
- `docs/prompts/PROMPT_KP_14_WORKSPACE_INTEGRATION_GAP_REVIEW.md`
- `docs/integration/KP-CORE-INTEGRATION-GAP-MATRIX.md`
- `docs/integration/KP-TU-DOCUMENT-BRIDGE-MATRIX.md`
- `docs/integration/KP-SAFA-PUBLIC-INFO-BRIDGE-MATRIX.md`

Diubah:
- `bootstrap/app.php` untuk mendaftarkan command `kp:integration-gap-check`.

## Guardrails Yang Dipertahankan
- Tidak ada write ke database Core/TU/SAFA.
- Tidak ada route web/API baru.
- Tidak ada SSO, auto-login, signed login URL, atau token URL.
- Tidak ada credential, `.env`, `vendor`, `node_modules`, atau upload storage yang disentuh.
- Core tetap read-only dari perspektif KP.
- TU/SAFA hanya dibahas sebagai bridge readiness, bukan integrasi runtime aktif.

## Rekomendasi KP-15
KP-15 sebaiknya fokus pada contract design, bukan runtime write:
1. Buat role translation contract Core-to-KP.
2. Buat Core mapping coverage report untuk data UAT/staging.
3. Tetapkan field authority policy: Core master vs KP transaction snapshot.
4. Definisikan TU document bridge contract: service code, payload, status trigger, related id, dan permission download.
5. Definisikan SAFA public-info DTO/whitelist untuk periode, timeline, dan persyaratan.
6. Tambahkan migration KP hanya jika contract sudah final, misalnya tabel lokal `kp_external_document_refs` atau `kp_public_info_exports`; jangan tulis ke TU/SAFA dulu.
7. Jalankan staging dry-run dengan `kp:integration-gap-check`, `kp:core-mode-preflight`, dan UAT checklist.

## Status Validasi
Validasi akhir:
- `php -l app/Console/Commands/IntegrationGapCheckCommand.php`: berhasil, no syntax errors.
- `php -l tests/Feature/IntegrationGapCheckCommandTest.php`: berhasil, no syntax errors.
- `php artisan kp:integration-gap-check`: berhasil, read-only counts unchanged.
- `php artisan test --filter=IntegrationGapCheckCommandTest`: berhasil, 1 passed, 5 assertions.
- `php artisan route:list`: berhasil, 205 routes.
- `php artisan test`: berhasil, 130 passed, 618 assertions.
- `npm run build`: berhasil.
- `git status --short`: ada file KP-14 baru/diubah dan `docs/reports/HANDOFF_REPORT_2026_06_01.md` masih untracked dari pekerjaan handoff sebelumnya; tidak ada file sensitif.

