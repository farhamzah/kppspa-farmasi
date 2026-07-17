# MY PSPA Core Integration Spec

## Endpoint Core yang Ditemukan

Sumber audit: `apps/core-farmasi/routes/api.php` dan `apps/core-farmasi/docs/CORE-INTERNAL-API.md`.

- `GET /api/v1/health`
- `POST /api/v1/auth/login`
- `GET|POST /api/v1/auth/validate-token`
- `GET /api/v1/internal/apps/{app_code}/users/{user}/access`
- `GET /api/v1/internal/directory/users`
- `GET /api/v1/internal/directory/users/{id}`
- `GET /api/v1/internal/directory/students`
- `GET /api/v1/internal/directory/students/{id}`
- `GET /api/v1/internal/directory/lecturers`
- `GET /api/v1/internal/directory/lecturers/{id}`
- `GET /api/v1/internal/directory/employees`
- `GET /api/v1/internal/directory/employees/{id}`
- `GET /api/v1/internal/directory/study-programs`
- `GET /api/v1/internal/directory/study-programs/{id}`
- `GET /api/v1/internal/directory/departments`
- `GET /api/v1/internal/directory/departments/{id}`

## Auth

Mode Tahap 00: `KP_AUTH_MODE=core_http`.

Alur:

1. User mengisi email dan password di MY PSPA.
2. MY PSPA mengirim kredensial ke `POST /api/v1/auth/login`.
3. Core memverifikasi password dan status aktif.
4. MY PSPA membaca app access melalui `GET /api/v1/internal/apps/kppspa-farmasi/users/{id}/access`.
5. MY PSPA membuat/memperbarui projection lokal minimal.
6. Role aktif disimpan di session jika hanya satu role; multi-role diarahkan ke selector.

## App Client Credentials

Endpoint internal memakai:

```text
X-Core-App-Code
X-Core-Client-Id
X-Core-Client-Secret
```

Secret tidak boleh dicatat di log, file, report, atau screenshot.

## Local Projection

Projection lokal dapat menyimpan:

- `core_user_id`
- `name`
- `email`
- `status`
- `core_synced_at`
- `core_sync_status`
- `core_sync_note`

Projection lokal tidak boleh menjadi sumber password atau role master.

## Role Mapping

Mapping Tahap 00:

- `mahasiswa` -> `mahasiswa`
- `admin-kp` -> `admin`
- `koordinator-kp` -> `koordinator_kp`
- `pembimbing-dalam` -> `pembimbing_dalam`
- `pembimbing-lapangan` -> `pembimbing_lapangan`
- `penguji` -> `penguji`

`admin-core` tidak otomatis memberi akses MY PSPA.

## Error Handling

- Core unavailable: login ditolak dengan pesan aman.
- Core inactive user: login ditolak.
- Missing app access: login ditolak.
- Missing role mapping: login ditolak.
- Response tidak lengkap: login ditolak dan dicatat sebagai gap kontrak.

## Gap

Belum ditemukan endpoint khusus `kppspa-farmasi` untuk password verification seperti endpoint TU. Belum ditemukan central logout/SSO resmi untuk MY PSPA.
