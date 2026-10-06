# Antrean Validasi: UI dan UX

## Perubahan

- Filter mahasiswa, wahana, dan rentang tanggal mempunyai tinggi 44 px, border dan padding eksplisit, serta susunan responsif.
- Ringkasan penempatan/logbook dipadatkan menjadi tiga kolom termasuk pada ponsel. Angka penempatan tidak lagi diberi label seolah merupakan mahasiswa unik.
- Checkbox dan judul logbook sejajar di kiri; judul menggunakan ruang tersisa, bukan tersebar oleh justify-between.
- Header mahasiswa memiliki checkbox untuk memilih semua logbook miliknya pada halaman saat ini. Tidak memilih item tersembunyi atau halaman lain.
- Toolbar massal menampilkan jumlah pilihan, checkbox sebagian (indeterminate), catatan opsional, dan tombol yang nonaktif tanpa pilihan. Pilihan per mahasiswa, individual, dan semua halaman saling tersinkron.
- Baris terpilih diberi latar ringan. Keputusan tetap menggunakan konfirmasi dan validasi/otorisasi server yang sudah ada.
- Komponen daftar dan toolbar bersama juga memperbaiki konsistensi tampilan pengguna lainnya tanpa mengubah aturan penilaian.

## Verifikasi

- Tahap06: 18 tests passed, 128 assertions.
- Tahap14 (komponen massal juga digunakan portofolio): 23 tests passed, 316 assertions.
- Build Vite dan kompilasi Blade berhasil.
- Playwright pada Edge headless menggunakan HTML hasil request Laravel dengan data fixture pengujian, bukan akun/data VPS.
- Desktop 1440x1100 dan mobile 390x844: screenshot diperiksa; input filter 44 px; tidak ada overflow horizontal dokumen.
- Checkbox per mahasiswa, semua halaman, pilihan individual, jumlah pilihan, tombol aktif, dan keadaan sebagian diuji melalui browser.
- Screenshot lokal di storage/app/validation-desktop.png dan validation-mobile.png. Logo pada fixture offline tidak dimuat karena URL localhost tidak dilayani; pemeriksaan ditujukan ke area antrean yang diubah.
- Tidak ada migrasi atau perubahan data produksi. Pengujian seluruh suite tidak diulang untuk patch tampilan ini; baseline sebelumnya memiliki 26 kegagalan legacy.

## VPS

```bash
cd /var/www/kppspa-farmasi
git pull --ff-only origin main
npm run build
php artisan optimize:clear
```
