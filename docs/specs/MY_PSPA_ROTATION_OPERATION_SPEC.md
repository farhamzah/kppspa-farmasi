# MY PSPA Rotation Operation Spec

## Tujuan

Operasional rotasi PKPA dimulai setelah jadwal resmi dipublikasikan. Modul ini membuat runtime rotasi dari snapshot published assignment, mengelola aktivasi/status, menjaga histori pembimbing, dan menyediakan monitoring operasional tanpa menyentuh nilai akhir.

## Entitas Utama

- `pkpa_rotation_operation_rules`: aturan per program-wahana.
- `pkpa_rotation_runs`: runtime operasional per requirement published.
- `pkpa_rotation_status_histories`: histori perubahan status.
- `pkpa_rotation_supervisor_histories`: histori Pembimbing Lapangan/Dalam aktif.
- `pkpa_rotation_publication_sync_logs`: jejak sinkronisasi perubahan publikasi.

## Aturan

- Runtime hanya dibuat dari `pkpa_placement_publications` dengan `status=published` dan `is_current=true`.
- Aktivasi hanya oleh Koordinator PKPA dan memerlukan rule aktif pada program-wahana.
- Status operasional tidak menggantikan status akademik requirement.
- Perubahan publikasi pada rotasi berjalan yang mengubah tempat/tanggal ditandai `review_required`.
- Tabel legacy `kp_*` tidak ditulis.
- Tahap akademik berikutnya memakai `pkpa_rotation_runs` sebagai parent untuk kompetensi, tugas khusus, laporan rotasi, bimbingan, dan readiness. Operational complete tetap hanya status operasional, bukan penilaian.
