# TAHAP 02 - Manajemen User dan Import Excel

## 1. Ringkasan Pengerjaan
Tahap 2 menambahkan modul Manajemen User, Import User berbasis Excel/CSV, riwayat import, dan pengembangan Profil Saya. Modul ini melanjutkan Tahap 1 tanpa mengubah alur login multi-role yang sudah ada.

## 2. Fitur yang Dibuat
- Manajemen user khusus Admin.
- CRUD user manual.
- Multi-role management.
- Reset password development.
- Aktivasi/nonaktivasi user.
- Import Excel/CSV menggunakan `maatwebsite/excel`.
- Download template import `.xlsx`.
- Preview validasi import.
- Proses import baris valid dan pencatatan error baris gagal.
- Riwayat import dan detail error.
- Profil Saya dengan edit profil sesuai tipe user.
- Card ringkasan pada Dashboard Admin.

## 3. Struktur File Penting
- `app/Http/Controllers/Admin/UserManagementController.php`
- `app/Http/Controllers/Admin/UserImportController.php`
- `app/Http/Controllers/ProfileController.php`
- `app/Services/UserImportService.php`
- `app/Imports/UsersImport.php`
- `app/Exports/UserTemplateExport.php`
- `app/Models/Student.php`
- `app/Models/Lecturer.php`
- `app/Models/FieldSupervisor.php`
- `app/Models/UserImportBatch.php`
- `app/Models/UserImportError.php`
- `database/migrations/2026_05_21_000003_create_profile_tables.php`
- `database/migrations/2026_05_21_000004_create_user_import_tables.php`
- `resources/views/admin/users/*`
- `resources/views/admin/imports/*`
- `resources/views/profile/edit.blade.php`
- `tests/Feature/AdminUserManagementTest.php`
- `tests/Feature/UserImportAndProfileTest.php`

## 4. Database dan Migration
Tabel `students` menyimpan profil mahasiswa: `user_id`, `nim`, program studi, semester, kelas, kontak, alamat, gender, tempat/tanggal lahir, status, dan waktu profil lengkap.

Tabel `lecturers` menyimpan profil dosen: `user_id`, `nidn_nip`, `employee_number`, program studi, departemen, bidang keahlian, kontak, alamat, status, dan waktu profil lengkap.

Tabel `field_supervisors` menyimpan profil preseptor: `user_id`, nama institusi, jabatan, kontak, alamat, status, dan waktu profil lengkap.

Tabel `user_import_batches` menyimpan riwayat import: pengimpor, tipe import, nama file, jumlah baris, sukses, gagal, status, dan catatan.

Tabel `user_import_errors` menyimpan error per baris: batch import, nomor baris, identifier, pesan error, dan data baris dalam JSON.

## 5. Alur Manajemen User
Admin membuka `/admin/users`, mencari dan memfilter user, lalu dapat membuat user baru melalui form. Form mendukung nama, email, password awal, status, role multi-select, dan tipe profil. Setelah user dibuat, sistem membuat record profil sesuai tipe: mahasiswa, dosen, preseptor, atau tanpa profil khusus.

Admin juga dapat melihat detail user, mengedit data, reset password ke password development, mengaktifkan/nonaktifkan user lain, dan menghapus user. Admin tidak boleh menonaktifkan atau menghapus dirinya sendiri.

## 6. Alur Import Excel
Admin membuka `/admin/import-users`, memilih tipe import, download template, upload file, lalu sistem membuat preview validasi. Preview menampilkan baris Excel, nama, email, tipe profil, role, status valid/error, dan pesan error. Saat diproses, baris valid dibuat menjadi user, sedangkan baris error tidak dibuat dan dicatat di riwayat import.

## 7. Format Template Excel
Mahasiswa:
- `nim`
- `name`
- `email`
- `study_program`
- `semester`
- `class_name`
- `phone`

Dosen:
- `nidn_nip`
- `employee_number`
- `name`
- `email`
- `study_program`
- `department`
- `expertise`
- `phone`
- `roles`

Pembimbing lapangan:
- `name`
- `email`
- `institution_name`
- `position`
- `phone`

Mixed:
- `profile_type`
- `identifier`
- `name`
- `email`
- `roles`
- `phone`
- `study_program`
- `semester`
- `class_name`
- `institution_name`
- `position`
- `nidn_nip`
- `employee_number`
- `department`
- `expertise`

## 8. Validasi Import
Validasi import mencakup email wajib, format email, email unik di database dan file, nama wajib, role valid, NIM unik untuk mahasiswa, NIDN/NIP unik untuk dosen jika diisi, role dosen hanya `koordinator_kp`, `pembimbing_dalam`, dan `penguji`, serta preseptor wajib memiliki `institution_name`.

Baris kosong diabaikan. Error ditampilkan per baris pada preview dan disimpan ke `user_import_errors` saat proses import.

## 9. Alur Profil Pengguna
Semua user dapat membuka Profil Saya. User dapat memperbarui data profil sesuai tipe profil. Mahasiswa tidak mengedit NIM sendiri. Dosen tidak mengedit NIDN/NIP dan nomor pegawai sendiri. Jika field wajib minimal terpenuhi, `users.profile_completed` menjadi true dan `profile_completed_at` pada tabel profil diisi.

## 10. UI/UX yang Diterapkan
UI tetap menggunakan Blade, Tailwind CSS, sidebar, topbar, card, badge, table, filter, search, pagination, alert, dan empty state. Halaman import dibuat seperti step flow: download template, upload file, preview, proses import, dan lihat hasil. Semua teks UI berbahasa Indonesia dan responsive.

## 11. Keamanan yang Diterapkan
- Route Manajemen User dan Import User hanya dapat diakses active role Admin.
- User biasa tidak dapat melihat daftar user.
- Role divalidasi server-side.
- Password di-hash.
- File import divalidasi tipe dan ukuran.
- Data import divalidasi server-side sebelum disimpan.
- Password tidak pernah disimpan plain text.
- File upload tidak ditempatkan di public folder.
- CSRF protection menggunakan bawaan Laravel.
- Admin tidak bisa menonaktifkan atau menghapus akun sendiri.

## 12. Testing
Hasil verifikasi:
- `php artisan migrate` berhasil.
- `php artisan test` berhasil: 16 passed, 47 assertions.
- `npm.cmd run build` berhasil.

Test Tahap 1 tetap passed.

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

Di PowerShell Windows, jika `npm` ditolak execution policy, gunakan:

```bash
npm.cmd run build
```

## 14. Catatan Kendala
Package `maatwebsite/excel` berhasil terpasang dan digunakan. Command `composer require maatwebsite/excel` sempat timeout pada tool, tetapi dependency sudah masuk ke `composer.json`, `composer.lock`, dan `vendor`, lalu `php artisan package:discover` berhasil.

Saat pengembangan, dashboard admin sempat error karena migration Tahap 2 belum dijalankan di database browser lokal. Masalah selesai setelah `php artisan migrate`.

Fitur `must_change_password` saat ini masih berupa flag dan alert. Alur ubah password penuh direkomendasikan dibuat pada tahap berikutnya atau tahap keamanan akun.

## 15. Rekomendasi Tahap Berikutnya
Tahap 3 - Periode KP, Tempat KP, dan Kuota.

