# PROMPT TAHAP 06 - Penentuan Pembimbing dan Penempatan KP

Tahap 6 membangun modul Penempatan KP resmi dan penentuan pembimbing pada aplikasi SI-KP Farmasi UBP tanpa mengulang Tahap 1 sampai Tahap 5.

Tujuan utama:
- Membuat assignment/penempatan KP dari pilihan tempat aktif.
- Menentukan satu Pembimbing Dalam/Dosen dan satu Preseptor.
- Mahasiswa melihat detail penempatannya sendiri.
- Pembimbing Dalam melihat mahasiswa bimbingannya.
- Preseptor melihat mahasiswa KP yang ditugaskan.
- Admin/Koordinator memonitor penempatan dan log perubahan.

Batasan:
- Tidak membuat logbook detail, validasi logbook, laporan akhir, sidang, atau penilaian.
- Assignment menjadi fondasi untuk Tahap 7 Logbook KP.

Database yang dibuat:
- `kp_assignments`
- `kp_assignment_logs`
- `kp_place_field_supervisors`

Role akses:
- Mahasiswa: lihat penempatan sendiri.
- Admin/Koordinator KP: kelola assignment, pembimbing, cancel, log.
- Pembimbing Dalam: lihat mahasiswa bimbingannya.
- Preseptor: lihat mahasiswa KP yang ditugaskan.

