# MY PSPA UAT Scenarios

Skenario:
- Program: buat program, enam wahana, tempat, kapasitas, pembimbing.
- Peserta: tambah peserta, import, kelompok.
- Penempatan: draft, bulk, validasi, publish, revision.
- Operasional: runtime, presensi, logbook, review.
- Akademik: kompetensi, tugas, laporan, bimbingan.
- Penilaian: PL, PD, moderasi, finalisasi, release.
- Penyelesaian: completion, remedial, final grade, graduation, release.
- Dokumen: generate, approve, publish, download, cancel.
- Security: direct URL, IDOR, unauthorized file, role mismatch, Core unavailable, expired session, invalid upload.

Hasil UAT harus mencatat pass/fail, bukti, blocker, dan owner tindak lanjut.
# Catatan Baseline Tahap 11A

Skenario human UAT menggunakan hasil AI-Assisted Pre-UAT sebagai baseline awal. Skenario yang sudah lolos otomatis tetap perlu dicek pengguna dari sisi SOP, istilah akademik, dan kenyamanan alur.

## Prioritas Tahap 11B

Urutan UAT disarankan:
1. Setup program dan master wahana.
2. Import/sinkron peserta.
3. Readiness dan placement planner.
4. Publikasi dan acknowledgement.
5. Operasional rotasi, presensi, logbook.
6. Kompetensi, tugas, laporan, bimbingan.
7. Penilaian wahana, nilai akhir, remedial, kelulusan.
8. Dokumen, download, analytics, dan notifikasi.
