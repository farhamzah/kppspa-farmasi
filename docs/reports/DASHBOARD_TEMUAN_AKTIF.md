# Perbaikan Hitungan Dashboard Koordinator dan Admin

## Masalah

Dashboard menghitung seluruh temuan validasi yang belum ditandai selesai dari berbagai run dan versi rancangan. Notifikasi pending juga mencampur status gagal dan publikasi lama. Empat angka terbesar otomatis dijadikan sorotan utama, sehingga riwayat temuan terlihat seperti error aplikasi saat ini.

## Perubahan

- Temuan hanya berasal dari run pemeriksaan penuh terakhir yang selesai pada rancangan current program draft/ready/active, bukan rancangan arsip.
- Rancangan yang belum diperiksa, stale, atau sedang divalidasi tidak memakai temuan pemeriksaan lama sebagai kondisi terbaru. Rancangan belum diperiksa/stale tetap muncul sebagai pekerjaan untuk diperiksa ulang; angka nol bukan klaim sudah valid.
- Notifikasi menunggu dan gagal dipisahkan, dibatasi ke publikasi published current dan kanal pengiriman yang aktif.
- Permintaan perubahan dibatasi ke publikasi terkini, termasuk status under_review.
- Pengingat sinkronisasi hanya menghitung pembimbing aktif yang punya Core ID, unik per akun. Preseptor manual tanpa akun tidak menjadi pengingat sinkronisasi.
- Sorotan utama memakai indikator tetap: peserta aktif, tempat praktik aktif, penempatan resmi, dan temuan terbaru atau rancangan perlu diperiksa.
- Label Error diganti Temuan wajib diperbaiki, Warning menjadi Peringatan rancangan. Kapasitas diberi label total slot terdaftar (bukan sisa), dan kelompok diberi keterangan opsional.

Tidak ada data akademik, riwayat validasi, atau notifikasi lama yang dihapus. Temuan validasi terbaru yang masih nyata tetap ditampilkan dan perlu ditindaklanjuti pada menu Penyusunan Penempatan.

## Verifikasi

- Tahap05PkpaPublicationPortalTest: 22 tes, 450 assertions.
- KpRecapExportAndDashboardTest: 5 tes, 55 assertions.
- Pengujian meliputi run lama tidak terakumulasi, arsip diabaikan, resolved tidak terhitung, stale ditandai, notifikasi lama/kanal nonaktif diabaikan, pending/gagal dipisahkan, dan preseptor manual tidak diminta sinkronisasi.

## VPS

```bash
cd /var/www/kppspa-farmasi
git pull --ff-only origin main
php artisan optimize:clear
```

Tidak perlu migrasi atau command perbaikan data. Muat ulang dashboard setelah kode diperbarui. Pemeriksaan data produksi tetap diperlukan bila temuan terbaru masih tampil.
