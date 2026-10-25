# Catatan Revisi pada Pemantauan Mahasiswa

## Penyebab

Review logbook tersimpan di `pkpa_logbook_reviews` dan sudah dimuat controller mahasiswa, tetapi view detail rotasi tidak merender koleksi review. Mahasiswa hanya melihat status revisi tanpa komentarnya. Catatan pemeriksa presensi juga belum ditampilkan.

## Perbaikan

- Daftar rotasi mahasiswa menampilkan jumlah logbook/presensi yang perlu revisi.
- Detail rotasi menampilkan ringkasan revisi di atas, dengan tautan ke entri, komentar lengkap, peran/nama pemeriksa jika identitas lokal tersedia, serta waktu pemeriksaan logbook.
- Logbook dan presensi berstatus revisi terbuka otomatis. Tombol edit diberi label Perbaiki Logbook/Presensi, bukan Edit Draft.
- Riwayat semua review tetap ditampilkan pada logbook setelah pengiriman ulang atau persetujuan. Catatan terakhir ikut tampil di form ketika tombol perbaikan dipilih, menggunakan textContent.
- Komentar lama dapat langsung terbaca tanpa migrasi atau perubahan status. Review yang pengguna lokalnya tidak tersedia tetap menampilkan komentar dan peran pemeriksa.
- Tidak ada perubahan alur validasi, nilai, role, kepemilikan, status pengiriman, atau penghapusan review. Komentar di-escape oleh Blade dan tidak dirender sebagai HTML aktif.

## Verifikasi dan Batasan

- Tes mencakup pembimbing meminta revisi, mahasiswa membaca komentar dan nama, indeks jumlah revisi, pengiriman ulang tanpa kehilangan riwayat, escape HTML, serta akses mahasiswa lain ditolak.
- Tes tambahan mencakup revisi presensi, tautan entri, komentar preseptor lama dan reviewer tanpa cache user lokal.
- Build frontend dan Pint lulus. Tes operasional terkait dijalankan dengan SQLite in-memory, bukan database VPS.
- Hasil seluruh file `Tahap06PkpaRotationOperationTest`: 21 tes lulus, 189 assertions. Percobaan awal sandbox gagal membaca vendor/autoload.php; pengujian diulang dengan izin baca yang sesuai dan lulus. Tidak diklaim seluruh suite aplikasi atau UAT pengguna sudah lulus.
- Pemeriksaan browser visual desktop/mobile dan pengecekan akun Diany langsung di VPS belum dilakukan karena tidak ada sesi akses tersedia.
- File manual/referensi lain yang sudah ada di workspace tidak disentuh. Patch belum diterapkan di VPS; perlu persetujuan commit/push lalu update kode/build di VPS.
