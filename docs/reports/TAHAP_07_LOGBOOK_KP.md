# TAHAP 07 - Logbook KP

## 1. Ringkasan Pengerjaan
Tahap 7 menambahkan modul Logbook KP untuk mahasiswa yang sudah memiliki penempatan aktif/berjalan. Modul ini mencakup draft, submit, upload bukti opsional, validasi Preseptor, komentar Pembimbing Dalam/Admin/Koordinator, monitoring, dan audit log aktivitas.

## 2. Fitur yang Dibuat
- Logbook mahasiswa berdasarkan assignment aktif.
- Simpan draft, edit draft/revisi, submit validasi.
- Upload dan download bukti kegiatan opsional.
- Validasi logbook oleh Preseptor: setujui, revisi, tolak.
- Pemantauan dan komentar oleh Pembimbing Dalam.
- Monitoring logbook dan log aktivitas oleh Admin/Koordinator.
- Dashboard dan sidebar diperbarui untuk menu logbook.

## 3. Struktur File Penting
- Migration: `database/migrations/2026_05_22_000009_create_kp_logbook_tables.php`
- Model: `app/Models/KpLogbook.php`, `KpLogbookComment.php`, `KpLogbookLog.php`
- Service: `app/Services/KpLogbookService.php`
- Controller: `Student/LogbookController.php`, `FieldSupervisor/LogbookValidationController.php`, `InternalSupervisor/LogbookMonitoringController.php`, `Management/LogbookMonitoringController.php`, `Management/LogbookLogController.php`
- Request: `StoreKpLogbookRequest`, `UpdateKpLogbookRequest`, `SubmitKpLogbookRequest`, `ReviewKpLogbookRequest`, `StoreKpLogbookCommentRequest`
- View: `resources/views/student/logbooks`, `field-supervisor/logbooks`, `internal-supervisor/logbooks`, `management/logbooks`, `management/logbook-logs`
- Test: `tests/Feature/KpLogbookTest.php`

## 4. Database dan Migration
- `kp_logbooks`: menyimpan tanggal, jam, judul, uraian kegiatan, hasil pembelajaran, kendala, solusi, metadata bukti, status, waktu submit, dan validasi.
- `kp_logbook_comments`: menyimpan komentar/catatan pemantauan dengan visibility `internal` atau `visible_to_student`.
- `kp_logbook_logs`: menyimpan audit aksi seperti created, updated, submitted, approved, revision_requested, rejected, comment_added, evidence_uploaded, dan evidence_replaced.

## 5. Alur Logbook Mahasiswa
Mahasiswa membuka menu Logbook KP. Jika belum memiliki penempatan aktif, sistem menampilkan empty state. Jika penempatan aktif tersedia, mahasiswa dapat membuat logbook, menyimpan draft, mengedit draft/revisi, dan submit untuk validasi.

## 6. Alur Validasi Preseptor
Preseptor hanya melihat logbook mahasiswa yang ditugaskan kepadanya. Logbook berstatus `menunggu_validasi` dapat disetujui, diminta revisi dengan catatan, atau ditolak dengan catatan.

## 7. Alur Pemantauan Pembimbing Dalam
Pembimbing Dalam hanya melihat logbook mahasiswa bimbingannya. Pada Tahap 7 pembimbing dalam belum menjadi validator utama, tetapi dapat memberi komentar/catatan pemantauan.

## 8. Monitoring Admin/Koordinator
Admin dan Koordinator KP dapat melihat seluruh logbook, filter periode/status/search, melihat detail, memberi komentar monitoring, dan membuka log aktivitas.

## 9. Upload dan Download Bukti Kegiatan
Bukti kegiatan bersifat opsional dengan format PDF/JPG/JPEG/PNG maksimal 5MB. File disimpan pada disk `local` di folder non-public `kp-logbook-evidence`. Download dilakukan lewat route protected sesuai hak akses.

## 10. Status Logbook
- `draft`
- `menunggu_validasi`
- `disetujui`
- `revisi`
- `ditolak`

## 11. Role dan Hak Akses
- Mahasiswa: mengelola logbook miliknya sendiri.
- Preseptor: validasi logbook mahasiswa tugasnya.
- Pembimbing Dalam: memantau dan memberi komentar pada logbook mahasiswa bimbingannya.
- Admin/Koordinator KP: monitoring seluruh logbook dan log aktivitas.
- Penguji: belum memiliki akses logbook pada tahap ini.

## 12. UI/UX yang Diterapkan
UI menggunakan bahasa Indonesia, card, badge status, table responsive, search/filter, empty state, pesan sukses/error, form upload dengan helper text, dan dashboard ringkas per role.

## 13. Keamanan yang Diterapkan
- Role middleware tetap digunakan pada semua route.
- Ownership logbook dicek server-side melalui service.
- File upload divalidasi server-side.
- File disimpan non-public dan diunduh melalui route protected.
- Status logbook tidak diubah langsung dari request bebas.
- Semua perubahan penting dicatat ke `kp_logbook_logs`.

## 14. Testing
- `php artisan migrate`: berhasil.
- `php artisan test`: berhasil, 49 passed, 190 assertions.
- `npm run build`: berhasil.
- `git status`: dicek setelah build. Ada file UI `resources/views/student/place-selections/index.blade.php` yang sudah berubah sebelum Tahap 7 dan dibiarkan tetap aman tanpa overwrite.

## 15. Cara Menjalankan
```bash
composer install
npm install
npm run dev
php artisan migrate --seed
php artisan serve
```

Untuk build production:
```bash
npm run build
```

## 16. Catatan Kendala
Tidak ada kendala dependency. Satu file UI dari tahap sebelumnya sudah berubah sebelum pengerjaan Tahap 7, sehingga perubahan tersebut dipertahankan dan tidak dioverwrite.

## 17. Rekomendasi Tahap Berikutnya
Tahap 8 - Laporan Akhir KP.

