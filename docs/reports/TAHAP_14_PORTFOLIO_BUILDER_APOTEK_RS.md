# TAHAP 14 - Pembuat Portofolio Digital PKPA Apotek dan Rumah Sakit

## Status Akhir

Tahap 14 lulus dengan catatan. Fitur inti, alur kerja, penjaga privasi, otorisasi, kelengkapan, unduhan DOCX/PDF, QA peramban, PHPUnit, build, audit, dan kesehatan antrean sudah lulus. Catatan: tata letak unduhan DOCX/PDF masih acuan internal awal dan perlu dipoles jika format dokumen resmi kampus sudah ditentukan.

Tidak ada commit, push, tag, rilis, atau deployment pada tahap ini. `apps/core-farmasi` tidak diubah.

## Ringkasan Implementasi

Tahap 14 menambahkan Pembuat Portofolio generik untuk MY PKPA. Modul membuat portofolio digital per mahasiswa per wahana dari `pkpa_rotation_runs`, dengan pola awal:

- `PORT-APT-v1` untuk Wahana Apotek;
- `PORT-RS-v1` untuk Wahana Rumah Sakit.

Layanan utama adalah `PkpaPortfolioBuilderService`.

## Database

Migrasi baru: `2026_07_19_140001_create_pkpa_portfolio_builder_tables.php`.

Tabel baru:

- `pkpa_portfolio_templates`
- `pkpa_portfolio_template_sections`
- `pkpa_rotation_portfolios`
- `pkpa_portfolio_section_records`
- `pkpa_portfolio_case_reports`
- `pkpa_portfolio_weekly_reflections`
- `pkpa_portfolio_self_assessments`
- `pkpa_portfolio_documentation_items`
- `pkpa_portfolio_reviews`
- `pkpa_portfolio_publications`
- `pkpa_portfolio_export_versions`

`php artisan migrate --seed --force` final: sukses, tidak ada migrasi tertinggal, seeder idempoten. Migrasi Tahap 14 tercatat `Ran` batch 25.

## Pemetaan Data

Portofolio tidak menggandakan data operasional. Bagian otomatis menyimpan referensi ke data yang sudah ada:

- identitas dari enrollment;
- penempatan dari rotasi berjalan dan penugasan terbit;
- PL/PD dari riwayat pembimbing/penugasan aktif;
- presensi dari attendance;
- Logbook dari logbook PKPA;
- kompetensi dari competency record;
- tugas/laporan/nilai dari modul akademik dan penilaian yang sudah ada.

Snapshot hanya dipakai untuk identitas, penempatan, kemajuan, penerbitan, dan versi unduhan.

## Template Apotek

Template Apotek memuat sampul, identitas, tempat/periode/PL/PD, lembar pengesahan, visi/misi/tata tertib, pakta integritas, tujuan, kompetensi Apotek, Logbook, presensi, kompetensi, pengelolaan sediaan farmasi, pelayanan resep, swamedikasi, konseling, PIO, narkotika/psikotropika, administrasi, studi kasus, refleksi mingguan, Penilaian Diri, penilaian PL/PD, rubrik, dokumentasi, lampiran, kemajuan, pemeriksaan, penerbitan, dan unduhan DOCX/PDF.

## Template Rumah Sakit

Template Rumah Sakit memuat sampul Wahana Rumah Sakit, kompetensi Rumah Sakit, gudang farmasi, rawat jalan, rawat inap, farmasi klinik, PIO, konseling, rekonsiliasi obat, ADR/MESO, visite ruang rawat, sediaan steril, studi kasus, refleksi, Penilaian Diri, penilaian PL/PD, rubrik, dokumentasi, lampiran, pemeriksaan, penerbitan, dan unduhan DOCX/PDF.

Pengujian regresi membaca isi DOCX Rumah Sakit dan memastikan label `Rumah Sakit` ada serta label `Apotek` tidak bocor pada unduhan RS.

## Privacy

Studi kasus menolak identitas langsung pasien seperti nama pasien, nomor rekam medis, NIK/KTP/KK/BPJS, alamat, nomor telepon, dan pola identifier pasien yang jelas. Mahasiswa wajib konfirmasi anonimisasi. Dokumentasi kegiatan wajib konfirmasi anonimisasi dan izin/consent, lalu disimpan pada private disk.

