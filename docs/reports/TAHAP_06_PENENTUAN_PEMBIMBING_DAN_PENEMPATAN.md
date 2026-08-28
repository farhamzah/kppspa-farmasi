# TAHAP 06 - Penentuan Pembimbing dan Penempatan KP

## 1. Ringkasan Pengerjaan
Tahap 6 menambahkan modul penempatan KP resmi. Admin dan Koordinator KP dapat membuat assignment dari selection aktif, menentukan Pembimbing Dalam dan Preseptor, membatalkan assignment, serta melihat log perubahan. Mahasiswa, Pembimbing Dalam, dan Preseptor mendapatkan halaman sesuai cakupan aksesnya.

## 2. Fitur yang Dibuat
- Assignment/penempatan KP dari selection aktif.
- Penentuan Pembimbing Dalam.
- Penentuan Preseptor.
- Mapping Preseptor dengan Tempat KP.
- Monitoring penempatan.
- Log perubahan assignment.
- Halaman Penempatan KP mahasiswa.
- Halaman Mahasiswa Bimbingan untuk Pembimbing Dalam.
- Halaman Mahasiswa KP untuk Preseptor.
- Dashboard dan sidebar Tahap 6.
- Feature test Tahap 6.

## 3. Struktur File Penting
- `database/migrations/2026_05_22_000008_create_kp_assignment_tables.php`
- `app/Models/KpAssignment.php`
- `app/Models/KpAssignmentLog.php`
- `app/Models/KpPlaceFieldSupervisor.php`
- `app/Services/KpAssignmentService.php`
- `app/Http/Controllers/Management/KpAssignmentController.php`
- `app/Http/Controllers/Management/KpAssignmentLogController.php`
- `app/Http/Controllers/Student/AssignmentController.php`
- `app/Http/Controllers/InternalSupervisor/SupervisedStudentController.php`
- `app/Http/Controllers/FieldSupervisor/FieldStudentController.php`
- `resources/views/management/assignments/*`
- `resources/views/student/assignments/show.blade.php`
- `resources/views/internal-supervisor/assignments/*`
- `resources/views/field-supervisor/assignments/*`
- `tests/Feature/KpAssignmentAndSupervisorTest.php`

## 4. Database dan Migration
Tabel `kp_assignments` menyimpan penempatan resmi mahasiswa KP, relasi periode, pendaftaran, selection, mahasiswa, tempat, pembimbing dalam, preseptor, status, pembuat assignment, waktu assignment, `active_key`, dan catatan.

Tabel `kp_assignment_logs` menyimpan audit perubahan assignment, status lama/baru, pembimbing lama/baru, user pelaku, action, catatan, dan waktu.

Tabel `kp_place_field_supervisors` menghubungkan preseptor dengan tempat KP. Mapping dibuat otomatis saat preseptor ditetapkan pada assignment.

## 5. Alur Penempatan KP
Admin atau Koordinator memilih selection aktif yang belum punya assignment, lalu membuat penempatan. Jika pembimbing belum lengkap, status menjadi `menunggu_pembimbing`. Jika dua pembimbing sudah lengkap, status menjadi `aktif`.

## 6. Alur Penentuan Pembimbing Dalam
Admin atau Koordinator memilih dosen dari data `lecturers`. Sistem memvalidasi user dosen tersebut memiliki role `pembimbing_dalam`. Perubahan dicatat di log assignment.

## 7. Alur Penentuan Preseptor
Admin atau Koordinator memilih data `field_supervisors`. Sistem memvalidasi user memiliki role `pembimbing_lapangan`, lalu membuat mapping tempat-pembimbing jika belum ada.

## 8. Halaman Mahasiswa
Mahasiswa melihat status penempatan, tempat KP, alamat, Pembimbing Dalam, Preseptor, dan status kelengkapan pembimbing. Jika belum ada assignment, mahasiswa melihat status menunggu penetapan pembimbing.

## 9. Halaman Pembimbing Dalam
Pembimbing Dalam melihat daftar mahasiswa bimbingannya dan detail penempatan. Placeholder Logbook disediakan untuk Tahap 7.

## 10. Halaman Preseptor
Preseptor melihat daftar mahasiswa KP yang ditugaskan kepadanya dan detail penempatan. Placeholder validasi logbook disediakan untuk Tahap 7.

## 11. Role dan Hak Akses
Mahasiswa hanya melihat penempatan miliknya sendiri. Pembimbing Dalam hanya melihat assignment dengan `internal_supervisor_id` miliknya. Preseptor hanya melihat assignment dengan `field_supervisor_id` miliknya. Admin dan Koordinator KP dapat mengelola semua assignment. Penguji belum diberi akses assignment.

## 12. UI/UX yang Diterapkan
UI memakai bahasa Indonesia, card, badge status, tabel responsive, filter/search, empty state, alert sukses/error, helper text, dan konfirmasi sebelum cancel assignment.

## 13. Keamanan yang Diterapkan
- Route dilindungi auth, active user, role selected, dan middleware role.
- Assignment dibuat melalui service dan transaction.
- Selection harus aktif.
- Duplikasi assignment non-batal per mahasiswa/periode dicegah oleh validasi dan `active_key`.
- Role pembimbing divalidasi server-side.
- Mahasiswa dan pembimbing dibatasi hanya pada data miliknya.
- Semua perubahan penting dicatat di `kp_assignment_logs`.

## 14. Testing
Hasil verifikasi:
- `php artisan migrate`: berhasil, tidak ada migration tertinggal.
- `php artisan test`: berhasil, 42 passed, 158 assertions.
- `npm.cmd run build`: berhasil, Vite build selesai.
- `git status`: bersih setelah commit Tahap 6.
- Commit Tahap 6 dibuat dengan pesan `Add KP assignments and supervisors`.

Test Tahap 6 mencakup akses Admin/Koordinator, larangan akses mahasiswa ke management, assignment dari selection aktif, penolakan selection dibatalkan, pencegahan duplikat assignment, assign Pembimbing Dalam, assign Preseptor, status aktif saat lengkap, visibilitas mahasiswa, visibilitas pembimbing, dan log cancel.

## 15. Cara Menjalankan
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

## 16. Catatan Kendala
Ada perubahan UI canonical Tailwind dari perbaikan Problems sebelum Tahap 6 yang masih berada di worktree. Perubahan tersebut dipertahankan karena build dan test berhasil.

## 17. Rekomendasi Tahap Berikutnya
Tahap 7 - Logbook KP.

