# Revisi Portofolio Puskesmas November 2026

## Sumber

Dokumen pengguna disimpan tanpa modifikasi di
`docs/referensi-portofolio/PORTOFOLIO_PUSKESMAS_REVISI_2026-11-01.docx`.
SHA256: `d4c29b36e9841d71259475e3a2b770e4577d47f2b21ecbf54fd6b0325867f721`.
Dokumen referensi adalah sumber isi, bukan instruksi operasional untuk aplikasi.
JSON turunan dapat direproduksi menggunakan `scripts/extract_puskesmas_portfolio_reference.py`.

## Perubahan

- Template `PORT-PKM-v2` khusus Puskesmas dalam program yang dipilih; v1 tetap tersedia.
- Tujuh aspek: regulasi/organisasi; perbekalan farmasi; pelayanan farmasi klinik;
  SDM; mutu; keselamatan pasien; pencegahan/pengendalian infeksi.
- Form tabel berulang, penyimpanan draf, jumlah minimum per tugas, dan N/A dengan alasan.
- Penilaian diri: 20 kompetensi berskala 1-4, bukti, alasan N/A, refleksi diri dan
  rencana perbaikan. Nilai dihitung server-side, N/A tidak masuk penyebut.
- V2 tidak mewajibkan lagi formulir studi kasus generik, refleksi mingguan terpisah,
  atau self-assessment 1-5; tugas klinik dan refleksi terdapat dalam aspek baru.
- Ekspor Word/PDF mengikuti aspek baru dan menyertakan instrumen manual:
  22 aspek preseptor, 13 aspek dosen, rubrik, rekap 60%-40%, dan umpan balik.
- Instrumen dalam sumber berstatus rancangan pelengkap untuk ditetapkan Prodi.
  **Skema dan hasil nilai di aplikasi tidak diganti/digabung otomatis.**
- Identitas pasien pada contoh sumber diadaptasi menjadi kode anonim; nama,
  alamat, telepon, atau nomor rekam medis pasien tidak diminta.
- Word menggunakan tabel A4, header tabel berulang, baris pendek tidak terbelah,
  dan tabel lebar dipecah menjadi aspek/isian. Sel panjang tetap dapat mengalir
  antarhalaman. Seluruh logbook v2 ikut diekspor, tidak dibatasi sepuluh entri.
- Penandatanganan hardcopy dan unggah scan PDF tetap memakai alur yang ada;
  tidak mengaktifkan kembali validasi online preseptor.

## Penerapan VPS

Cadangkan database dan storage sesuai prosedur operasional sebelum perubahan.

```bash
cd /var/www/kppspa-farmasi
git pull --ff-only origin main
npm run build
php artisan optimize:clear
php artisan pkpa:setup-puskesmas-portfolio-revision --program=PKPA-2026-G1 --upgrade-drafts
```

Periksa jumlah draf pada preview. Terapkan dengan:

```bash
php artisan pkpa:setup-puskesmas-portfolio-revision --program=PKPA-2026-G1 --upgrade-drafts --apply
php artisan pkpa:setup-puskesmas-portfolio-revision --program=PKPA-2026-G1 --upgrade-drafts
```

`--apply` tanpa `--upgrade-drafts` hanya memasang template untuk portofolio baru.
Perintah bersifat idempoten, transaksional dan memeriksa checksum sumber.
Hanya draf/in-progress yang belum pernah dikirim, belum direview, belum
dipublikasikan, dan tidak memiliki scan bertanda tangan yang boleh dipindahkan.
Portofolio terkirim/disetujui/terkunci tetap memakai versi semula, termasuk saat
halaman dibuka. Isian manual lama tidak dihapus; masuk arsip keluaran Word/PDF.
Nilai, presensi, logbook, ekspor lama dan tanda tangan tidak ditimpa.
Persetujuan pakta integritas yang sudah ada tetap dipertahankan.

Jika portofolio lama yang sudah disahkan perlu beralih format, lakukan keputusan
akademik dan pembukaan ulang terkontrol terlebih dahulu; jangan mengedit status
langsung di database. Perintah ini sengaja tidak melewati proteksi tersebut.

## Verifikasi

Pengujian meliputi checksum sumber, jumlah instrumen, preview tanpa perubahan,
idempotensi, batas program/wahana, perlindungan portofolio terkirim, arsip isian,
nilai lama, validasi tabel, N/A, skor 1-4, pemalsuan nilai, formulir mahasiswa,
ringkasan pembimbing, dan ekspor Word.
Build Vite berhasil. QA Playwright pada 1440px dan 390px memeriksa tambah/hapus
entri dan tidak adanya luapan horizontal. Contoh DOCX dibuka dengan Microsoft Word dan dirender ke PDF
untuk pemeriksaan tata letak; tidak ada perubahan pada dokumen pengguna.

Penerapan dan pemeriksaan data VPS dilakukan pengelola menggunakan perintah
di atas. Tidak ada akses langsung VPS dalam pengerjaan ini.
