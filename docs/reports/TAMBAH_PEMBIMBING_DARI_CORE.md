# Tambah Pembimbing Dalam dari Core

## Penggunaan

1. Buka Pembimbing Dalam, pilih program, lalu klik Tambah Pembimbing Dalam.
2. Cari dosen berdasarkan nama, email, atau NIDN melalui direktori Core, dan klik hasil yang sesuai.
3. Klik Tambahkan ke Semua Wahana. Hanya dosen pilihan yang diaktifkan pada seluruh wahana aktif program.
4. Kembali ke Peserta PKPA untuk memilih mahasiswa dan menjalankan penggantian pembimbing melalui preview serta konfirmasi terpisah.

Tautan Tambah dosen dari Core juga tersedia pada form penggantian pembimbing dan dibuka di tab baru.

## Batas dan Keamanan

- Tidak membuat akun lokal, akun Core, password, role, atau app access baru.
- Identitas dosen diambil ulang berdasarkan Core ID saat POST. Akun inactive, role tidak sesuai, dan akses MY PKPA yang belum diberikan ditolak.
- Jika dosen tidak muncul, Admin Core perlu memeriksa identitas dosen, status akun, dan akses MY PKPA di Core Farmasi. Jangan membuat akun duplikat untuk mengatasi pencarian kosong.
- Penambahan satu dosen tidak mengaktifkan kembali dosen lain dan tidak mengubah penugasan mahasiswa.
- Mendukung dosen yang sebelumnya pernah didaftarkan/soft-deleted tanpa membuat eligibility duplikat. Batas beban, tanggal efektif, catatan, dan pencipta data eligibility existing dipertahankan.
- Program selesai/arsip dan program tanpa wahana aktif ditolak.
- Form single mewajibkan memilih dosen. Sinkronisasi seluruh program yang sudah ada tetap tersedia sebagai aksi terpisah.
- Pencarian menggabungkan direktori dosen dan daftar app access Pembimbing Dalam, lalu menormalisasi, memfilter akses/role/status, dan menduplikasi berdasarkan Core ID.

## Verifikasi

- Tahap03PkpaCapacitySupervisorTest: 11 tes / 181 assertions.
- CoreDirectorySearchControllerTest: 13 tes / 65 assertions.
- PkpaCoreResolverNormalizationTest: 3 tes / 10 assertions.
- Tahap05PkpaPublicationPortalTest: 24 tes / 518 assertions.
- Total: 51 tes / 774 assertions lulus. Vite build dan cache kompilasi Blade berhasil.
- Belum memverifikasi identitas/status Dewi pada Core produksi atau melakukan penambahan data di VPS.

## VPS

```bash
cd /var/www/kppspa-farmasi
git pull --ff-only origin main
php artisan optimize:clear
```

Tidak perlu migrasi. Memperbarui kode tidak otomatis menambahkan Dewi atau mengganti pembimbing mahasiswa.
