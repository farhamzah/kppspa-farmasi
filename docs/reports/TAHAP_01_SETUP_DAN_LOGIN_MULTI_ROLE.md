# TAHAP 01 - Setup dan Login Multi Role

## 1. Ringkasan Pengerjaan
Tahap 1 telah membuat fondasi aplikasi Laravel SI-KP Farmasi UBP. Fondasi yang dibuat meliputi autentikasi login, multi-role user, pemilihan role aktif, dashboard berbeda untuk setiap role, halaman Profil Saya, middleware keamanan, seeder role dan akun demo, feature test, serta struktur dokumentasi awal.

## 2. Fitur yang Dibuat
- Project Laravel 12.
- Login berbasis email dan password.
- Validasi user aktif dan user wajib memiliki role.
- Multi-role dengan halaman "Pilih Akses".
- Session `active_role`.
- Redirect dashboard otomatis berdasarkan role aktif.
- Dashboard awal untuk mahasiswa, admin, koordinator KP, pembimbing dalam, preseptor, dan penguji.
- Layout aplikasi setelah login dengan sidebar, topbar, content area, dan footer.
- Halaman Profil Saya.
- Middleware `CheckUserActive`, `EnsureRoleSelected`, dan `CheckRole`.
- Seeder role dan akun demo development.
- Feature test untuk alur login dan role.

## 3. Struktur File Penting
- `app/Models/User.php`
- `app/Models/Role.php`
- `app/Support/RoleDashboard.php`
- `app/Http/Controllers/Auth/LoginController.php`
- `app/Http/Controllers/RoleSelectionController.php`
- `app/Http/Controllers/DashboardController.php`
- `app/Http/Controllers/ProfileController.php`
- `app/Http/Middleware/CheckUserActive.php`
- `app/Http/Middleware/EnsureRoleSelected.php`
- `app/Http/Middleware/CheckRole.php`
- `database/migrations/0001_01_01_000000_create_users_table.php`
- `database/migrations/2026_05_21_000001_create_roles_table.php`
- `database/migrations/2026_05_21_000002_create_user_roles_table.php`
- `database/seeders/RoleSeeder.php`
- `database/seeders/AdminSeeder.php`
- `database/seeders/DemoUserSeeder.php`
- `resources/views/auth/login.blade.php`
- `resources/views/roles/select.blade.php`
- `resources/views/layouts/app.blade.php`
- `resources/views/dashboard/show.blade.php`
- `resources/views/profile/show.blade.php`
- `tests/Feature/AuthMultiRoleTest.php`
- `AGENTS.md`

## 4. Database dan Migration
Tabel `users` berisi data akun dengan field utama `name`, `email`, `email_verified_at`, `password`, `status`, `must_change_password`, `profile_completed`, `last_login_at`, `remember_token`, dan timestamps. Email dibuat unique, status default `active`, `must_change_password` default `true`, dan `profile_completed` default `false`.

Tabel `roles` berisi master role dengan field `name`, `label`, `description`, dan timestamps.

Tabel `user_roles` menjadi pivot many-to-many antara user dan role. Tabel ini memiliki foreign key ke `users` dan `roles`, cascade delete saat user/role dihapus, serta unique constraint untuk kombinasi `user_id` dan `role_id`.

## 5. Seeder dan Akun Demo
Seeder yang dibuat:
- `RoleSeeder`
- `AdminSeeder`
- `DemoUserSeeder`

Akun demo development:
- `admin@sikp.test`
- `koordinator@sikp.test`
- `mahasiswa@sikp.test`
- `dosen@sikp.test`
- `lapangan@sikp.test`

Password development untuk akun demo adalah `password`. Password ini hanya untuk development dan bukan rekomendasi production.

## 6. Alur Login
User login melalui halaman `/login`. Sistem memvalidasi email dan password, lalu mengecek status akun. Jika akun inactive, login ditolak dengan pesan "Akun Anda tidak aktif. Silakan hubungi Admin."

Jika user tidak memiliki role, sistem logout otomatis dan menampilkan pesan "Akun belum memiliki role. Silakan hubungi Admin."

