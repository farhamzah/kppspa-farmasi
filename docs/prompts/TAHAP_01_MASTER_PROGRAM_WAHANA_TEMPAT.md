# Prompt Tahap 01

Bangun fondasi master data MY PSPA di `apps/kppspa-farmasi` tanpa mengubah `apps/core-farmasi`.

Scope:

- Program PKPA
- Enam wahana default
- Pilihan Pemerintahan Loka POM dan Dinas Kesehatan
- Konfigurasi durasi per program-wahana
- Tempat praktik
- Authorization Admin/Koordinator PKPA
- Audit trail master data
- UI Program PKPA, Wahana PKPA, Tempat Praktik
- Test, browser QA, build, dan dokumentasi

Larangan:

- Jangan membuat penempatan mahasiswa, kelompok rotasi, kapasitas per tanggal, pembimbing, logbook rotasi, laporan rotasi, nilai rotasi, migrasi data lama, atau algoritma penjadwalan.
- Jangan mengubah Core Farmasi.
- Jangan menghapus tabel `kp_*`.
- Jangan menghidupkan kembali fitur war.
- Jangan membuat akun, password, atau role lokal.
