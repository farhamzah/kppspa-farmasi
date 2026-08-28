# KP-17 - Admin Review Screen & Export Preview for TU/SAFA Payloads

Tanggal: 2026-06-01

## Ringkasan
KP-17 menambahkan halaman review browser untuk Admin dan Koordinator KP agar payload dry-run TU dan public-info SAFA dapat diperiksa tanpa menjalankan command terminal. Tahap ini juga menambahkan endpoint JSON preview yang tetap role-limited dan disanitasi.

Tidak ada write bridge aktif, tidak ada request nyata ke TU/SAFA, tidak ada write ke Core/TU/SAFA, dan tidak ada SSO/autologin/token URL.

## File Dibuat/Diubah
Dibuat:
- `app/Http/Controllers/Management/IntegrationReviewController.php`
- `resources/views/management/integration/tu-payload-preview.blade.php`
- `resources/views/management/integration/safa-public-info-preview.blade.php`
- `tests/Feature/IntegrationReviewScreenTest.php`
- `docs/reports/KP-17-ADMIN-REVIEW-SCREEN-EXPORT-PREVIEW.md`
- `docs/prompts/PROMPT_KP_17_ADMIN_REVIEW_SCREEN_EXPORT_PREVIEW.md`

Diubah:
- `routes/web.php`
- `app/Support/RoleDashboard.php`
- `resources/views/layouts/app.blade.php`
- `docs/integration/KP-TU-DRY-RUN-PAYLOAD-PREVIEW.md`
- `docs/integration/KP-SAFA-PUBLIC-INFO-PREVIEW.md`

## Route dan Menu
Route baru:
- `GET /management/integration/tu-payload-preview`
- `GET /management/integration/tu-payload-preview.json`
- `GET /management/integration/safa-public-info-preview`
- `GET /management/integration/safa-public-info-preview.json`

Semua route berada di group management existing:
- `auth`
- `active`
- `role.selected`
- `role:admin,koordinator_kp`

Menu baru:
- `Review Integrasi` untuk role `admin` dan `koordinator_kp`.

## Tampilan Review TU
Halaman TU menampilkan:
- status dry-run;
- status external request;
- assignment scanned;
- documents previewed;
- filter assignment ID, document type, dan limit;
- cakupan tujuh dokumen TU;
- daftar payload dokumen dengan document type, service code, source reference, source module, status, snapshot mahasiswa, periode, pembimbing, penguji, jadwal ringkas, dan validation warnings.

Halaman tidak menampilkan path file privat atau raw payload penuh.

## Tampilan Review SAFA
Halaman SAFA menampilkan:
- status dry-run;
- status external request;
- public visibility;
- status sanitasi public-safe;
- periode aktif/terpilih;
- timeline;
- persyaratan umum;
- pengumuman KP;
- kontak/admin umum placeholder;
- status umum pendaftaran dan pemilihan tempat.

Halaman tidak menampilkan nilai mahasiswa, dokumen mahasiswa, logbook, laporan akhir, status individual mahasiswa, kontak privat preseptor, path internal, token, signed URL, password, atau secret.

## Endpoint JSON
Endpoint JSON mengembalikan response langsung dari service preview dengan sanitasi tambahan pada key sensitif. Endpoint ini tidak membuat file export permanen dan tidak melakukan request keluar.

Endpoint:
- `management.integration.tu-payload-preview.json`
- `management.integration.safa-public-info-preview.json`

## Bukti Read-only
Implementasi hanya memanggil service preview KP-16:
- `KpTuDocumentPayloadPreviewService`
- `KpSafaPublicInfoPreviewService`

Tidak ada insert/update/delete, tidak ada HTTP client ke TU/SAFA, dan tidak ada perubahan konfigurasi bridge write.

Test `IntegrationReviewScreenTest` memverifikasi count tabel lokal yang relevan tetap sama setelah endpoint JSON dipanggil.

## Bukti Data Privat Tidak Terekspos
View tidak melakukan raw dump payload. Field yang ditampilkan dipilih manual dari whitelist aman.

JSON endpoint melakukan sanitasi tambahan untuk key yang mengandung auth/secret/signed/temporary-link pattern. Test memverifikasi response JSON tidak mengandung marker sensitif dan tidak mengandung field privat SAFA seperti `final_score`, `student_documents`, atau `individual_registration_status`.

Catatan: layout aplikasi tetap membawa CSRF meta standar Laravel untuk keamanan form. Itu bukan auth bridge, bukan signed login URL, dan bukan payload integrasi.

## Hasil Validasi
Validasi akhir 2026-06-01:

- `git status --short` sebelum validasi: hanya perubahan KP-17.
- `php -l app\Http\Controllers\Management\IntegrationReviewController.php`: OK.
- `php -l app\Support\RoleDashboard.php`: OK.
- `php artisan test --filter=IntegrationReviewScreenTest`: passed, 3 tests, 44 assertions.
- `php artisan kp:integration-gap-check`: berhasil; semua aplikasi workspace terdeteksi, guardrails write off, read-only counts unchanged.
- `php artisan kp:core-mapping-coverage`: berhasil; total user 8, mapped user 8, unmapped user 0, possible duplicate 0, role mismatch 0, missing identifier 0, read-only counts unchanged.
- `php artisan kp:tu-document-payload-preview --limit=1`: berhasil; assignments scanned 1, documents previewed 7, `dry_run=true`, `external_request_sent=false`, read-only counts unchanged.
- `php artisan kp:safa-public-info-preview`: berhasil; periode `KP Farmasi Demo 2026`, requirements 4, `dry_run=true`, `external_request_sent=false`, read-only counts unchanged.
- `php artisan route:list`: berhasil, 209 routes.
- `php artisan test`: berhasil, 139 passed, 699 assertions.
- `npm run build`: berhasil, Vite production build selesai.
- `git status --short` setelah validasi: hanya perubahan KP-17; tidak ada `.env`, `vendor`, `node_modules`, upload storage, cache/log, atau build output.

## Rekomendasi KP-18
1. Tambahkan halaman approval checklist kontrak TU/SAFA sebelum runtime bridge dibuat.
2. Tambahkan mode compare untuk melihat perubahan payload antar periode atau assignment.
3. Siapkan external reference table lokal KP untuk menyimpan reference ID TU/SAFA bila bridge runtime disetujui.
4. Pertahankan JSON preview sebagai kontrak regression test sebelum membuat write bridge terbatas.

