# TAHAP 12.2 - Global Layout Overflow dan Topbar Fix

## 1. Masalah
Pada halaman dengan tabel cukup lebar, terutama `/pembimbing-dalam/mahasiswa-bimbingan`, kolom kanan tabel dapat terpotong karena shell utama belum cukup ketat mengatur `min-w-0` dan overflow. Area topbar kanan juga dapat terlihat berantakan ketika nama user dan label role panjang, misalnya "Pembimbing Dalam / Dosen".

## 2. Perbaikan Layout Global
- Root app diberi `overflow-x-hidden` agar konten tidak memaksa viewport melebar.
- Main shell setelah sidebar diberi `min-w-0` dan `overflow-x-hidden`.
- Header/topbar diberi `flex-none`, `overflow-hidden`, dan container `min-w-0`.
- Content container diperluas ke `max-w-screen-2xl`, tetap `w-full`, `mx-auto`, dan `min-w-0`.
- Main content diberi `overflow-x-hidden` agar scroll horizontal dikendalikan oleh wrapper tabel/card, bukan body.

## 3. Perbaikan Table Responsive
- `.si-table-wrap` diubah menjadi wrapper horizontal scroll dengan `w-full overflow-x-auto overflow-y-hidden`.
- Default table di `main` diberi `min-w-max` agar tabel tanpa class khusus tetap dapat memicu scroll horizontal di wrapper.
- Wrapper `overflow-x-auto` dan `.si-table-wrap` diberi `-webkit-overflow-scrolling: touch` untuk pengalaman scroll yang lebih baik di mobile/tablet.

## 4. Perbaikan Topbar User Menu
- Badge role dan nama user yang terpisah diganti menjadi user pill ringkas.
- Nama user dan role aktif memakai `truncate` satu baris.
- Label role panjang dipendekkan di topbar:
  - `pembimbing_dalam` menjadi `Pembimbing Dalam`
  - `pembimbing_lapangan` menjadi `Preseptor`
  - `koordinator_kp` menjadi `Koordinator KP`
- Tombol ganti peran dan logout dibuat `flex-none` agar tidak tertimpa nama/role.
- Topbar kanan memakai `min-w-0`, `flex-wrap` di layar kecil, dan `flex-nowrap` di desktop.

## 5. File yang Diubah
- `resources/views/layouts/app.blade.php`
- `resources/css/app.css`

## 6. Testing
- `php artisan test`: berhasil, 77 passed, 376 assertions.
- `npm run build`: berhasil.
- `git status`: clean setelah commit.

## 7. Catatan
Perbaikan hanya menyentuh layout global, overflow table, dan tampilan topbar. Tidak ada perubahan logic bisnis, route, middleware, migration, atau service.

