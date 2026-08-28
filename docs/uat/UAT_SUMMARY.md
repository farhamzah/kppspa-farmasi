# UAT Summary - SI-KP Farmasi UBP

## Tujuan UAT
Memastikan MVP SI-KP Farmasi UBP dapat digunakan untuk demo internal dan validasi alur kerja praktek dari pendaftaran sampai nilai akhir.

## Role yang Diuji
- Admin
- Koordinator KP
- Mahasiswa
- Pembimbing Dalam
- Preseptor
- Penguji

## Alur yang Diuji
- Login dan pilih role.
- Manajemen user dan profil.
- Pendaftaran KP dan upload berkas.
- Verifikasi berkas.
- Pemilihan tempat KP.
- Penempatan dan pembimbing.
- Logbook KP.
- Laporan akhir.
- Sidang KP.
- Penilaian dan nilai akhir.
- Rekap dan export.

## Hasil Sementara
MVP siap untuk UAT/demo internal. Hasil final UAT perlu dicatat menggunakan `docs/uat/UAT_ISSUES_TEMPLATE.md`.

## Cara Mencatat Bug
1. Beri ID unik seperti `UAT-001`.
2. Tulis role dan halaman.
3. Jelaskan langkah reproduksi.
4. Tentukan severity.
5. Update status sampai `done` atau `rejected`.

## Kriteria Aplikasi Layak Dipakai
- Alur login dan role berjalan.
- Data setiap role tidak bocor ke role lain.
- Upload/download file berjalan sesuai hak akses.
- Alur KP utama dapat diselesaikan.
- Tidak ada blocker pada dashboard, pendaftaran, verifikasi, pemilihan, assignment, logbook, laporan, sidang, nilai, dan rekap.

