# TAHAP 03 - Periode KP, Tempat KP, dan Kuota

## 1. Ringkasan Pengerjaan
Tahap 3 menambahkan fondasi pengelolaan Periode KP, Tempat KP, Kuota Tempat KP per periode, dan Log Perubahan Kuota. Modul ini dapat diakses oleh Admin dan Koordinator KP, serta disiapkan untuk tahap pendaftaran dan pemilihan tempat KP berikutnya.

## 2. Fitur yang Dibuat
- CRUD Periode KP.
- CRUD Tempat KP.
- CRUD Kuota Tempat KP.
- Toggle buka/tutup kuota.
- Log perubahan kuota.
- Filter, search, pagination, badge status, dan empty state.
- Sidebar menu untuk Admin dan Koordinator KP.
- Dashboard ringkasan periode, tempat, dan kuota.
- Middleware role multi-role untuk `admin,koordinator_kp`.
- Feature test Tahap 3.

## 3. Struktur File Penting
- `database/migrations/2026_05_21_000005_create_kp_management_tables.php`
- `app/Models/KpPeriod.php`
- `app/Models/KpPlace.php`
- `app/Models/KpPlaceQuota.php`
- `app/Models/KpQuotaLog.php`
- `app/Http/Controllers/Management/KpPeriodController.php`
- `app/Http/Controllers/Management/KpPlaceController.php`
- `app/Http/Controllers/Management/KpPlaceQuotaController.php`
- `app/Http/Controllers/Management/KpQuotaLogController.php`
- `app/Http/Requests/Management/*`
- `resources/views/management/periods/*`
- `resources/views/management/places/*`
- `resources/views/management/quotas/*`
- `resources/views/management/quota-logs/index.blade.php`
- `tests/Feature/KpManagementTest.php`

## 4. Database dan Migration
Tabel `kp_periods` menyimpan nama periode, tahun akademik, semester, jadwal pendaftaran, jadwal verifikasi dokumen, jadwal pemilihan tempat, tanggal pelaksanaan KP, status, deskripsi, dan audit user pembuat/perubah.

Tabel `kp_places` menyimpan data tempat KP seperti nama, tipe, alamat, kota, provinsi, kontak, email, deskripsi, status, dan audit user pembuat/perubah.

Tabel `kp_place_quotas` menyimpan kuota tempat KP per periode. Kombinasi `kp_period_id` dan `kp_place_id` dibuat unique agar satu tempat hanya memiliki satu kuota pada satu periode.

Tabel `kp_quota_logs` menyimpan audit perubahan kuota, status buka/tutup, action, catatan, user pelaku, dan waktu perubahan.

## 5. Alur Periode KP
Admin atau Koordinator KP membuka menu Periode KP, lalu dapat membuat periode baru dengan nama, tahun akademik, semester, jadwal pendaftaran, jadwal verifikasi, jadwal pemilihan tempat, tanggal KP, status, dan deskripsi. Validasi tanggal memastikan tanggal akhir berada setelah tanggal mulai.

## 6. Alur Tempat KP
Admin atau Koordinator KP membuka menu Tempat KP, lalu dapat membuat tempat baru dengan tipe, alamat, kota, provinsi, contact person, telepon, email, status, dan deskripsi. Tempat yang sudah memiliki kuota tidak boleh dihapus dan sebaiknya dinonaktifkan.

## 7. Alur Kuota Tempat KP
Admin atau Koordinator KP memilih periode dan tempat aktif, mengisi kuota, status buka/tutup, serta catatan. Sistem menolak duplikasi kuota untuk kombinasi periode dan tempat yang sama. Pada Tahap 3, `filledCount()` masih bernilai 0, sehingga sisa kuota sama dengan kuota.

## 8. Log Perubahan Kuota
Log dibuat saat kuota dibuat, diperbarui, dibuka, ditutup, atau dihapus. Log mencatat user, periode, tempat, kuota lama, kuota baru, status lama, status baru, action, catatan, dan waktu.

## 9. Role dan Hak Akses
Admin dan Koordinator KP dapat mengelola periode, tempat, kuota, dan melihat log. Mahasiswa, Pembimbing Dalam, Preseptor, dan Penguji tidak dapat mengakses route modul Tahap 3.

## 10. UI/UX yang Diterapkan
UI menggunakan bahasa Indonesia, card, badge status, table responsive, filter, search, pagination, empty state, alert sukses/error, form berlabel jelas, helper text, dan konfirmasi sebelum aksi penting.

## 11. Keamanan yang Diterapkan
- Route dilindungi `auth`, `active`, `role.selected`, dan middleware role `admin,koordinator_kp`.
- Validasi input memakai Form Request.
- CSRF protection bawaan Laravel tetap aktif.
- Unique constraint database mencegah duplikasi kuota periode-tempat.
- Log kuota tidak bisa diedit melalui UI.
- Role lain menerima 403 saat mengakses modul.

## 12. Testing
Hasil verifikasi:
- `php artisan migrate` berhasil.
- `php artisan test` berhasil: 23 passed, 69 assertions.
- `npm.cmd run build` berhasil.
- `git status` dicek. Commit Tahap 3 dibuat dengan hash `7a6e9bd`.
- Setelah commit, masih ada perubahan UI yang tidak terkait langsung dengan Tahap 3 pada `resources/views/auth/login.blade.php`, `resources/views/layouts/guest.blade.php`, `resources/views/profile/edit.blade.php`, dan `resources/views/profile/show.blade.php`. Perubahan tersebut tidak dimasukkan ke commit Tahap 3 agar tidak mencampur pekerjaan modul KP dengan polish UI lain.

Test Tahap 1 dan Tahap 2 tetap passed.

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
Middleware role awal hanya menerima satu parameter. Pada Tahap 3 diperluas agar aman menerima beberapa role seperti `role:admin,koordinator_kp` tanpa merusak route single-role lama.

View dashboard sudah pernah dipoles setelah Tahap 2, sehingga penambahan ringkasan Tahap 3 dilakukan dengan mengikuti struktur visual yang sudah ada.

## 15. Rekomendasi Tahap Berikutnya
Tahap 4 - Pendaftaran KP dan Verifikasi Berkas.

