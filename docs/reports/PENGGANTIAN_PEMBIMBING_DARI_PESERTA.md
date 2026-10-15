# Penggantian Pembimbing Dalam dari Peserta PKPA

## Cara Menggunakan

1. Masuk sebagai Koordinator PKPA dan buka Peserta PKPA.
2. Klik Ganti Pembimbing pada baris mahasiswa, atau centang beberapa mahasiswa dari program yang sama dan klik Ganti Pembimbing Dalam.
3. Pilih dosen pengganti, tanggal serah terima, dan alasan. Periksa daftar seluruh wahana yang sudah dipublikasikan.
4. Klik Tinjau Penggantian. Ini hanya membuat permintaan draf, belum mengubah penugasan.
5. Periksa pembimbing lama/baru dan tanggal efektif setiap wahana, lalu klik Konfirmasi dan Terapkan.

Tautan yang sama tersedia dari Detail Peserta. Mahasiswa yang belum memiliki penempatan resmi tidak dapat dipilih. Pilihan halaman ini hanya mencakup halaman daftar yang sedang dibuka, bukan seluruh hasil pencarian.

## Perilaku dan Pengamanan

- Pemilihan mahasiswa dibatasi satu program dan seluruh ID divalidasi di server. Mahasiswa/pembimbing tidak dapat mengakses aksi penggantian.
- Dosen pengganti harus eligible pada setiap wahana yang dialihkan. Preview lama tidak boleh diterapkan setelah publikasi sumber diganti.
- Semua penempatan resmi mahasiswa diproses meskipun pembimbing lama berbeda antarwahana. Penempatan yang sudah memakai dosen baru tidak dibuatkan penggantian berulang.
- Rotasi yang sudah berakhir dapat dialihkan tanggung jawab pemeriksaannya sejak tanggal serah terima, bila runtime sudah tersedia. Periode praktik tidak berubah dan rentang penugasan pengganti tidak dibuat terbalik.
- Riwayat pembimbing lama tidak dihapus atau diperpanjang melewati tanggal akhir yang sudah tercatat. Persetujuan, komentar, dan nilai final sebelumnya tetap milik dosen asal. Penilaian draf yang belum dikirim diteruskan untuk ditinjau pengganti melalui mekanisme yang sudah ada.
- Untuk wahana yang belum dijadwalkan, penyimpanan penempatan berikutnya memeriksa penggantian terakhir yang sudah diterapkan per mahasiswa. Pemilihan dosen lama ditolak dengan pesan agar memilih pengganti. Tidak membuat jadwal baru secara otomatis.
- Tidak mengubah akun/role Core dan tidak melakukan pergantian data VPS secara otomatis saat deploy.

## Verifikasi

- Tahap05PkpaPublicationPortalTest: 24 tes / 518 assertions, termasuk preview, penerapan pada lima wahana yang sudah berakhir, histori tanggal tetap, publikasi stale ditolak, program campuran ditolak, akses mahasiswa ditolak, dan penempatan berikutnya harus menggunakan pengganti.
- Regresi planner: 9 tes / 88 assertions; runtime rotasi: 19 tes / 163 assertions; dashboard/rekap: 5 tes / 55 assertions. Total pengujian terfokus: 57 tes / 824 assertions, semuanya lulus.
- Vite build berhasil.
- Playwright dengan Edge headless menggunakan HTML hasil render fixture lokal pada 1440px dan 390px: tidak ada overflow horizontal halaman; checkbox, penghitung pilihan, tombol disabled/enabled, reset pilih semua, dan daftar lima wahana lulus.
- Screenshot fixture tersimpan di workspace `.codex-temp/supervisor-ui-qa`, bukan data produksi. Pemeriksaan visual memakai fixture dan tidak menguji login Core produksi.

## Update VPS

```bash
cd /var/www/kppspa-farmasi
git pull --ff-only origin main
php artisan optimize:clear
```

Tidak perlu migrasi. Setelah update, muat ulang Peserta PKPA. Penggantian ke Dewi Darwati Agustini tetap harus dipilih dan dikonfirmasi oleh Koordinator di aplikasi; fitur tidak menebak identitas atau Core ID dosen berdasarkan nama.
