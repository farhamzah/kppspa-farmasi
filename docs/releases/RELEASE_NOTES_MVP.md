# Release Notes MVP - SI-KP Farmasi UBP

## Versi
MVP Internal Demo

## Ringkasan
SI-KP Farmasi UBP sudah mencakup alur utama Kerja Praktek Farmasi: user management, pendaftaran, verifikasi berkas, pemilihan tempat, penempatan pembimbing, logbook, laporan akhir, sidang, nilai akhir, rekap, dan export.

## Fitur Utama
- Setup login multi-role dan dashboard per role.
- Manajemen user, import Excel, dan profil pengguna.
- Manajemen periode KP, tempat KP, kuota tempat, dan log kuota.
- Pendaftaran KP dan verifikasi berkas.
- Pemilihan tempat KP first come first served berbasis kuota.
- Penempatan KP dan penentuan pembimbing.
- Logbook KP dan validasi preseptor.
- Laporan akhir KP dan review pembimbing dalam.
- Pengajuan dan penjadwalan sidang KP.
- Penilaian KP dan nilai akhir.
- Rekap dan export Excel.
- Seed demo end-to-end dan dokumen UAT.

## Role yang Didukung
- Mahasiswa
- Admin
- Koordinator KP
- Pembimbing Dalam
- Preseptor
- Penguji

## Akun Demo
Password development: `password`.

| Role | Email |
|---|---|
| Admin | `admin@sikp.test` |
| Koordinator KP | `koordinator@sikp.test` |
| Mahasiswa lengkap | `mahasiswa@sikp.test` |
| Mahasiswa berjalan | `mahasiswa2@sikp.test` |
| Pembimbing Dalam 1 | `dosen@sikp.test` |
| Pembimbing Dalam 2 | `dosen2@sikp.test` |
| Preseptor | `lapangan@sikp.test` |
| Penguji | `penguji@sikp.test` |

Akun dan password demo hanya untuk development/UAT internal, tidak boleh dipakai production.

## Cara Menjalankan Demo Lokal
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

## Status Kesiapan
- Siap UAT/demo internal.
- Belum production final sebelum konfigurasi server, HTTPS, backup, credential, dan security production selesai.

## Known Limitations
- Notifikasi email/WhatsApp belum aktif.
- Tanda tangan digital belum ada.
- Sertifikat otomatis belum ada.
- Export PDF resmi/berita acara belum ada.
- Aturan bobot nilai masih fleksibel/default dan perlu disesuaikan kebijakan resmi.
- Integrasi SSO kampus belum ada.

