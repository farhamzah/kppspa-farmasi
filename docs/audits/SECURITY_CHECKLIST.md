# Security Checklist - SI-KP Farmasi UBP

## Authentication
- [x] Semua halaman aplikasi utama memakai `auth`.
- [x] Multi-role session divalidasi melalui middleware `role.selected` dan role middleware.
- [x] User inactive tidak bisa login.
- [x] Password di-hash oleh Laravel.
- [x] Logout invalidates session dan regenerate token.

## Authorization
- [x] Mahasiswa hanya mengakses data miliknya.
- [x] Pembimbing Dalam hanya mengakses mahasiswa bimbingannya.
- [x] Preseptor hanya mengakses mahasiswa tugasnya.
- [x] Penguji hanya mengakses sidang/nilai yang ditugaskan.
- [x] Admin/Koordinator mengakses area management sesuai kebutuhan.

## File Upload
- [x] Upload file divalidasi tipe dan ukuran.
- [x] File disimpan di storage non-public untuk dokumen KP.
- [x] Download file lewat route protected.
- [x] Path file asli tidak diekspos sebagai URL publik aplikasi.

## CSRF dan Session
- [x] Form POST/PUT/DELETE memakai CSRF.
- [x] 419 ditangani ramah dan diarahkan ke login.
- [x] `.env.example` mencantumkan session config lokal dan catatan production.

## Input Validation
- [x] Nilai divalidasi 0-100.
- [x] Email user unik.
- [x] Data profil kunci seperti NIM/NIDN dikelola server-side.
- [x] File import divalidasi.
- [x] Jadwal sidang divalidasi.
- [x] Kuota selection memakai transaction dan `lockForUpdate()`.

## Production
- [ ] Set `APP_DEBUG=false`.
- [ ] Pastikan `.env` tidak public.
- [ ] Aktifkan backup database.
- [ ] Ganti/nonaktifkan password demo.
- [ ] Gunakan HTTPS dan `SESSION_SECURE_COOKIE=true`.
- [ ] Review permission storage dan `bootstrap/cache`.

## TODO / Known Issue
- Route `storage/{path}` bawaan Laravel hanya untuk kebutuhan local/development. File upload modul KP tetap melalui route protected. Pada production, pastikan file private tidak disajikan langsung dari public web root.
- Notifikasi email/WhatsApp belum production-ready.

