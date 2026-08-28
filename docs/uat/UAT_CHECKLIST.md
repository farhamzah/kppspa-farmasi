# UAT Checklist SI-KP Farmasi UBP

Gunakan checklist ini untuk UAT internal setelah menjalankan `php artisan db:seed --class=DemoEndToEndSeeder`.

| ID UAT | Role | Skenario | Langkah | Hasil yang Diharapkan | Status | Catatan |
|---|---|---|---|---|---|---|
| ADM-01 | Admin | Login admin | Login dengan `admin@sikp.test` / `password` | Admin masuk Dashboard Admin | Belum diuji |  |
| ADM-02 | Admin | Kelola user | Buka Manajemen User, lihat daftar, buka detail user | Data user tampil dan aksi admin tersedia | Belum diuji |  |
| ADM-03 | Admin | Import user | Buka Import User, unduh template, buka riwayat import | Halaman import dan riwayat tampil | Belum diuji |  |
| ADM-04 | Admin | Kelola periode | Buka Periode KP, buat/edit data demo jika perlu | Data periode dapat dikelola | Belum diuji |  |
| ADM-05 | Admin | Kelola tempat dan kuota | Buka Tempat KP dan Kuota Tempat KP | Tempat, kuota, sisa kuota, dan status tampil | Belum diuji |  |
| ADM-06 | Admin | Kelola persyaratan dokumen | Buka Persyaratan Dokumen | Dokumen KRS, Transkrip, Bukti Pembayaran, Surat Permohonan tampil | Belum diuji |  |
| ADM-07 | Admin | Verifikasi pendaftaran | Buka Verifikasi Pendaftaran | Pendaftaran mahasiswa demo tampil dengan status berkas | Belum diuji |  |
| ADM-08 | Admin | Monitoring pemilihan tempat | Buka Monitoring Pemilihan | Selection mahasiswa demo tampil | Belum diuji |  |
| ADM-09 | Admin | Monitoring penempatan | Buka Penempatan KP | Assignment mahasiswa demo tampil | Belum diuji |  |
| ADM-10 | Admin | Monitoring logbook | Buka Monitoring Logbook | Logbook disetujui/menunggu/revisi tampil | Belum diuji |  |
| ADM-11 | Admin | Monitoring laporan | Buka Monitoring Laporan | Laporan Alya disetujui dan Bima draft tampil | Belum diuji |  |
| ADM-12 | Admin | Monitoring sidang | Buka Pengajuan/Jadwal Sidang | Sidang Alya tampil selesai | Belum diuji |  |
| ADM-13 | Admin | Monitoring nilai | Buka Monitoring Nilai | Nilai Alya published dan Bima belum lengkap | Belum diuji |  |
| ADM-14 | Admin | Rekap dan export | Buka Rekap, export mahasiswa/nilai | File Excel terunduh | Belum diuji |  |
| KOR-01 | Koordinator KP | Login koordinator | Login `koordinator@sikp.test`, pilih Koordinator KP | Masuk Dashboard Koordinator tanpa 419 | Belum diuji |  |
| KOR-02 | Koordinator KP | Kelola periode | Buka Periode KP | Koordinator bisa melihat dan mengelola periode | Belum diuji |  |
| KOR-03 | Koordinator KP | Kelola kuota | Buka Kuota Tempat KP | Kuota dapat dibuka/tutup/diedit | Belum diuji |  |
| KOR-04 | Koordinator KP | Verifikasi pendaftaran | Buka Verifikasi Pendaftaran | Koordinator dapat melihat detail dan review | Belum diuji |  |
| KOR-05 | Koordinator KP | Monitoring war ticket | Buka Monitoring Pemilihan dan Daftar Tunggu | Data selection dan waiting list tampil | Belum diuji |  |
| KOR-06 | Koordinator KP | Buat assignment | Buka Penempatan KP | Selection aktif dapat dijadikan assignment | Belum diuji |  |
| KOR-07 | Koordinator KP | Tentukan pembimbing | Edit assignment, ubah pembimbing dalam/lapangan | Status dan log assignment berubah | Belum diuji |  |
| KOR-08 | Koordinator KP | Jadwalkan sidang | Buka Pengajuan Sidang lalu Jadwal Sidang | Pengajuan dapat dijadwalkan dengan penguji valid | Belum diuji |  |
| KOR-09 | Koordinator KP | Finalisasi nilai | Buka Monitoring Nilai | Nilai lengkap dapat dihitung/final/publish | Belum diuji |  |
| KOR-10 | Koordinator KP | Export rekap | Buka Rekap Nilai dan export | File Excel terunduh | Belum diuji |  |
| MHS-01 | Mahasiswa | Login mahasiswa | Login `mahasiswa@sikp.test` / `password` | Masuk Dashboard Mahasiswa | Belum diuji |  |
| MHS-02 | Mahasiswa | Lengkapi profil | Buka Profil Saya | Profil mahasiswa lengkap | Belum diuji |  |
| MHS-03 | Mahasiswa | Daftar KP | Buka Pendaftaran KP | Pendaftaran terverifikasi tampil | Belum diuji |  |
| MHS-04 | Mahasiswa | Upload Berkas KP | Buka Berkas KP | Dokumen dan status disetujui tampil | Belum diuji |  |
| MHS-05 | Mahasiswa | Pilih tempat KP | Buka Pemilihan Tempat | Tempat terpilih tampil dan tombol pilih terkunci | Belum diuji |  |
| MHS-06 | Mahasiswa | Lihat penempatan | Buka Penempatan KP | Tempat dan pembimbing tampil | Belum diuji |  |
| MHS-07 | Mahasiswa | Isi logbook | Buka Logbook KP | Logbook demo tampil | Belum diuji |  |
| MHS-08 | Mahasiswa | Upload laporan akhir | Buka Laporan Akhir | Laporan disetujui tampil untuk Alya | Belum diuji |  |
| MHS-09 | Mahasiswa | Ajukan sidang | Buka Sidang | Jadwal sidang tampil untuk Alya | Belum diuji |  |
| MHS-10 | Mahasiswa | Lihat nilai | Buka Nilai | Nilai final published tampil untuk Alya | Belum diuji |  |
| MHS-11 | Mahasiswa | Skenario berjalan | Login `mahasiswa2@sikp.test` dan buka Nilai | Nilai belum final/published | Belum diuji |  |
| PBD-01 | Pembimbing Dalam | Login dosen | Login `dosen@sikp.test` / `password` | Masuk Dashboard Pembimbing Dalam | Belum diuji |  |
| PBD-02 | Pembimbing Dalam | Lihat mahasiswa bimbingan | Buka Mahasiswa Bimbingan | Mahasiswa bimbingan tampil | Belum diuji |  |
| PBD-03 | Pembimbing Dalam | Pantau logbook | Buka Logbook Mahasiswa | Logbook bimbingan tampil | Belum diuji |  |
| PBD-04 | Pembimbing Dalam | Review laporan | Buka Review Laporan | Laporan mahasiswa bimbingan tampil | Belum diuji |  |
| PBD-05 | Pembimbing Dalam | Lihat jadwal sidang | Buka Jadwal Sidang | Jadwal sidang mahasiswa bimbingan tampil | Belum diuji |  |
| PBD-06 | Pembimbing Dalam | Input nilai pembimbing | Buka Penilaian Pembimbing | Komponen pembimbing dalam tampil | Belum diuji |  |
| PBL-01 | Preseptor | Login preseptor | Login `lapangan@sikp.test` / `password` | Masuk Dashboard Preseptor | Belum diuji |  |
| PBL-02 | Preseptor | Lihat mahasiswa PKPA | Buka Mahasiswa PKPA | Mahasiswa bimbingan preseptor tampil | Belum diuji |  |
| PBL-03 | Preseptor | Validasi logbook | Buka Validasi Logbook | Logbook mahasiswa bimbingan preseptor tampil | Belum diuji |  |
| PBL-04 | Preseptor | Input nilai preseptor | Buka Penilaian Preseptor | Komponen penilaian preseptor tampil | Belum diuji |  |
| PGJ-01 | Penguji | Login penguji | Login `penguji@sikp.test` / `password` | Masuk Dashboard Penguji | Belum diuji |  |
| PGJ-02 | Penguji | Lihat jadwal sidang | Buka Jadwal Sidang | Sidang yang ditugaskan tampil | Belum diuji |  |
| PGJ-03 | Penguji | Input nilai sidang | Buka Penilaian Sidang | Komponen penguji tampil | Belum diuji |  |

