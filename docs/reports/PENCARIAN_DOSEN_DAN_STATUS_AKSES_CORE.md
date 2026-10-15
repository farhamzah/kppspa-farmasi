# Pencarian Dosen dan Status Akses Core

## Masalah

Pencarian eligible sebelumnya menyembunyikan dosen yang tidak aktif, tidak memiliki akses MY PKPA, atau tidak memiliki role yang sesuai. Pesan Data tidak ditemukan di Core tidak membedakan akun yang memang tidak ditemukan dari akun yang ada tetapi belum eligible pada MY PKPA. Akses KP/TA dan PKPA berbeda di Core.

## Perubahan

- Endpoint kandidat khusus penambahan dosen mencari direktori dosen, direktori akun, dan app access Pembimbing Dalam.
- Hasil tetap memerlukan pencocokan nama/email/identifier dengan kata pencarian, deduplikasi Core ID, dan batas hasil.
- Akun yang ada tetapi belum memenuhi status/akses/role ditampilkan dengan alasan dan tombol disabled, bukan dianggap tidak ada di Core.
- Profil dosen yang belum terhubung ke akun tidak memakai ID profil sebagai Core user ID.
- Pencarian minimal dua karakter. Endpoint hanya Admin/Koordinator dengan query dan limit tervalidasi.
- Endpoint direktori eligible yang digunakan alur lain tidak dilonggarkan. POST penambahan masih mengambil ulang dan memvalidasi status, identitas, role, dan akses dari Core.
- Tidak ada pembuatan akun, pemberian role/akses, perubahan password, atau perubahan aplikasi Core.

## Pemeriksaan di Core

Di Core, buka Access Control > Akses Aplikasi User. Cari Dewi memakai akun existing. Periksa aplikasi `kppspa-farmasi`/MY PKPA, role Pembimbing Dalam, toggle Akses aktif, dan periode akses. Jangan mengubah akses KP/TA yang sudah ada atau membuat akun duplikat.

Status Dewi di Core produksi belum diverifikasi dari workspace ini. Hasil pencarian baru membantu membedakan kemungkinan penyebab, bukan bukti bahwa akses tertentu sudah diberikan.

## Verifikasi

- CoreDirectorySearchControllerTest: 16 tes / 81 assertions.
- Tahap03PkpaCapacitySupervisorTest: 11 tes / 181 assertions.
- Total 27 tes / 262 assertions lulus. Vite build dan cache Blade berhasil.
- Tes meliputi akun existing tanpa akses tampil disabled, fallback direktori akun, profil tanpa user tidak menjadi ID akun, pencarian bounded, dan POST tanpa akses tetap ditolak.

## VPS

```bash
cd /var/www/kppspa-farmasi
git pull --ff-only origin main
php artisan optimize:clear
```

Tidak perlu migrasi. Muat ulang form penambahan lalu cari Dewi kembali.
