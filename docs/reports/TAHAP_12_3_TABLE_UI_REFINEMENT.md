# TAHAP 12.3 - Table UI Refinement

## 1. Masalah
Beberapa tabel, terutama halaman `/pembimbing-dalam/mahasiswa-bimbingan`, terasa terlalu melebar, jarak kolom terlalu jauh, row terasa kosong, dan area aksi tampak terlalu jauh di sisi kanan. Global table style juga masih terlalu agresif dalam memaksa lebar tabel sehingga halaman dengan sedikit kolom dapat terlihat seperti card besar yang kosong.

## 2. Perbaikan Global Table Style
- Menambahkan class global `si-table-card`, `si-data-table`, dan `si-table-action`.
- `.si-table-wrap` dirapikan sebagai table card responsive dengan border slate lembut dan horizontal scroll.
- Default tabel di area `main` dibuat lebih compact dengan header `py-3`, cell `py-3.5`, border row halus, dan hover state ringan.
- Minimum width table wrapper diturunkan ke `max(100%, 48rem)` agar tabel normal tidak terlalu melebar, tetapi tetap scroll horizontal di layar sempit.
- Badge dalam tabel dibuat compact dan konsisten.
- Action button tabel dibuat lebih readable dengan `px-4 py-2`, border teal, hover soft, dan focus ring.

## 3. Perbaikan Halaman Mahasiswa Bimbingan
- Tabel diubah menggunakan `si-table-wrap` dan `si-data-table`.
- Ditambahkan `colgroup` agar kolom Mahasiswa, Periode, Tempat, Preseptor, Status, dan Aksi lebih proporsional.
- Row dibuat lebih compact, nama mahasiswa dan NIM disusun rapi.
- Badge status dibuat compact.
- Tombol `Detail` memakai class `si-table-action` agar tidak terasa terlalu kecil atau terlalu jauh secara visual.
- Empty state tetap dipertahankan.

## 4. Halaman yang Diaudit
Audit dilakukan pada pola tabel di:
- `/pembimbing-dalam/mahasiswa-bimbingan`
- `/pembimbing-dalam/logbook`
- `/pembimbing-dalam/laporan-akhir`
- `/pembimbing-dalam/jadwal-sidang`
- `/pembimbing-lapangan/mahasiswa-kp`
- `/pembimbing-lapangan/logbook`
- `/penguji/jadwal-sidang`
- `/penguji/penilaian`
- `/management/kp-assignments`
- `/management/logbooks`
- `/management/final-reports`
- `/management/exam-requests`
- `/management/exams`
- `/management/scores`
- `/management/recaps`
- `/mahasiswa/logbook`

Prioritas perubahan diberikan pada global CSS dan halaman contoh yang bermasalah agar risiko regression tetap kecil.

## 5. File yang Diubah
- `resources/css/app.css`
- `resources/views/internal-supervisor/assignments/index.blade.php`
- `resources/views/internal-supervisor/final-reports/index.blade.php`
- `resources/views/management/final-reports/index.blade.php`

## 6. Testing
- `php artisan test`: berhasil, 77 passed, 376 assertions.
- `npm run build`: berhasil.
- `git status`: clean setelah commit.

## 7. Catatan
Tidak ada perubahan business logic, route, middleware, migration, atau service. Perubahan hanya pada style tabel, table card, density, dan readability.

