# MY PSPA Competency Management Spec

Kompetensi PKPA dikonfigurasi per program-wahana melalui `pkpa_competency_sets`, `pkpa_competency_categories`, dan `pkpa_competency_items`.

## Prinsip

- Production seeder tidak membuat kompetensi resmi palsu.
- Satu set current aktif per program-wahana.
- Checklist runtime disalin sebagai snapshot ke `pkpa_rotation_competency_records`.
- Evidence disimpan privat di `pkpa_rotation_competency_evidences`.
- Pembimbing Lapangan memverifikasi; Pembimbing Dalam memberi monitoring.
- Tidak ada skor, bobot, nilai, passed, atau failed pada tahap ini.
