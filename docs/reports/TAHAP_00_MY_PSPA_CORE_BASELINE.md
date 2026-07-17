# Tahap 00 - MY PSPA Core Baseline

## Ringkasan

Tahap 00 mengubah baseline SI-KP legacy menjadi MY PSPA, menambahkan landing page publik, menyiapkan mode autentikasi Core HTTP, menutup manajemen akun lokal dan war secara default, serta menambahkan dokumentasi integrasi, migrasi domain, dan UX penempatan.

## Workspace

Struktur aktual:

```text
farmasi-ubp-workspace/
└── apps/
    ├── core-farmasi/
    └── kppspa-farmasi/
```

Instruksi awal menyebut folder langsung di root, tetapi workspace yang ditemukan memakai `apps/`.

## Status Git Awal dan Akhir

- `apps/core-farmasi`: `main...origin/main`, bersih.
- `apps/kppspa-farmasi`: `main...origin/main`, sudah memiliki perubahan lokal sebelum Tahap 00 pada `app/Http/Controllers/Management/KpAssignmentController.php`, `app/Support/SimplePdfReport.php`, `resources/views/management/assignments/report-word.blade.php`, `app/Exports/KpTableReportExport.php`, dan `docs/manuals/`.
- Tidak ada commit, push, tag, merge, atau release.

## Audit Core Farmasi

Temuan aktual dari `apps/core-farmasi/routes/api.php` dan `docs/CORE-INTERNAL-API.md`:

- User model: `App\Models\User` menyimpan `name`, `email`, `username`, `identity_type`, `identity_number`, `password`, `active`, `api_token`, `must_change_password`.
- Role: relasi many-to-many `roles`, role global dan app access dipisah.
- Auth API: `POST /api/v1/auth/login` mengembalikan bearer token dan safe user untuk akun aktif.
- Token validation: `GET|POST /api/v1/auth/validate-token`.
- Health: `GET /api/v1/health`.
- Internal app access: `GET /api/v1/internal/apps/{app_code}/users/{user}/access` memakai app-client credentials.
- Directory read-only: users, students, lecturers, employees, study-programs, departments.
- App client credential: `X-Core-App-Code`, `X-Core-Client-Id`, `X-Core-Client-Secret`.
- Gap: belum ditemukan SSO/OAuth/central logout khusus MY PSPA; belum ditemukan endpoint password verification khusus `kppspa-farmasi` seperti endpoint TU.

## Perubahan MY PSPA

- Rebranding utama menjadi MY PSPA pada README, AGENTS, env examples, layout, login, role dashboard, role seeder, dan landing.
- Route `/` sekarang landing page publik.
- `KP_AUTH_MODE` default config menjadi `core_http`.
- `CORE_FARMASI_*` env alias ditambahkan dengan fallback `KP_CORE_*` lama.
- `config/my_pspa.php` menampung feature flag dan tautan akun Core.
- Middleware baru:
  - `EnsureLocalAccountManagementEnabled`
  - `EnsureStudentPlaceSelectionEnabled`
- Test Tahap 00 baru memakai `Http::fake` tanpa akses jaringan.

## Integrasi Autentikasi

Mode `core_http`:

1. MY PSPA mengirim email/password ke Core `POST /api/v1/auth/login`.
2. MY PSPA membaca app access user di Core.
3. MY PSPA membuat atau memperbarui local projection minimal.
4. Password Core tidak disimpan dan tidak diverifikasi secara lokal.
5. Jika Core down, inactive, app access hilang, atau kontrak tidak lengkap, login ditolak dengan pesan aman.

Mode legacy/core_bridge lama tetap ada untuk test dan transisi, tetapi default Tahap 00 diarahkan ke Core HTTP.

## Integrasi Role

Mapping saat ini:

- `mahasiswa` -> `mahasiswa`
- `admin-kp` -> `admin`
- `koordinator-kp` -> `koordinator_kp`
- `pembimbing-dalam` -> `pembimbing_dalam`
- `pembimbing-lapangan` -> `pembimbing_lapangan`
- `penguji` -> `penguji`

Role aktif disimpan di session dan divalidasi terhadap role lokal hasil sync dari Core. Role yang tidak diberikan Core tidak dapat dipilih atau dipakai.

## Landing Page

Landing publik mencakup hero MY PSPA, informasi PKPA, enam wahana, literatur/panduan, dan tautan login/akun Core berbasis konfigurasi.

## Status Akun Lokal

- Registrasi lokal: tidak tersedia.
- Reset password lokal: tidak tersedia sebagai public route.
- Manajemen akun lokal: dikunci default `MY_PSPA_LOCAL_ACCOUNT_MANAGEMENT_ENABLED=false`.
- Import user lama: ikut terkunci dalam grup manajemen akun lokal.
- Role assignment lokal: tidak menjadi alur aktif Tahap 00.

## Status War

- Route mahasiswa `/mahasiswa/pemilihan-tempat*` dikunci default.
- Route monitoring/manual selection legacy dikunci default.
- Menu mahasiswa tidak lagi menampilkan pemilihan tempat.
- Tabel dan kode lama dipertahankan sementara untuk migrasi dan test legacy.

## File Penting

Perubahan MY PSPA:

- `config/my_pspa.php`
- `config/core_farmasi.php`
- `config/kp_auth.php`
- `app/Services/CoreFarmasiClient.php`
- `app/Services/CoreBridgeAuthService.php`
- `app/Http/Middleware/EnsureLocalAccountManagementEnabled.php`
- `app/Http/Middleware/EnsureStudentPlaceSelectionEnabled.php`
- `routes/web.php`
- `resources/views/welcome.blade.php`
- `resources/views/auth/login.blade.php`
- `resources/views/layouts/app.blade.php`
- `README.md`
- `AGENTS.md`
- `tests/Feature/Tahap00MyPspaBaselineTest.php`

Dokumentasi workspace:

- `../../docs/architecture/FARMASI_UBP_WORKSPACE_INTEGRATION.md`

Perubahan Core Farmasi: tidak ada.

## QA

Berhasil:

```bash
php artisan optimize:clear
php artisan migrate:fresh --seed --force
php artisan test
npm run build
```

Catatan migration QA: dijalankan memakai SQLite sementara `C:\tmp\my_pspa_tahap00.sqlite`, bukan database MySQL lokal.

Hasil test:

```text
250 passed, 1554 assertions
```

Hasil build:

```text
vite build completed, 55 modules transformed
```

Browser manual responsive belum dijalankan pada Tahap 00 ini; landing dan login tercakup feature test/render test.

## Kendala dan Risiko

- Banyak label domain legacy KP masih ada pada modul lama, report lama, dan dokumen historis. Tahap 00 hanya mengganti area aktif utama.
- Core belum menyediakan kontrak SSO/central logout khusus MY PSPA.
- Local projection masih memakai tabel `users` legacy karena foreign key aplikasi lama.
- `core_http` bergantung pada app client `kppspa-farmasi` tersedia di Core.

## Rekomendasi Tahap Berikutnya

Tahap 01 - Master Program PKPA, Enam Wahana Default, Pilihan Pemerintahan, Tempat Praktik, dan Konfigurasi Durasi dengan Referensi User dari Core Farmasi.
