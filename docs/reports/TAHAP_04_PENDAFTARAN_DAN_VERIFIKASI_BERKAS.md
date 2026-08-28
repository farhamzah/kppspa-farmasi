# TAHAP 04 - Pendaftaran KP dan Verifikasi Berkas

## 1. Ringkasan Pengerjaan
Tahap 4 menambahkan modul Pendaftaran KP dan Verifikasi Berkas. Mahasiswa dapat membuat pendaftaran pada periode yang sedang dibuka, upload dokumen persyaratan, submit pendaftaran, dan melihat status verifikasi. Admin dan Koordinator KP dapat mengelola persyaratan dokumen, meninjau pendaftaran, menyetujui/revisi/menolak dokumen, dan memverifikasi pendaftaran.

## 2. Fitur yang Dibuat
- CRUD persyaratan dokumen per periode KP.
- Pendaftaran KP mahasiswa berdasarkan periode aktif.
- Upload dan download dokumen melalui route protected.
- Submit pendaftaran jika dokumen wajib sudah diupload.
- Review dokumen oleh Admin/Koordinator: setujui, revisi, tolak.
- Review pendaftaran: verifikasi, revisi, tolak.
- Log aktivitas pendaftaran dan dokumen.
- Status eligibility untuk pemilihan tempat KP tahap berikutnya.
- Menu sidebar dan dashboard ringkasan untuk Mahasiswa, Admin, dan Koordinator KP.
- Feature test Tahap 4.

## 3. Struktur File Penting
- `database/migrations/2026_05_21_000006_create_kp_registration_tables.php`
- `app/Models/KpDocumentRequirement.php`
- `app/Models/KpRegistration.php`
- `app/Models/KpDocument.php`
- `app/Models/KpRegistrationLog.php`
- `app/Http/Controllers/Student/KpRegistrationController.php`
- `app/Http/Controllers/Student/KpDocumentUploadController.php`
- `app/Http/Controllers/Management/KpDocumentRequirementController.php`
- `app/Http/Controllers/Management/KpRegistrationReviewController.php`
- `app/Http/Requests/Student/*`
- `app/Http/Requests/Management/*`
- `resources/views/student/registrations/*`
- `resources/views/management/document-requirements/*`
- `resources/views/management/registrations/*`
- `tests/Feature/KpRegistrationAndDocumentVerificationTest.php`

## 4. Database dan Migration
Tabel `kp_document_requirements` menyimpan persyaratan dokumen per periode, termasuk nama dokumen, instruksi, wajib/tidak wajib, tipe file, ukuran maksimal, urutan, status, dan audit user pembuat/perubah.

Tabel `kp_registrations` menyimpan pendaftaran KP mahasiswa per periode. Kombinasi `kp_period_id` dan `student_id` dibuat unique agar mahasiswa tidak membuat pendaftaran ganda pada periode yang sama.

Tabel `kp_documents` menyimpan metadata file dokumen mahasiswa, status review, catatan review, reviewer, dan waktu upload/review. File disimpan di storage Laravel, bukan path public langsung.

Tabel `kp_registration_logs` menyimpan audit aksi penting seperti pendaftaran dibuat, submit, upload dokumen, approve/revisi/tolak dokumen, verifikasi pendaftaran, revisi, penolakan, dan pembatalan.

## 5. Alur Pendaftaran Mahasiswa
Mahasiswa membuka menu Pendaftaran KP. Jika profil belum lengkap, sistem menampilkan peringatan dan menolak pembuatan pendaftaran. Jika ada periode yang pendaftarannya dibuka, mahasiswa dapat membuat pendaftaran. Sistem membuat pendaftaran berstatus `draft` dan menyiapkan daftar dokumen sesuai persyaratan aktif periode tersebut.

## 6. Alur Upload Berkas
Mahasiswa upload dokumen untuk setiap persyaratan. File divalidasi berdasarkan tipe file dan ukuran maksimal requirement. Jika upload ulang dilakukan, file lama dihapus dari storage untuk menghindari penumpukan file tidak terpakai. Dokumen yang diupload berstatus `menunggu`.

## 7. Alur Verifikasi Admin/Koordinator
Admin atau Koordinator KP membuka menu Verifikasi Pendaftaran. Mereka dapat filter periode/status, search mahasiswa, melihat detail pendaftaran, download dokumen, approve dokumen, meminta revisi dengan catatan, menolak dokumen, dan memverifikasi pendaftaran jika semua dokumen wajib sudah disetujui.

## 8. Status Pendaftaran dan Dokumen
Status pendaftaran: `draft`, `menunggu_verifikasi`, `revisi`, `terverifikasi`, `ditolak`, `dibatalkan`.

Status dokumen: `belum_upload`, `menunggu`, `disetujui`, `revisi`, `ditolak`.

Pendaftaran dianggap eligible untuk Tahap 5 jika statusnya `terverifikasi` dan semua dokumen wajib berstatus `disetujui`.

## 9. Role dan Hak Akses
Mahasiswa hanya dapat melihat, mengelola, upload, dan download dokumen pendaftaran miliknya sendiri. Admin dan Koordinator KP dapat mengelola persyaratan dokumen dan memverifikasi semua pendaftaran. Pembimbing Dalam, Preseptor, dan Penguji tidak diberi akses ke modul Tahap 4.

## 10. UI/UX yang Diterapkan
UI menggunakan bahasa Indonesia, card, badge status, table responsive, search/filter, pagination, empty state, alert sukses/error, progress step mahasiswa, progress bar dokumen, helper text upload, dan konfirmasi sebelum aksi penting.

## 11. Keamanan Upload dan Download File
- Route dilindungi `auth`, `active`, `role.selected`, dan middleware role.
- Validasi upload dilakukan server-side melalui Form Request.
- File disimpan pada disk lokal non-public.
- Download dokumen selalu melewati controller dan authorization.
- Mahasiswa tidak dapat mengakses pendaftaran atau file mahasiswa lain.
- Admin/Koordinator saja yang dapat melakukan review.
- Semua aksi penting dicatat di `kp_registration_logs`.

## 12. Testing
Hasil verifikasi:
- `php artisan migrate`: berhasil setelah nama unique index `kp_documents` dipendekkan agar kompatibel dengan batas identifier MySQL.
- `php artisan test`: berhasil, 30 passed, 98 assertions.
- `npm.cmd run build`: berhasil, Vite build selesai.
- `git status`: bersih setelah commit Tahap 4.
- Commit Tahap 4 dibuat dengan pesan `Add KP registration and document verification`.

Test Tahap 4 mencakup akses mahasiswa, pembatasan role, profil belum lengkap, pendaftaran periode aktif, pencegahan duplikasi pendaftaran, validasi upload, submit pendaftaran, review admin/koordinator, aturan verifikasi, dan eligibility pemilihan tempat.

## 13. Cara Menjalankan
```bash
composer install
npm install
php artisan migrate --seed
npm run dev
php artisan serve
```

Untuk build asset:

```bash
npm run build
```

Di PowerShell Windows, gunakan `npm.cmd run build` jika `npm` ditolak execution policy.

## 14. Catatan Kendala
Sebelum Tahap 4 dimulai, empat file polish UI dari pekerjaan sebelumnya diaudit dan dibuat commit terpisah: `Polish authentication and profile UI`.

Tidak ada fallback upload khusus yang digunakan. File dokumen KP disimpan di storage lokal non-public dan file lama dihapus saat upload ulang.

## 15. Rekomendasi Tahap Berikutnya
Tahap 5 - Pemilihan Tempat KP / War Ticket.

