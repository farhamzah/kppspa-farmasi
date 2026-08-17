# MY PKPA Core Student Import Spec

## Tujuan

Import peserta memungkinkan Admin PKPA dan Koordinator PKPA menambahkan peserta Program PKPA dari Core Farmasi secara massal.

## Template

Kolom CSV/XLSX:

```text
core_user_id,npm,group_code,notes
```

Minimal salah satu dari `core_user_id` atau `npm` wajib terisi. `group_code` dan `notes` opsional.

Kolom `password`, `role`, dan `roles` ditolak sebelum file disimpan sebagai batch import.

## Preview

Upload selalu menghasilkan preview terlebih dahulu. Preview menyimpan:

- batch import;
- row validasi;
- payload mentah aman tanpa password/role;
- status validasi;
- pesan validasi;
- Core ID hasil resolve bila tersedia.

Preview tidak membuat enrollment.

## Status Row

Status yang dipakai:

- `valid`
- `duplicate_file`
- `duplicate_database`
- `not_found`
- `inactive`
- `invalid_role`
- `app_access_denied`
- `identity_mismatch`
- `group_not_found`
- `group_full`
- `import_failed`

Row invalid tidak memblokir row valid.

## Final Import

Final import hanya memproses row `valid` yang belum memiliki `resolved_enrollment_id`.

Setiap row valid:

1. Resolve mahasiswa ulang ke Core.
2. Buat enrollment.
3. Buat enam requirement.
4. Tambahkan ke kelompok bila `group_code` valid.
5. Catat `resolved_enrollment_id` dan `imported_at`.

Jika satu row gagal saat final import, row tersebut menjadi `import_failed`; row valid lain tetap diproses.

## Core Unavailable

Jika Core tidak memberi data saat preview atau sync, sistem tidak membuat enrollment baru, tidak menghapus snapshot lama, dan menampilkan pesan aman tanpa raw exception.

## Batasan

Import tidak membuat akun, password, role, app access, atau perubahan data di Core. Import tidak mengelola penempatan tempat, pembimbing, jadwal, atau rotasi.
