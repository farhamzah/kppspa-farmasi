# Handoff Report - SI-KP Farmasi UBP

Tanggal audit: 2026-06-01

## Ringkasan Singkat
SI-KP Farmasi UBP adalah aplikasi Laravel untuk mengelola proses Kerja Praktek Farmasi dari administrasi user sampai nilai akhir. MVP sudah mencakup alur end-to-end: login multi-role, manajemen user, pendaftaran KP, verifikasi berkas, pemilihan tempat berbasis kuota, penempatan pembimbing, logbook, laporan akhir, sidang, penilaian, rekap, export Excel, seed demo, dan dokumentasi UAT/deployment.

Status audit terakhir: aplikasi siap untuk UAT/demo internal. Production live masih perlu konfigurasi server nyata, HTTPS, credential production, backup, dan penonaktifan akun demo.

## Stack dan Lokasi Project
- Lokasi workspace: `E:\Aplikasi\farmasi-ubp-workspace`
- Aplikasi utama: `apps/kp-farmasi`
- Framework: Laravel 12, PHP 8.2+
- Frontend: Blade, Tailwind CSS 4, Vite 7
- Database target: MySQL/MariaDB
- Testing: PHPUnit feature tests
- Export: `maatwebsite/excel`

## Role Sistem
Role resmi di aplikasi:
- `admin`
- `koordinator_kp`
- `mahasiswa`
- `pembimbing_dalam`
- `pembimbing_lapangan`
- `penguji`

Kontrol akses utama:
- Semua area utama memakai middleware `auth`, `active`, `role.selected`, dan middleware role spesifik.
- Session `active_role` divalidasi terhadap role milik user.
- User multi-role diarahkan ke halaman `/pilih-role`.

## Akun Demo
Password development/UAT: `password`.

Seeder default `DatabaseSeeder` menjalankan `RoleSeeder`, `AdminSeeder`, dan `DemoUserSeeder`.
Seeder end-to-end tambahan: `DemoEndToEndSeeder`.

Akun demo end-to-end:
- `admin@sikp.test` sebagai Admin
- `koordinator@sikp.test` sebagai Koordinator KP dan Pembimbing Dalam
- `mahasiswa@sikp.test` sebagai Mahasiswa lengkap
- `mahasiswa2@sikp.test` sebagai Mahasiswa berjalan
- `dosen@sikp.test` sebagai Pembimbing Dalam
- `dosen2@sikp.test` sebagai Pembimbing Dalam
- `lapangan@sikp.test` sebagai Preseptor
- `penguji@sikp.test` sebagai Penguji

Akun demo hanya untuk development/UAT, tidak boleh dipakai production.

## Modul Yang Sudah Dibuat
1. Autentikasi dan multi-role
   - Login, logout, pilih role, dashboard per role, user inactive ditolak.
   - Middleware penting: `CheckUserActive`, `EnsureRoleSelected`, `CheckRole`.

2. Manajemen user dan import
   - Admin dapat CRUD user, reset password, toggle status.
   - Import user via Excel dengan preview, validasi, batch history, dan error per baris.
   - Profil dipisah ke `students`, `lecturers`, dan `field_supervisors`.

3. Profil dan avatar
   - User dapat edit profil sendiri.
   - Upload, lihat, dan hapus foto profil.
   - Validasi avatar JPG/JPEG/PNG/WebP maksimal 2MB, SVG tidak diizinkan.

4. Master KP
   - Periode KP, tempat KP, kuota tempat, log perubahan kuota.
   - Admin dan Koordinator KP mengelola area `/management/*`.

5. Pendaftaran dan berkas KP
   - Mahasiswa membuat pendaftaran, upload dokumen, submit pendaftaran.
   - Admin/Koordinator memverifikasi dokumen dan pendaftaran.
   - File disimpan non-public dan download lewat controller protected.

6. Pemilihan tempat KP
   - First come first served dengan transaction dan lock kuota.
   - Mahasiswa eligible dapat memilih tempat atau masuk daftar tunggu.
   - Admin/Koordinator dapat monitor, cancel, move selection, dan membuat assignment.

7. Penempatan dan pembimbing
   - Assignment dibuat dari selection aktif.
   - Pembimbing Dalam wajib role `pembimbing_dalam`.
   - Preseptor wajib role `pembimbing_lapangan`.
   - Perubahan dicatat ke log assignment.

8. Logbook KP
   - Mahasiswa membuat draft, edit, submit, upload bukti.
   - Preseptor approve/revisi/tolak logbook.
   - Pembimbing Dalam monitor dan memberi komentar.
   - Admin/Koordinator monitor semua logbook.

9. Laporan akhir KP
   - Mahasiswa upload laporan versi bertahap dan submit.
   - Pembimbing Dalam approve/revisi/tolak laporan.
   - Admin/Koordinator melakukan monitoring dan melihat log.

