# MY PKPA Placement Publication Spec

Tahap 05 mengubah rancangan penempatan PKPA yang sudah terkunci menjadi jadwal resmi berbasis snapshot.

## Prinsip

- Sumber publikasi adalah `pkpa_placement_plans` yang sudah `locked`, tervalidasi, dan tidak memiliki error aktif.
- Portal mahasiswa dan pembimbing tidak membaca `pkpa_rotation_assignments`; mereka membaca `pkpa_published_assignments`.
- Publication lama tidak diedit saat ada revisi. Revisi membuat publication baru dan publication lama menjadi `superseded`.
- Withdrawal mencabut publication current, tetapi snapshot tetap tersimpan untuk audit.

## Tabel Utama

- `pkpa_placement_publications`
- `pkpa_published_assignments`
- `pkpa_published_assignment_supervisors`
- `pkpa_schedule_acknowledgements`
- `pkpa_placement_change_requests`
- `pkpa_placement_change_request_items`
- `pkpa_notification_deliveries`

## Alur Publish

1. Admin/koordinator menyelesaikan penyusunan penempatan Tahap 04.
2. Sistem menjalankan final review.
3. Koordinator PKPA mengunci plan untuk publikasi.
4. Koordinator mengetik kode program sebagai konfirmasi publish.
5. Sistem membuat publication, assignment snapshot, supervisor snapshot, audit, dan notification delivery.
6. Portal mahasiswa/PD/PL mulai menampilkan jadwal current.

## Batasan

Tahap 05 tidak membuat logbook rotasi, presensi, nilai, surat, WhatsApp, atau migrasi tabel legacy `kp_*`.
