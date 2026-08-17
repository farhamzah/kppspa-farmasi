# MY PKPA Domain Migration Map

## Target Domain

```text
Program PKPA
└── Kepesertaan Mahasiswa
    ├── Apotek
    ├── Puskesmas
    ├── PBF
    ├── Rumah Sakit
    ├── Industri
    └── Pemerintahan
        └── Loka POM atau Dinas Kesehatan
```

## Tabel Target yang Direkomendasikan

- `pkpa_programs`
- `pkpa_practice_domains`
- `pkpa_practice_domain_options`
- `pkpa_program_domains`
- `pkpa_practice_sites`
- `pkpa_site_capacities`
- `pkpa_enrollments`
- `pkpa_student_groups`
- `pkpa_rotation_assignments`
- `pkpa_assignment_supervisors`
- `pkpa_assignment_supervisor_histories`
- `pkpa_assignment_change_logs`
- `pkpa_schedule_exceptions`

## Relasi User

Gunakan referensi Core:

- `student_core_user_id`
- `internal_supervisor_core_user_id`
- `field_supervisor_core_user_id`
- `examiner_core_user_id`
- `created_by_core_user_id`
- `updated_by_core_user_id`

Local numeric surrogate key boleh dipakai untuk performa dan relasi Laravel, tetapi tidak boleh mengganti Core ID sebagai referensi identitas utama.

## Evaluasi Legacy

Tabel KP legacy dipertahankan pada Tahap 00 karena masih menjadi foreign key fitur lama. Fitur war tidak dihapus, tetapi route aktif dikunci feature flag. Migrasi destruktif belum dilakukan.

## Tahap 01: Struktur Paralel

Tahap 01 membuat struktur master PKPA baru tanpa menghapus atau memigrasikan tabel lama.

Mapping awal yang terdokumentasi:

- `kp_periods` -> `pkpa_programs`
- `kp_places` -> `pkpa_practice_sites`
- `kp_places.type` -> `pkpa_practice_domains`

Mapping tersebut belum dieksekusi. Migrasi data lama membutuhkan tahap khusus yang berisi audit data, mapping final, approval, dry run, rollback plan, dan validasi lintas modul. Sampai tahap itu disetujui, fitur legacy tetap membaca tabel `kp_*`, sedangkan modul master baru membaca tabel `pkpa_*`.

Tabel lama yang tidak disentuh Tahap 01:

- `kp_periods`
- `kp_places`
- `kp_place_quotas`
- `kp_place_selections`
- `kp_waiting_lists`
- `kp_assignments`

## Tahap 02: Kepesertaan Paralel

Tahap 02 membuat kepesertaan PKPA baru tanpa memigrasikan peserta legacy.

Mapping konseptual:

- referensi user/student legacy -> `pkpa_enrollments.core_user_id`
- data mahasiswa untuk laporan -> snapshot pada `pkpa_enrollments`
- kebutuhan enam wahana -> `pkpa_enrollment_requirements`
- kelompok persiapan penempatan -> `pkpa_student_groups` dan `pkpa_student_group_members`
- import peserta -> `pkpa_enrollment_import_batches` dan `pkpa_enrollment_import_rows`

Mapping yang belum dieksekusi:

- `kp_assignments` -> belum dimigrasikan pada Tahap 02
- `kp_place_selections` dan `kp_waiting_lists` -> tetap legacy dan route aktif tetap dikunci feature flag
- kapasitas tempat, tanggal rotasi, pembimbing, jadwal, logbook, nilai -> disiapkan untuk tahap berikutnya

Aturan identitas:

- `core_user_id` adalah key stabil untuk kepesertaan baru.
- Email hanya snapshot, bukan foreign key.
- Tidak ada foreign key lintas database ke Core Farmasi.

## Tahap 03: Kapasitas dan Pembimbing Paralel

Tahap 03 mulai mengganti konsep kuota/kapasitas PKPA dengan tabel paralel `pkpa_*`, tetapi tidak memigrasikan atau mengubah tabel legacy.

Mapping konseptual:

- `kp_place_quotas` -> `pkpa_site_availability_periods` untuk PKPA baru, dengan kapasitas berbasis periode availability.
- mapping tempat ke program -> `pkpa_program_sites`.
- pembimbing lapangan legacy -> `pkpa_site_field_supervisors` berbasis `core_user_id`.
- pembimbing dalam legacy -> `pkpa_internal_supervisor_eligibilities` berbasis `core_user_id`.
- konflik pembimbing -> `pkpa_supervisor_unavailability_periods`.
- audit/sync Core pembimbing -> `pkpa_supervisor_sync_logs`.

Mapping yang belum dieksekusi:

- `kp_place_quotas`, `kp_assignments`, `kp_assignment_logs`, `kp_place_field_supervisors`, dan tabel `kp_*` lain tetap tidak berubah.
- penempatan mahasiswa, urutan rotasi, jadwal, logbook, laporan, dan nilai rotasi belum dibuat.

## Tahap 04: Rancangan Penempatan Paralel

Tahap 04 membuat planner penempatan PKPA baru tanpa memigrasikan assignment legacy.

Mapping konseptual:

- `kp_assignments` -> `pkpa_rotation_assignments` untuk rancangan PKPA baru.
- `kp_assignment_logs` -> audit melalui `pkpa_master_audits` dan histori batch planner.
- kuota/kapasitas legacy -> dihitung dari `pkpa_site_availability_periods`.
- pembimbing legacy -> snapshot Core pada `pkpa_rotation_assignment_supervisors`.

Mapping yang belum dieksekusi:

- Tidak ada migrasi data dari `kp_assignments`.
- Tidak ada publikasi jadwal ke mahasiswa.
- Logbook, laporan, ujian, nilai, dan rekap legacy belum dihubungkan ke assignment baru.

## Tahap 05: Publication Snapshot Paralel

Tahap 05 membuat jadwal resmi PKPA tanpa memigrasikan assignment legacy.

Mapping konseptual:

- `pkpa_rotation_assignments` -> `pkpa_published_assignments` sebagai snapshot resmi.
- `pkpa_rotation_assignment_supervisors` -> `pkpa_published_assignment_supervisors`.
- audit publish/revisi/withdrawal -> `pkpa_master_audits`.
- notifikasi resmi -> `pkpa_notification_deliveries`.

Mapping yang belum dieksekusi:

- `kp_assignments` tetap tidak dipakai untuk jadwal PKPA baru.
- Logbook, presensi, nilai, surat, WhatsApp, dan rekap legacy belum dihubungkan ke publication snapshot.
# Tahap 07 Akademik Rotasi

Modul akademik rotasi menggunakan tabel `pkpa_*` baru dan tidak memigrasikan tabel legacy `kp_*`. Relasi utama adalah `pkpa_rotation_runs`.
## Catatan Tahap 10

Tidak ada migrasi tabel legacy `kp_*` pada Tahap 10. Dokumen dan analytics PKPA memakai tabel `pkpa_*` baru serta snapshot publication/final result. Mapping domain lama hanya menjadi referensi, bukan sumber dokumen resmi internal.