Catatan penting: penjaga privasi adalah pemeriksaan awal berbasis pola teks, bukan deteksi sempurna. Pemeriksaan manusia oleh PL/PD tetap wajib, dan PL dapat meminta revisi karena temuan privasi.

## Alur Kerja

- Mahasiswa: portofolio dibuat otomatis dari rotasi berjalan, idempoten, isi manual, pakta integritas, kirim.
- PL: hanya melihat mahasiswa bimbingannya, pemeriksaan lapangan, minta revisi/verifikasi, validasi privasi.
- PD: hanya melihat mahasiswa bimbingannya, pemeriksaan akademik, minta revisi/setujui.
- Koordinator/Admin: pantau, buka ulang wajib alasan, terbitkan, kunci, unduh.

Status perubahan tercatat pada `pkpa_portfolio_reviews`, waktu/status portofolio, serta versi penerbitan/unduhan.

## Kelengkapan

Kelengkapan tidak berbasis jumlah bagian saja. Syarat kirim mengecek pakta integritas, Logbook, presensi, kompetensi wajib, studi kasus minimum, refleksi minimum, Penilaian Diri, dokumentasi/bukti, dan revisi terbuka. Tugas/laporan/nilai ditautkan dari modul yang sudah ada dan tidak diduplikasi.

## Unduhan

DOCX dibuat sebagai dokumen Office yang valid dan diuji dengan `ZipArchive`. PDF dibuat melalui `SimplePdfReport` dan diuji header `%PDF`. Berkas tersimpan privat. Unduhan versi terbit dikaitkan ke penerbitan dan tidak ditimpa saat unduhan diulang.

## Playwright QA

Eksekusi pertama:

- 11 dari 16 pengujian lulus;
- tidak ada pengujian gagal saat dihentikan;
- shell berhenti karena batas waktu setelah 5 menit;
- hasil tidak dianggap final.

Eksekusi ulang awal:

- terminal 15 lulus, 1 gagal;
- kegagalan: `net::ERR_NO_BUFFER_SPACE` pada Chrome desktop-wide;
- penyebab: resource/socket lokal Windows dan dua child `php -S` server port 3006 tertinggal;
- pengujian terkait direproduksi ulang setelah pembersihan dan lulus 1 pengujian.

Eksekusi final:

- command: `npx.cmd playwright test --reporter=line`;
- hasil: 16 lulus, 0 gagal, 0 dilewati;
- durasi: 3.9 menit;
- viewport: desktop 1366x768, desktop-wide 1920x1080, tablet 768x1024, mobile 390x844.

## QA Sisi Server/Build Final

- `php artisan optimize:clear`: lulus.
- `php artisan migrate --seed --force`: lulus.
- `php artisan test --stop-on-failure`: 303 lulus, 2097 asersi, 0 gagal.
- `npm.cmd run build`: lulus.
- `php artisan route:list --name=pkpa`: route portofolio mahasiswa, PL, PD, management terdaftar.
- `php artisan pkpa:integrity-audit --json`: status lulus, `issue_count=0`, `critical_open=0`, `high_open=0`.
- `php artisan pkpa:document-orphan-audit --json`: `missing_count=0`.
- `php artisan pkpa:queue-health --json`: `failed_jobs=0`, dokumen gagal `0`.

## File Berubah

- migrasi pembuat portofolio;
- model pembuat portofolio;
- `PkpaPortfolioBuilderService`;
- controller mahasiswa, PL, PD, management;
- route web;
- Blade portfolio;
- `PkpaPortfolioTemplateSeeder`;
- `DatabaseSeeder`;
- `PkpaDemoEndToEndSeeder`;
- `Tahap14PkpaPortfolioBuilderTest`;
- dokumen spesifikasi dan laporan.

## Belum Dikerjakan

Template Puskesmas, PBF, Industri, dan Pemerintahan sengaja belum dibuat sesuai batas lingkup. Tahap berikutnya yang disarankan setelah persetujuan Project Manager: Tahap 15 - Pembuat Portofolio Wahana Puskesmas, PBF, Industri, dan Pemerintahan.