Jika user memiliki satu role, role tersebut disimpan ke session `active_role` dan user langsung diarahkan ke dashboard role. Jika user memiliki lebih dari satu role, user diarahkan ke `/pilih-role`. Setelah memilih role, sistem memvalidasi role tersebut benar-benar dimiliki user, menyimpan `active_role`, lalu mengarahkan ke dashboard sesuai role.

Saat logout, session `active_role` dihapus.

## 7. Role dan Hak Akses
Role yang tersedia:
- Mahasiswa
- Admin
- Koordinator KP
- Pembimbing Dalam / Dosen
- Preseptor
- Penguji

Dashboard setiap role dilindungi middleware. User tidak dapat membuka dashboard role lain hanya dengan mengganti URL.

## 8. UI/UX yang Diterapkan
UI menggunakan Blade, Tailwind CSS, dan Vite. Halaman login dibuat bersih dengan panel informasi dan form login. Setelah login, aplikasi menggunakan layout sidebar, topbar, content area, dan footer sederhana.

Sidebar menampilkan menu sesuai role aktif. Topbar menampilkan nama aplikasi, nama user, role aktif, tombol ganti role untuk user multi-role, dan tombol logout. Dashboard menggunakan card status profil, card role aktif, status akun, alert kelengkapan profil, dan grid fitur placeholder. Tampilan dibuat responsive untuk desktop dan mobile.

## 9. Keamanan yang Diterapkan
- Password akun menggunakan hash Laravel.
- Route utama dilindungi middleware `auth`.
- User inactive dicegah mengakses sistem.
- User tanpa role tidak dapat masuk dashboard.
- Session `active_role` divalidasi terhadap role milik user.
- Dashboard role dilindungi middleware `CheckRole`.
- Form menggunakan CSRF protection bawaan Laravel.
- Input login dan pemilihan role divalidasi server-side.
- Rate limiting sederhana diterapkan pada login.

## 10. Cara Menjalankan
Perintah umum:

```bash
composer install
npm install
npm run dev
php artisan migrate --seed
php artisan serve
```

Untuk build production asset:

```bash
npm run build
```

Pastikan database MySQL/MariaDB `sikp_farmasi_ubp` tersedia atau izinkan Laravel membuat database saat menjalankan migration di environment lokal yang mendukung.

## 11. Cara Testing Manual
- Buka `/login` dan pastikan halaman tampil rapi.
- Login sebagai `mahasiswa@sikp.test` dengan password `password`; pastikan langsung masuk Dashboard Mahasiswa.
- Login sebagai `koordinator@sikp.test`; pastikan diarahkan ke halaman Pilih Akses.
- Pilih role Koordinator KP; pastikan masuk Dashboard Koordinator KP.
- Dari topbar, klik Ganti Role; pilih role lain yang dimiliki.
- Login sebagai `dosen@sikp.test`; pastikan dapat memilih Pembimbing Dalam atau Penguji.
- Login sebagai `lapangan@sikp.test`; pastikan masuk Dashboard Preseptor.
- Coba akses URL dashboard role lain; pastikan ditolak 403.
- Buka Profil Saya dan pastikan data user serta role tampil.
- Klik Logout dan pastikan kembali ke halaman login.
- Uji tampilan mobile menggunakan responsive mode browser.

## 12. Catatan Kendala
Starter kit auth resmi tidak digunakan karena project Laravel skeleton awal belum memasang starter kit, dan kebutuhan Tahap 1 dapat dipenuhi lebih ringan dengan auth Blade custom berbasis Laravel bawaan. Dependency frontend sempat perlu dijalankan melalui `npm.cmd` karena PowerShell menolak `npm.ps1` akibat execution policy.

Migration dan seeder MySQL berhasil dijalankan pada database `sikp_farmasi_ubp`. Feature test juga berhasil menggunakan SQLite in-memory.

## 13. Rekomendasi Tahap Berikutnya
Tahap berikutnya adalah: Tahap 2 - Manajemen User dan Import Excel.

