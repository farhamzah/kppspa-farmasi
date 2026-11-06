# Revisi PKPA Puskesmas 6 November 2026

- Periode 10 November sampai 17 Desember 2026, inklusif. Hari operasional
  Senin-Jumat menghasilkan 28 hari efektif, tanpa pengecualian libur tambahan.
- Plotting tetap 36 mahasiswa di 29 Puskesmas. Publikasi ulang membuat revisi baru;
  snapshot lama dan wahana lain dipertahankan. Jika peserta, lokasi, atau
  pembimbing Puskesmas berbeda dari publikasi aktif, penerbitan dibatalkan.
- Runtime yang masih terjadwal/siap disinkronkan. Runtime yang sudah berjalan
  dan memerlukan pemeriksaan membatalkan transaksi publikasi.
- Referensi Word 6 November disimpan tanpa modifikasi. Ekspor format Puskesmas
  v2 menambah formulir bimbingan Preseptor dan Dosen Pembimbing, masing-masing
  delapan baris kosong untuk kegiatan dan tanda tangan. Nama jabatan Ketua
  Program Studi Pendidikan Profesi Apoteker disesuaikan. Skema nilai dan
  validasi daring tidak berubah.

Setelah perubahan kode tersedia di VPS, jalankan preview plotting:

```bash
php artisan pkpa:plot-puskesmas-2026
```

Periksa tanggal, hari operasional, dan peserta. Jika sesuai, buat rancangan
revisi, kemudian preview publikasi:

```bash
php artisan pkpa:plot-puskesmas-2026 --apply
php artisan pkpa:publish-puskesmas-2026
```

Terbitkan dengan `php artisan pkpa:publish-puskesmas-2026 --apply` hanya setelah
preview lulus. Jangan ubah publikasi lama langsung di database.
