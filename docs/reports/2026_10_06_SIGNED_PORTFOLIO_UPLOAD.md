# Scan Portofolio Bertanda Tangan

## Keputusan

- Semua wahana memakai scan hardcopy bertanda tangan mahasiswa dan Preseptor sebagai lampiran wajib sebelum pengiriman/persetujuan final oleh Pembimbing Dalam.
- Preseptor tidak perlu masuk aplikasi. Pernyataan mahasiswa bukan verifikasi otomatis tanda tangan; Pembimbing Dalam memeriksa PDF secara manual.
- Isi digital tetap disimpan. Scan tidak menggantikan data portofolio.
- Dokumen yang sudah final sebelum perubahan ini tidak dibatalkan secara retroaktif.

## Implementasi

- Upload PDF maksimal 20 MB melalui bagian Portofolio Bertanda Tangan pada halaman mahasiswa.
- Penamaan unduhan otomatis: `Portofolio_PKPA_[wahana]_[npm]_[nama]_Bertanda_Tangan_v01.pdf`. Bagian identitas dinormalisasi menjadi ASCII, underscore, dan panjang terbatas. Nama asli unggahan tidak digunakan.
- Penggantian membuat versi berikutnya; file lama tidak ditimpa. Versi yang diperiksa dicatat dalam audit persetujuan.
- PDF disimpan pada disk private local. Pratinjau dan unduhan memeriksa izin akses di server.
- Checksum isi digital mendeteksi perubahan setelah upload, sehingga scan terbaru diperlukan sebelum pemeriksaan final.
- Mahasiswa dengan portofolio terkirim tetapi belum final dapat menambahkan scan tanpa membuka ulang isian digital. Dokumen final terkunci perlu dibuka oleh koordinator untuk revisi.
- Tampilan Pembimbing Dalam, koordinator, dan Preseptor menyertakan pratinjau/unduhan scan yang diizinkan.
- Ekspor Word/PDF diperbarui untuk menyediakan baris Tanda Tangan Mahasiswa dan Tanda Tangan Preseptor. Versi generator dinaikkan agar ekspor lama tidak ditimpa.
- Konfigurasi `PKPA_PORTFOLIO_SIGNED_PDF_REQUIRED` default true.

## Verifikasi Lokal

- Migrasi berhasil pada database lokal, menggunakan nama foreign key pendek yang kompatibel dengan MySQL.
- Suite Tahap14: 23 passed, 316 assertions.
- Suite keseluruhan: 399 tests, 2918 assertions, 26 failures, 0 errors. Daftar 26 kegagalan identik dengan baseline sebelum fitur ini; terutama modul KP legacy. Tidak ada kegagalan baru.
- Pengujian mencakup pemilik salah, akses baca tidak sah, file non-PDF, konfirmasi tanda tangan wajib, scan wajib sebelum submit, versi pengganti, perubahan isi, dan penguncian dokumen final.
- Build Vite, kompilasi Blade, optimize:clear, dan git diff --check berhasil.
- Tidak dilakukan deploy VPS atau pemeriksaan visual browser dalam patch ini.

## Deploy VPS

Backup database dan private storage terlebih dahulu, lalu:

```bash
cd /var/www/kppspa-farmasi
git pull --ff-only origin main
php artisan migrate --force
npm run build
php artisan optimize:clear
```

Pastikan PHP `upload_max_filesize` minimal 20M dan `post_max_size` minimal 24M. Untuk Nginx, `client_max_body_size` minimal 24M. Sesuaikan dan reload layanan apabila batas server lebih kecil. Jangan menjalankan migrate:fresh.

File private perlu disertakan dalam backup rutin. Tanda tangan/keterbacaan diperiksa manusia, bukan disimpulkan dari MIME file atau checkbox.