10. Sidang KP
    - Mahasiswa mengajukan sidang setelah laporan akhir disetujui.
    - Admin/Koordinator approve/revisi/tolak pengajuan, jadwalkan, reschedule, cancel, dan complete sidang.
    - Pembimbing Dalam dan Penguji hanya melihat jadwal terkait.

11. Penilaian dan nilai akhir
    - Komponen penilaian per periode.
    - Pembimbing Dalam, Preseptor, dan Penguji memberi nilai sesuai assignment/exam masing-masing.
    - Admin/Koordinator calculate, finalize, publish, dan unlock nilai.
    - Mahasiswa melihat nilai setelah publish.

12. Rekap dan export
    - Rekap mahasiswa, penempatan, logbook, sidang, dan nilai.
    - Export Excel hanya untuk Admin/Koordinator.

13. Integrasi Core Farmasi
    - Ada mode auth `legacy`, `core_bridge`, dan fallback.
    - Ada adapter read-only untuk master data Core.
    - Ada command preflight, health check, smoke test, dan sync mapping lokal.
    - Core integration dirancang tidak menulis ke database Core.

## File dan Folder Penting
- `AGENTS.md`: aturan utama pengembangan aplikasi.
- `routes/web.php`: definisi route dan role group utama.
- `app/Models`: model domain KP dan user.
- `app/Services`: logic bisnis utama modul KP.
- `app/Http/Controllers`: controller per area role.
- `app/Http/Requests`: validasi request per modul.
- `app/Support/RoleDashboard.php`: mapping dashboard/menu/fitur per role.
- `database/migrations`: skema database KP.
- `database/seeders`: role, admin, demo user, dan demo end-to-end.
- `tests/Feature`: regression test untuk semua modul utama.
- `docs/reports`: report tahap 1 sampai 13.2.
- `docs/uat`: checklist dan template isu UAT.
- `docs/deployment`: checklist deploy dan smoke test.
- `docs/audits`: checklist security dan route permission audit.
- `docs/releases/RELEASE_NOTES_MVP.md`: release notes MVP.

## Cara Menjalankan Lokal
Dari folder `apps/kp-farmasi`:

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan db:seed --class=DemoEndToEndSeeder
npm run dev
php artisan serve
```

Untuk verifikasi:

```bash
php artisan test
npm run build
php artisan route:list
```

## Hasil Verifikasi Terbaru
Audit lokal pada 2026-06-01:
- `git status --short`: clean
- `php artisan route:list`: berhasil, 205 routes
- `php artisan test`: berhasil, 129 passed, 613 assertions
- `npm run build`: berhasil

## Batasan Yang Masih Diketahui
- Belum ada notifikasi email/WhatsApp aktif.
- Belum ada tanda tangan digital.
- Belum ada sertifikat otomatis.
- Belum ada export PDF resmi atau berita acara.
- Integrasi SSO kampus belum ada.
- Aturan bobot nilai masih perlu disesuaikan dengan kebijakan resmi prodi bila sudah final.
- Production live perlu konfigurasi server, HTTPS, backup, credential production, dan pengamanan akun demo.

## Rekomendasi Lanjutan
Prioritas paling masuk akal setelah MVP:
1. Jalankan UAT dengan prodi memakai `docs/uat/UAT_CHECKLIST.md`.
2. Catat isu di `docs/uat/UAT_ISSUES_TEMPLATE.md`.
3. Perbaiki bug UAT tanpa melemahkan test existing.
4. Siapkan server demo/production berdasarkan `docs/deployment/PRODUCTION_CHECKLIST.md`.
5. Tambahkan fitur lanjutan setelah UAT stabil: notifikasi, PDF resmi, berita acara, sertifikat, atau tanda tangan digital.

## Prompt Lanjutan Untuk ChatGPT/Codex
Gunakan prompt berikut bila ingin meminta agent melanjutkan project:

```text
Kamu adalah Codex di workspace E:\Aplikasi\farmasi-ubp-workspace. Lanjutkan aplikasi Laravel `apps/kp-farmasi` berdasarkan `apps/kp-farmasi/AGENTS.md` dan `apps/kp-farmasi/docs/reports/HANDOFF_REPORT_2026_06_01.md`.

Kondisi terakhir: MVP SI-KP Farmasi UBP sudah end-to-end dan audit terakhir lulus `php artisan test` 129 passed, `npm run build` berhasil, route list 205 routes, git status clean. Jangan revert perubahan user. Ikuti pola Laravel yang sudah ada, gunakan service/form request/controller sesuai modul, lindungi route dengan auth/active/role.selected/role, simpan upload non-public, dan tambahkan/ubah test sesuai risiko.

Tugas lanjutan saya: [ISI TUGAS SPESIFIK DI SINI].

Sebelum mengubah kode, baca file terkait dan ikuti modul existing. Setelah selesai, jalankan test/build yang relevan dan laporkan file yang diubah, hasil verifikasi, serta catatan risiko.
```

