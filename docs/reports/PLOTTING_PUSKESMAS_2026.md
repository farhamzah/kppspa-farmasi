# Plotting Puskesmas November-Desember 2026

## Sumber dan batas perubahan

- Sumber: tabel penempatan dari pemilik proyek, dikonfirmasi 22 Oktober 2026.
- Periode yang berlaku: 10 November sampai 7 Desember 2026, bukan judul gambar yang menyebut Oktober.
- 36 mahasiswa dan 29 Puskesmas. Nama dipakai untuk pencocokan unik; NIM selalu diambil dari aplikasi.
- Pembimbing Dalam mengikuti pergantian mahasiswa terakhir yang sudah diterapkan; fallback ke publikasi PBF, lalu Apotek. Tidak menyalin nama dosen lama dari gambar.
- Preseptor dicatat sebagai profil mitra eksternal di Core dan kontak tempat di PKPA. Tidak dibuatkan akun, password, atau akses aplikasi baru.
- Command PKPA hanya membuat versi rancangan baru. Publikasi resmi, runtime, presensi, logbook, portofolio, dan nilai tidak diterbitkan/diubah.

## Langkah VPS

Pastikan backup database Core dan PKPA tersedia. Update kedua repository dengan working tree bersih. Jalankan preview dahulu dan cocokkan semua identitas, nomor telepon, dan pembimbing.

Core Farmasi:

```sh
cd /var/www/core-farmasi
git pull --ff-only origin main
php artisan optimize:clear
php artisan core:import-pkpa-puskesmas-preceptors-2026
# Setelah preview disetujui:
php artisan core:import-pkpa-puskesmas-preceptors-2026 --apply
```

MY PKPA:

```sh
cd /var/www/kppspa-farmasi
git pull --ff-only origin main
php artisan optimize:clear
php artisan pkpa:plot-puskesmas-2026 --program=PKPA-2026-G1
# Setelah preview disetujui:
php artisan pkpa:plot-puskesmas-2026 --program=PKPA-2026-G1 --apply
```

Periksa rancangan current melalui Penyusunan Penempatan. Jalankan validasi dan alur review/publikasi aplikasi setelah diperiksa. Mahasiswa belum melihat rancangan sebelum publikasi.

## Pengamanan

- Pencocokan ambigu, NIM kosong/duplikat, tempat tidak aktif, kerja sama kedaluwarsa, atau kapasitas tidak cukup menghentikan proses.
- Periode baru hanya menyalin konfigurasi operasional/kapasitas yang sudah ada; konfigurasi yang belum tersedia harus diisi pengelola terlebih dahulu.
- Penyimpanan tetap melewati service validasi jadwal, kapasitas, masa efektif dan beban pembimbing. Kegagalan satu baris membatalkan seluruh transaksi.
- Rancangan sumber dan publikasi lama tetap disimpan. Salinan draft target yang baru dibuat dalam transaksi dapat dibuang untuk menghindari konflik kunci unik soft delete.
- Menjalankan ulang pada rancangan hasil import yang masih sesuai tidak membuat versi tambahan. Perubahan manual setelah import tidak ditimpa otomatis.
- Core menolak konflik identitas, memakai profil yang sesuai, dan tidak memodifikasi akun terhubung.

## Verifikasi

- Pengujian Core khusus import: 3 lulus, 15 assertions.
- Pengujian publikasi/plotting PKPA: 28 lulus, 759 assertions, termasuk 4 pengujian plotting (identitas/NIM, pewarisan pembimbing, idempotensi, kapasitas, rollback, preservasi publikasi/runtime).
- Build frontend PKPA: lulus.
- Pemeriksaan Pint untuk command dan test terkait: lulus.
- Suite seluruh aplikasi belum hijau. Percobaan penuh dihentikan setelah sejumlah kegagalan; pengulangan stop-on-failure menghasilkan 112 lulus, 1 gagal, 323 belum dijalankan. Kegagalan pertama adalah assertion lama `IntegrationReviewScreenTest` yang meminta teks `Pratinjau Dokumen KP untuk TU`, sedangkan view pada HEAD sudah bertuliskan `Pratinjau Dokumen PKPA untuk TU`. File integrasi tersebut tidak diubah patch ini; tidak diklaim seluruh regression lulus.
- Pengujian menggunakan SQLite in-memory; penerapan MySQL di VPS belum dilakukan oleh agen.
- Status: persiapan lokal, belum deployment. Persetujuan commit/push terpisah; perubahan Core lain di workspace tidak termasuk patch ini.

## Koreksi Berdasarkan Preview VPS

- Output VPS mengonfirmasi 29 profil mitra sudah tersimpan tanpa akun baru.
- Seluruh tempat Puskesmas aktif, tetapi `pkpa_program_domain_id` tempat masih mengarah ke PKM lama yang sengaja dinonaktifkan migrasi normalisasi. Command kini menampilkan jumlah tautan yang akan diperbaiki di preview, lalu memperbaiki hanya tautan PKM-ke-PEM yang terverifikasi dalam transaksi plotting. PKM lama tidak diaktifkan dan requirement/rotasi lama tidak dipindahkan secara massal.
- Perbedaan tautan lain, wahana target nonaktif, atau requirement yang tidak cocok tetap menjadi blocker.
- Telagasari mengikuti nama tempat aplikasi; Core menerima alias institusi Tegalsari yang dahulu diimport dan mengoreksinya tanpa profil baru.
- Alias mahasiswa terverifikasi dari output VPS: Farah Sulistyaningtyas dan Novi Elisa Nurhasanah. Nama/NIM aplikasi tidak diubah.
- Tes khusus plotting setelah koreksi: 6 lulus, 271 assertions. Tes Core import: 4 lulus, 21 assertions. Pint dan build frontend lulus.
- Pengujian seluruh aplikasi sebelumnya belum hijau sebagaimana dicatat di atas; koreksi ini tidak mengklaim menuntaskan kegagalan legacy tersebut.

## Periode Putaran Lama dari VPS

- Data VPS menunjukkan satu periode per tempat, bukan duplikasi. Sebanyak 22 tempat belum mencakup seluruh rotasi karena memakai 10-27 November atau 30 November-17 Desember 2026.
- Untuk program PKPA-2026-G1 dan hanya satu periode dengan tanggal lama tersebut, command menampilkan rencana perluasan di preview. Saat apply, periode yang sama diperluas dengan gabungan batas lama dan 10 November-7 Desember. Periode yang sudah mencakup rotasi tidak dipersempit.
- ID periode, kapasitas, reserved slots, hari/jam operasional tetap dipertahankan. Tanggal assignment/publikasi/runtime lama tidak ditulis ulang. Tidak dibuat periode tambahan yang bertumpuk.
- Pembaruan periode ikut transaksi plotting dan audit before/after; kegagalan pada assignment berikutnya mengembalikan tanggal periode juga. Baris availability dikunci dan snapshot konfigurasi dimasukkan dalam pemeriksaan stale preview.
- Beberapa periode, tanggal lain yang belum terverifikasi, atau kapasitas tidak cukup tetap ditolak untuk pemeriksaan manual. Core tidak perlu diubah lagi untuk koreksi ini.
- Verifikasi koreksi periode: 8 tes plotting lulus (306 assertions), seluruh file tes publikasi terkait 32 lulus (824 assertions), Pint dan build frontend lulus. Data VPS belum diubah oleh patch lokal ini.
