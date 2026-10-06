# Pemeriksaan Pembimbing Dalam dan Nilai Manual Preseptor

## Keputusan Pemilik Proyek

- Berlaku untuk seluruh wahana PKPA.
- Logbook dan portofolio mahasiswa langsung diperiksa Pembimbing Dalam.
- Preseptor tetap tercatat dan akun lama tetap dapat melihat dokumen, tetapi tidak memberikan keputusan logbook/portofolio atau mengisi nilai.
- Nilai komponen Preseptor dicatat manual oleh Admin/Koordinator dengan catatan dasar penilaian.
- Preseptor baru dapat dicatat dengan nama tanpa akun Core. Tidak dibuat akun atau password lokal.
- Kiriman lama yang menunggu Preseptor diteruskan ke Pembimbing Dalam tanpa menghapus isi maupun riwayat keputusan.
- Presensi tidak diubah oleh patch ini. Tindak lanjut presensi tanpa akun Preseptor memerlukan keputusan proses tersendiri.

## Implementasi

Default `PKPA_PRECEPTOR_DOCUMENT_VALIDATION_ENABLED=false`. Mode lama tetap tersedia melalui konfigurasi untuk kompatibilitas, bukan mode operasional yang dipilih pemilik proyek.

Antrean Pembimbing Dalam menyediakan pencarian mahasiswa/tempat, filter wahana dan tanggal untuk logbook, serta status portofolio. Antrean logbook langsung memprioritaskan kiriman siap validasi. Checklist per item, pilih seluruh halaman, jumlah pilihan, catatan bersama dan konfirmasi tersedia untuk logbook maupun portofolio. Portofolio dipaginasi 50 per halaman; endpoint membatasi maksimal 100 item.

Validasi massal memeriksa kewenangan dan status setiap item di server. Transaksi dan row lock menjaga seluruh batch tetap atomik. Item final tidak dapat divalidasi ulang; privasi dan kelengkapan portofolio tetap diperiksa. Riwayat keputusan dicatat per dokumen dengan identitas pemeriksa.

Nilai manual memakai komponen/bobot yang sudah ada, memeriksa batas nilai dan kesiapan akademik, mencatat sumber manual dan identitas koordinator, kemudian mengunci skor. Persiapan formulir massal dapat dilakukan pada setiap wahana yang memiliki skema aktif. Nilai lama yang sudah terkunci tidak ditimpa.

Migrasi hanya membuat referensi Core preseptor nullable pada catatan tempat, rancangan, publikasi, dan runtime. Nama tanpa akun tetap dipertahankan pada snapshot dan perubahan publikasi. Akun Core yang ada tidak dihapus atau diubah.

## Pemeriksaan Lokal

- Suite PKPA terkait (Tahap 03, 04, 05, 06, 07, 08, 14): 82 tes lulus / 1019 assertions sebelum penambahan tes penempatan tanpa akun.
- Pemeriksaan terakhir alur baru: 5 tes lulus / 39 assertions, mencakup penempatan tanpa akun, penolakan Preseptor, nilai manual, batch, penolakan validasi ulang, preview pemindahan, dan perlindungan keputusan final.
- `npm run build`: berhasil.
- `php artisan view:cache`: berhasil; template Blade tervalidasi.
- `php artisan migrate --no-interaction`: berhasil pada MySQL lokal, termasuk migrasi nullable yang baru. Tidak menggunakan migrate:fresh.
- `php artisan optimize:clear`: berhasil.
- Suite penuh: 398 tes / 2883 assertions; 372 lulus, 26 gagal, 0 errors. Log JUnit lokal: `storage/logs/pkpa-validation-regression-20261006.xml`. Kegagalan berada di IntegrationReviewScreenTest, modul Kp* legacy, dan StabilizationDemoSeederTest; mayoritas mengharapkan label/menu KP lama. Baseline sebelum patch tidak dijalankan ulang, jadi tidak diklaim sebagai suite hijau atau kegagalan historis yang telah dibuktikan.
- Belum dilakukan browser screenshot/UAT manusia atau pemeriksaan VPS pada patch ini.

## Penerapan VPS

Lakukan backup database sebelum migrasi. Sesudah commit tersedia di main:

```sh
cd /var/www/kppspa-farmasi
git pull --ff-only origin main
php artisan migrate --force
npm run build
php artisan optimize:clear
php artisan pkpa:enable-internal-validation
```

Periksa preview, lalu jalankan:

```sh
php artisan pkpa:enable-internal-validation --apply
php artisan pkpa:enable-internal-validation
```

Pastikan environment tidak mengaktifkan `PKPA_PRECEPTOR_DOCUMENT_VALIDATION_ENABLED=true`. Logbook submitted sudah masuk antrean Pembimbing Dalam melalui aturan baca baru; command hanya memindahkan status portofolio lama dan membuat audit. Command tidak menyetujui dokumen, tidak menghapus review, dan tidak mengubah presensi atau nilai.

Rollback aplikasi tidak boleh memaksa kolom nullable kembali non-null karena akan merusak referensi preseptor tanpa akun. Mode lama membutuhkan audit kiriman direct-internal sebelum dipakai kembali.

Status: persiapan lokal; deployment VPS belum dilakukan.
