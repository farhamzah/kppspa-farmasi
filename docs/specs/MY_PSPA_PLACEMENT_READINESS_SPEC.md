# MY PSPA Placement Readiness Spec

## Tujuan

Readiness penempatan memastikan fondasi sebelum Koordinator PKPA menyusun penempatan mahasiswa. Checklist ini bersifat pre-placement dan tidak membuat assignment.

## Input Checklist

- Program PKPA dan enam wahana aktif.
- Peserta aktif/on-hold pada program.
- Kelompok aktif dan peserta belum berkelompok.
- Tempat program aktif/ready.
- Availability period aktif (`available` atau `full`).
- Kapasitas terencana.
- Pembimbing Dalam eligible per program-wahana.
- Pembimbing Lapangan aktif pada tempat praktik.
- Option Pemerintahan bila wahana Pemerintahan dipakai.
- Status sinkronisasi pembimbing dari Core.

## Status

- `Belum siap`: terdapat isu kritis.
- `Perlu perhatian`: tidak ada isu kritis, tetapi ada warning.
- `Siap menyusun penempatan`: tidak ada isu kritis maupun warning.

## Isu Kritis

Contoh isu kritis:

- belum ada tempat aktif pada wahana;
- belum ada availability period;
- kapasitas terencana nol;
- kapasitas kurang dari jumlah peserta;
- belum ada Pembimbing Dalam eligible;
- tempat belum memiliki Pembimbing Lapangan aktif;
- Pemerintahan tidak memiliki option Loka POM atau Dinas Kesehatan.

## Warning

Contoh warning:

- pembimbing perlu sync ulang;
- peserta aktif belum berkelompok.

## Batasan

Readiness tidak membuat penempatan, matrix, jadwal rotasi, tanggal rotasi, logbook, nilai, atau publikasi hasil penempatan.

## Hubungan dengan Tahap 04

Readiness menjadi prasyarat operasional sebelum Koordinator memakai Penyusunan Penempatan. Tahap 04 memakai data yang diperiksa readiness:

- tempat program aktif;
- availability dan kapasitas;
- Pembimbing Dalam eligible;
- Pembimbing Lapangan aktif;
- option Pemerintahan;
- peserta dan kelompok.

Jika readiness belum siap, planner tetap tidak mengubah data secara otomatis. Validasi assignment dan validasi seluruh rancangan mengulang pemeriksaan backend agar kapasitas/pembimbing tidak hanya bergantung pada checklist.
