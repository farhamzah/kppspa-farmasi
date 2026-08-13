# Spesifikasi Privasi Portofolio MY PSPA

## Prinsip

Portofolio PKPA menyimpan berkas dan unduhan pada penyimpanan privat. Tidak ada berkas portofolio yang ditempatkan pada disk publik.

## Studi Kasus

Studi kasus menolak identitas langsung pasien:

- nama pasien;
- nomor rekam medis;
- NIK/KTP/KK/BPJS;
- alamat pasien;
- nomor kontak pasien.

Validasi berada pada `PkpaPortfolioBuilderService::patientPrivacyWarnings()`. Mahasiswa wajib mencentang konfirmasi anonimisasi. PL dapat meminta revisi jika masih ada temuan privasi.

Validasi ini adalah penjaga awal berbasis pola teks, bukan deteksi sempurna. Pemeriksaan manual oleh Pembimbing Lapangan dan Pembimbing Dalam tetap wajib sebelum disetujui/diterbitkan.

## Dokumentasi

Dokumentasi kegiatan wajib menyimpan konfirmasi:

- anonimisasi;
- izin/consent.

File foto/PDF disimpan di disk `local` pada path `pkpa-portfolios/{portfolio}/documentation`.

## Pakta Integritas

Pakta integritas menyimpan teks berversi, waktu persetujuan, Core user ID penyetuju, dan versi template/publication. Persetujuan elektronik ini bukan tanda tangan digital tersertifikasi.
