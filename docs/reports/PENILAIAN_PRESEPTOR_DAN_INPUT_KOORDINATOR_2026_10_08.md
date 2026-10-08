# Penilaian Preseptor dan Input Koordinator

## Sumber dan batasan

Rubrik preseptor memang tersedia di `PANDUAN PKPA 2026 NEW.pdf`:

| Wahana | Lampiran | Halaman | Bobot bagian |
| --- | --- | --- | --- |
| Apotek | 8 | 51-53 | 30, 40, 20, 10 |
| Rumah Sakit | 11 | 60-62 | 40, 40, 20 |
| Puskesmas | 14 | 68-70 | 40, 20, 20, 20 |
| Dinas Kesehatan | 17 | 76-78 | 30, 40, 30 |
| Loka POM | 20 | 85-88 | 25, 25, 30, 20 |
| PBF | 22 | 94-96 | 25, 40, 15, 10, 10 |
| Industri Farmasi | 24 | 101-103 | 40, 40, 20 |

Skor butir 1-5, nilai butir = bobot x skor / 5, total maksimal 100.
PBF menggunakan CDOB, distribusi, cold chain, persediaan dan dokumentasi, bukan rubrik resep Apotek.
Pemerintahan memilih rubrik berdasarkan option runtime/tempat, bukan berdasarkan nama tempat yang ditebak.
Puskesmas mendukung domain PKM/PUSKESMAS dan option PUSKESMAS pada data legacy Pemerintahan.

Form Pembimbing Dalam Apotek yang sudah ada tidak diubah butir/bobotnya. Form pembimbing wahana lain tetap mengikuti komponen yang sudah dikonfigurasi; tidak dibuat rubrik pembimbing baru tanpa sumber.
Panduan belum menetapkan proporsi penggabungan preseptor, pembimbing dan ujian. Jangan mengasumsikan 50:50.

## Perubahan

- Form preseptor semua wahana menggunakan butir dan bobot panduan, total otomatis, umpan balik dan tiga rekomendasi.
- Preseptor dengan akun dan penugasan yang sah dapat mengisi nilai, meskipun validasi dokumen oleh preseptor nonaktif.
- Koordinator/Admin dapat mencatat nilai preseptor maupun pembimbing tanpa mengganti identitas penilai.
- Nama/ID penilai, ID penginput, waktu dan dasar pencatatan disimpan pada sumber nilai serta audit.
- Preseptor tanpa akun tetap bisa dinilai melalui koordinator menggunakan penugasan dengan nama snapshot dan Core ID kosong. Tidak membuat akun baru.
- Pencatatan koordinator mempunyai halaman tersendiri, draf dan kirim/kunci. Dasar pencatatan wajib.
- Pengiriman tidak lengkap rollback seluruh perubahan. Nilai terkirim/terkunci atau penilai yang diganti tidak boleh ditimpa.
- Nilai historis yang hanya mempunyai total ditampilkan sebagai riwayat terkunci, tanpa form rubrik kosong. Perhitungan browser tidak menghitung ulang nilai terkunci.
- Readiness akademik tetap berlaku. Tidak mengubah alur presensi, logbook, portofolio atau validasinya.
- Default panduan baru menyimpan nilai penilai terpisah, bobot penggabungan nol sebagai belum ditetapkan. Aktivasi hanya menerima dua komponen penilai pada mode ini.
- Default Apotek lama dikenali dari kode `APT-PANDUAN-2026`; finalisasi/moderasi gabungan baru diblokir. Nilai historis tidak ditulis ulang atau dihapus. Skema kustom eksplisit tetap dipertahankan.
- Perintah setup baru scoped program, preview default, apply eksplisit, transaksional dan idempotent.

Engine rubrik memakai service Apotek yang sudah ada agar jalur izin, readiness dan penguncian tetap konsisten. Provider `PkpaPreceptorAssessment` memilih definisi per wahana. Nama service/form lama dipertahankan untuk kompatibilitas.

## Pemasangan VPS

```bash
cd /var/www/kppspa-farmasi
git pull --ff-only origin main
php artisan optimize:clear
php artisan pkpa:setup-preceptor-assessment --program=PKPA-2026-G1
```

Periksa preview dahulu. Setelah sesuai:

```bash
php artisan pkpa:setup-preceptor-assessment --program=PKPA-2026-G1 --apply
```

Tidak membutuhkan migrasi database. Gunakan aset build mengikuti prosedur deploy Vite yang biasa dipakai server.
Setup mempertahankan skema aktif dan panel/nilai existing; tidak menambah komponen pada skema aktif yang historis. Bila skema kustom belum mempunyai komponen preseptor, konfigurasi versi skema berikutnya secara terpisah, jangan mengedit histori.

## Pemakaian

1. Koordinator membuka Penilaian PKPA.
2. Pada mahasiswa yang dituju pilih Isi Nilai Preseptor atau Isi Nilai Pembimbing.
3. Pastikan wahana/tempat dan nama penilai sesuai lembar hardcopy.
4. Isi skor, catatan/rekomendasi dan dasar pencatatan; Simpan Draf bila belum selesai.
5. Kirim & Kunci ketika seluruh butir lengkap. Nilai preseptor dan pembimbing tetap terpisah.

## Verifikasi

- 24 pengujian penilaian terarah lulus: rubrik seluruh wahana, akses preseptor tanpa validasi dokumen, input koordinator, akses mahasiswa ditolak, readiness, penilai diganti, penguncian, preservasi total historis, rollback pengiriman tidak lengkap, audit, setup preview/idempotensi dan larangan penggabungan default.
- Build Vite dan cache Blade berhasil.
- Playwright/Edge pada 1440x1000 dan 390x844: 20 butir PBF, semua skor 4 menghasilkan 80; CDOB diubah ke 5 menghasilkan 82; rekomendasi dan dasar pencatatan dapat diisi; tidak ada overflow horizontal. Screenshot diperiksa.
- QA visual menggunakan respons Blade fixture dengan CSS build, bukan login VPS. Logo Core tidak dimuat pada fixture offline.
- Suite penuh sebelum tambahan pengujian total historis: 412 tests, 3066 assertions, 27 failures, 0 errors; seluruh pengujian penilaian lulus. Sebanyak 26 failure cocok dengan baseline 6 Oktober. Satu lainnya adalah ekspektasi teks pilihan role pada `UserImportAndProfileTest` yang tidak cocok dengan deskripsi Pembimbing Dalam yang sudah berubah pada kode HEAD; file tersebut tidak diubah dalam pekerjaan ini. Setelah tambahan penjagaan total historis, suite penilaian terarah dijalankan kembali. Kegagalan legacy/ekspektasi lama tidak dibetulkan dalam perubahan ini.
- Tidak mengakses atau mengubah database VPS pada pengerjaan lokal ini.
