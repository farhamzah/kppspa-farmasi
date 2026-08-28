# KP-SAFA Public Info Preview

Tanggal: 2026-06-01

## Tujuan
Dokumen ini menjelaskan preview read-only informasi publik KP untuk SAFA. Preview hanya memuat field whitelist dari kontrak KP-15 dan tidak mengirim request ke SAFA.

## Command
```bash
php artisan kp:safa-public-info-preview
```

Opsi:
```bash
php artisan kp:safa-public-info-preview --period-id=1
```

## Browser Review KP-17
Halaman review Admin/Koordinator:

```text
GET /management/integration/safa-public-info-preview
```

Endpoint JSON preview tersanitasi:

```text
GET /management/integration/safa-public-info-preview.json
```

Keduanya dilindungi middleware `auth`, `active`, `role.selected`, dan `role:admin,koordinator_kp`. Endpoint JSON tidak membuat file permanen dan tidak mengirim request ke SAFA.

## Struktur Payload
```json
{
  "source_app": "kp-farmasi",
  "contract_version": "kp-safa-public-v1",
  "dry_run": true,
  "external_request_sent": false,
  "public_visibility": "public_safe_preview",
  "period": {},
  "timeline": [],
  "requirements": [],
  "announcements": [],
  "contact": {},
  "registration_status": {},
  "private_data_excluded": true,
  "validation_warnings": [],
  "read_only_counts": {
    "unchanged": true
  }
}
```

## Data Publik Yang Masuk
- periode aktif atau periode terbaru;
- tahun akademik dan semester;
- status umum periode;
- timeline pendaftaran, verifikasi, pemilihan tempat, dan pelaksanaan KP;
- persyaratan umum aktif;
- pengumuman umum berbasis periode;
- placeholder kontak/admin umum;
- status umum pendaftaran dan pemilihan.

## Data Yang Sengaja Tidak Keluar
- nilai mahasiswa;
- dokumen mahasiswa;
- logbook;
- laporan akhir;
- status individual mahasiswa;
- kontak privat preseptor;
- path file internal;
- token, signed URL, password, atau secret.

## Read-only Guarantee
Command hanya membaca `kp_periods` dan `kp_document_requirements` untuk output public-safe. Tidak ada request HTTP ke SAFA dan tidak ada insert/update/delete. Count sebelum dan sesudah ditampilkan dalam `read_only_counts`.

