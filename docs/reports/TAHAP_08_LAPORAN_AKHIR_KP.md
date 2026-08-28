# TAHAP 08 - Laporan Akhir KP

## 1. Ringkasan Pengerjaan
Tahap 8 menambahkan modul Laporan Akhir KP untuk mahasiswa yang sudah memiliki penempatan aktif/berjalan. Modul mencakup upload laporan, versi revisi, submit review, review oleh Pembimbing Dalam, monitoring Admin/Koordinator, download protected, dan log aktivitas.

## 2. Fitur yang Dibuat
- Halaman Laporan Akhir mahasiswa.
- Upload laporan pertama dan revisi versi.
- Submit laporan untuk review.
- Review Pembimbing Dalam: setujui, minta revisi, tolak.
- Monitoring laporan oleh Admin/Koordinator.
- Riwayat versi file laporan.
- Log aktivitas laporan.
- Dashboard dan menu untuk Laporan Akhir, Review Laporan, Monitoring Laporan, dan Log Laporan.

## 3. Struktur File Penting
- Migration: `database/migrations/2026_05_22_000010_create_kp_final_report_tables.php`
- Model: `KpFinalReport`, `KpFinalReportFile`, `KpFinalReportLog`
- Service: `app/Services/KpFinalReportService.php`
- Controller: `Student/FinalReportController.php`, `InternalSupervisor/FinalReportReviewController.php`, `Management/FinalReportMonitoringController.php`, `Management/FinalReportLogController.php`
- Request: `UploadFinalReportRequest`, `SubmitFinalReportRequest`, `ReviewFinalReportRequest`
- View: `resources/views/student/final-reports`, `internal-supervisor/final-reports`, `management/final-reports`, `management/final-report-logs`
- Test: `tests/Feature/KpFinalReportTest.php`

## 4. Database dan Migration
- `kp_final_reports`: status laporan akhir per assignment, versi aktif, submit, review, approval, dan catatan.
- `kp_final_report_files`: file laporan per versi, metadata file, uploader, waktu upload, dan catatan.
- `kp_final_report_logs`: audit aktivitas laporan akhir seperti created, uploaded, submitted, revision_uploaded, approved, revision_requested, dan rejected.

## 5. Alur Laporan Mahasiswa
Mahasiswa membuka menu Laporan Akhir. Jika assignment aktif tersedia, sistem membuat/mengambil draft laporan. Mahasiswa upload file, submit untuk review, melihat status, catatan review, dan riwayat versi.

## 6. Alur Upload dan Revisi Versi
Upload pertama membuat versi 1. Jika laporan berstatus revisi/ditolak, upload berikutnya membuat versi baru dan status kembali menjadi draft. File lama tidak dioverwrite agar riwayat revisi tetap tersimpan.

## 7. Alur Review Pembimbing Dalam
Pembimbing Dalam hanya melihat laporan mahasiswa bimbingannya. Laporan berstatus `menunggu_review` dapat disetujui, diminta revisi dengan catatan, atau ditolak dengan catatan.

## 8. Monitoring Admin/Koordinator
Admin dan Koordinator KP dapat melihat seluruh laporan, filter status/periode/search, membuka detail, download file, dan melihat log laporan.

## 9. Upload dan Download File Laporan
File laporan menerima PDF, DOC, dan DOCX maksimal 10MB. File disimpan pada disk `local` di folder non-public `kp-final-reports`. Download hanya melalui route protected sesuai ownership/role.

## 10. Status Laporan
- `draft`
- `menunggu_review`
- `revisi`
- `disetujui`
- `ditolak`

## 11. Role dan Hak Akses
- Mahasiswa: mengelola dan download laporan miliknya.
- Pembimbing Dalam: review dan download laporan mahasiswa bimbingannya.
- Admin/Koordinator: monitoring dan download semua laporan.
- Preseptor/Penguji: belum memiliki akses pada Tahap 8.

## 12. UI/UX yang Diterapkan
UI menggunakan bahasa Indonesia, card status, badge, tabel responsive, filter/search, empty state, form upload jelas, riwayat versi, dan alert approval/revisi.

## 13. Keamanan yang Diterapkan
- Semua route dilindungi auth dan role middleware.
- Ownership mahasiswa dan pembimbing dicek server-side melalui service.
- Upload file divalidasi server-side.
- File disimpan non-public dan diunduh lewat route protected.
- Laporan disetujui tidak bisa diupload ulang oleh mahasiswa.
- Semua perubahan status dicatat ke log.

## 14. Testing
- `php artisan migrate`: berhasil.
- `php artisan test`: berhasil, 55 passed, 223 assertions.
- `npm run build`: berhasil.
- `git status`: dicek setelah build; perubahan Tahap 8 siap commit.

## 15. Catatan Worktree Awal
File `resources/views/student/place-selections/index.blade.php` diaudit sebagai polish UI yang aman. Sebelum Tahap 8, ditemukan juga CSS helper dan komponen UI kecil serta penghapusan tidak aman pada `resources/views/student/registrations/index.blade.php` dan `resources/views/profile/show.blade.php`. File yang terhapus dipulihkan, test/build lulus, lalu polish UI dicommit terpisah dengan commit `46f8c92 Polish student place selection UI`.

## 16. Cara Menjalankan
```bash
composer install
npm install
npm run dev
php artisan migrate --seed
php artisan serve
```

Build production:
```bash
npm run build
```

## 17. Catatan Kendala
Tidak ada kendala dependency. Catatan utama adalah worktree awal perlu dibersihkan dari perubahan UI dan file view yang terhapus sebelum implementasi Tahap 8.

## 18. Rekomendasi Tahap Berikutnya
Tahap 9 - Pengajuan dan Penjadwalan Sidang KP.

