# Presensi Langsung ke Pembimbing Dalam

## Masalah dan Keputusan

Layanan presensi masih mengotorisasi Preseptor, dan tombol/flash mahasiswa masih menyebut pengiriman ke Preseptor. Presensi sekarang diperiksa Pembimbing Dalam untuk semua wahana, tanpa persetujuan Preseptor dalam aplikasi. Hal ini berlaku juga untuk koreksi presensi, terlepas dari flag legacy validasi dokumen Preseptor.

## Implementasi

- Layanan review presensi memeriksa Pembimbing Dalam aktif pada penempatan; koordinator/admin tetap mengikuti akses layanan yang sudah ada.
- Endpoint lama pemeriksaan presensi/koreksi Preseptor memberikan 403. Tampilan Preseptor menjadi baca saja dan tidak menampilkan form persetujuan presensi.
- Teks pengiriman, status mahasiswa, petunjuk lama logbook, dan deskripsi peran diselaraskan.
- Tab Presensi tersedia di Pemantauan Mahasiswa, juga pada detail mahasiswa. Default antrean Perlu Diperiksa, dengan filter mahasiswa/tempat, wahana, tanggal, dan status Disetujui/Revisi/Semua.
- Checkbox individual, seluruh presensi mahasiswa pada halaman ini, dan semua item halaman tersedia. Tidak memilih item tersembunyi atau halaman lain.
- Persetujuan massal maksimal 100 ID berbeda. Batch menggunakan transaksi dan lock baris; ID hilang, tidak berwenang, atau sudah diputuskan membatalkan seluruh batch.
- Persetujuan, permintaan revisi, dan penolakan individual tersedia. Revisi/penolakan wajib memiliki catatan. Presensi selesai tidak dapat diedit langsung atau diputuskan ulang.
- Permintaan koreksi tersedia untuk Pembimbing Dalam pada riwayat/semua presensi. Keputusan koreksi diaudit dan dilindungi dari pengulangan.
- Tidak ada migrasi/data rewrite. Presensi lama berstatus submitted otomatis terbaca antrean Pembimbing Dalam; keputusan final dan identitas pemeriksa lama tidak diubah.
- Kolom historis field_supervisor_notes tetap dipakai sebagai catatan pemeriksaan untuk kompatibilitas, sedangkan reviewed_by_core_user_id mencatat pemeriksa sebenarnya.
- Upload rekap presensi bertanda tangan belum ditambahkan pada patch ini; permintaan saat ini menyangkut pengiriman dan validasi massal presensi.

## Verifikasi

- Tahap06 dan Tahap14: 42 passed, 479 assertions. Tahap06 terakhir setelah penyempurnaan koreksi: 19 passed, 163 assertions.
- Full suite: 400 tests, 2958 assertions, 26 failures, 0 errors. Daftar kegagalan identik dengan 26 kegagalan legacy baseline sebelum patch.
- Pengujian mencakup penolakan Preseptor, pembimbing tidak berwenang, ID hilang/duplikat, rollback batch campuran, catatan revisi wajib, persetujuan massal, larangan pengulangan, antrean selesai, dan koreksi.
- Build Vite, Blade cache, dan git diff --check berhasil.
- Playwright Edge headless memeriksa fixture HTML Laravel pada desktop 1440x1100 dan mobile 390x844: tinggi filter 44 px, tidak ada overflow horizontal dokumen, pilihan per mahasiswa/semua/sebagian dan jumlah pilihan bekerja. Screenshot lokal storage/app/attendance-desktop.png dan attendance-mobile.png. Logo offline fixture tidak dilayani; data bukan VPS.
- Penyesuaian akhir ringkasan angka presensi dan border section diverifikasi melalui render/test Blade setelah pemeriksaan browser.

## Deploy

```bash
cd /var/www/kppspa-farmasi
git pull --ff-only origin main
npm run build
php artisan optimize:clear
```

Tidak diperlukan migrasi, seeder, atau pengiriman ulang presensi mahasiswa. Setelah update, Pembimbing Dalam membuka Pemantauan Mahasiswa > Presensi > Perlu Diperiksa.
