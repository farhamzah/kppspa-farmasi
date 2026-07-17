# MY PSPA

MY PSPA adalah Sistem Informasi Program Studi Profesi Apoteker untuk pengelolaan Praktik Kerja Profesi Apoteker (PKPA) Fakultas Farmasi UBP.

## Status Tahap 11A

AI-Assisted Pre-UAT selesai dengan status: Layak masuk UAT pengguna dengan catatan.

Command penting:

```bash
php artisan pkpa:integrity-audit --json
php artisan pkpa:queue-health --json
php artisan pkpa:document-orphan-audit --json
```

Laporan utama:
- `docs/reports/TAHAP_11A_AI_ASSISTED_PRE_UAT.md`
- `docs/uat/MY_PSPA_AI_ASSISTED_PRE_UAT_RESULT.md`
- `docs/uat/MY_PSPA_HUMAN_UAT_HANDOFF.md`

## Status Tahap 11B

Penutupan catatan Pre-UAT selesai dengan status: Siap Human UAT dengan kondisi.

Browser E2E:

```bash
set E2E_BASE_URL=http://127.0.0.1:3006
npm.cmd run e2e
```

Framework browser: Playwright. Test mencakup desktop 1366x768, desktop-wide 1920x1080, tablet 768x1024, dan mobile 390x844.

Laporan utama:
- `docs/reports/TAHAP_11B_PRE_UAT_CONDITION_CLOSURE.md`
- `docs/reports/MY_PSPA_BROWSER_E2E_REPORT.md`
- `docs/uat/MY_PSPA_STAGING_UAT_CHECKLIST.md`

## Status Tahap 12A

Persiapan controlled go-live dan hypercare selesai dengan status: Persiapan selesai, deployment belum dilakukan.

Status resmi:

```text
Human UAT: Waived by Project Owner
Release approach: Controlled Go-Live
Post-release mode: Intensive Hypercare
```

Catatan penting: Human UAT formal dilewati berdasarkan keputusan Project Owner. Dokumen ini tidak menyatakan klaim UAT diterima, selesai, atau disetujui pengguna.

Command monitoring tambahan:

```bash
php artisan pkpa:hypercare-status --json
```

Laporan utama:
- `docs/reports/TAHAP_12A_CONTROLLED_GO_LIVE_AND_HYPERCARE.md`
- `docs/releases/MY_PSPA_HUMAN_UAT_WAIVER.md`
- `docs/releases/MY_PSPA_CONTROLLED_GO_LIVE_CHECKLIST.md`
- `docs/releases/MY_PSPA_HYPERCARE_PLAN.md`
- `docs/hypercare/MY_PSPA_HYPERCARE_LOG.md`

## Ruang Lingkup

- Program PKPA, kepesertaan, wahana, tempat praktik, rotasi, penempatan, jadwal, pembimbing, logbook, laporan, tugas, ujian, penilaian, monitoring, rekap, dokumen, dan pengumuman.
- Akun, password, status akun, identitas dasar, role, dan assignment role menjadi kewenangan Core Farmasi.
- Tabel `users` lokal diperlakukan sebagai local identity reference/projection untuk kebutuhan guard Laravel dan relasi legacy.

## Integrasi Core Farmasi

Mode default Tahap 00 adalah `KP_AUTH_MODE=core_http`.

Konfigurasi utama:

```env
CORE_FARMASI_ENABLED=true
CORE_FARMASI_URL=https://core.example.test
CORE_FARMASI_APP_CODE=kppspa-farmasi
CORE_FARMASI_CLIENT_ID=
CORE_FARMASI_CLIENT_SECRET=
CORE_FARMASI_TIMEOUT=10
```

Login memvalidasi password ke Core Farmasi, membaca app-access role dari Core, lalu membuat atau memperbarui projection lokal minimal tanpa menyimpan password Core.

## Guardrail Tahap 00

- Registrasi lokal tidak disediakan.
- Reset password lokal tidak disediakan.
- Manajemen akun lokal dinonaktifkan secara default melalui `MY_PSPA_LOCAL_ACCOUNT_MANAGEMENT_ENABLED=false`.
- War/pemilihan tempat oleh mahasiswa dinonaktifkan secara default melalui `MY_PSPA_STUDENT_PLACE_SELECTION_ENABLED=false`.
- Penempatan PKPA disusun oleh Koordinator PKPA.

## Master PKPA Tahap 01

Tahap 01 menambahkan modul master paralel:

- Program PKPA (`pkpa_programs`)
- Wahana PKPA (`pkpa_practice_domains`)
- Pilihan wahana (`pkpa_practice_domain_options`)
- Konfigurasi wahana dan durasi program (`pkpa_program_domains`)
- Tempat praktik (`pkpa_practice_sites`)
- Audit master (`pkpa_master_audits`)

Seeder `PkpaMasterSeeder` membuat enam wahana default dan dua pilihan Pemerintahan secara idempotent. Seeder tidak membuat tempat praktik palsu dan tidak menetapkan durasi resmi.

Program baru otomatis memiliki enam konfigurasi wahana. Durasi dapat diisi berbeda per wahana dan program hanya dapat diaktifkan setelah checklist kesiapan valid.

## Kepesertaan PKPA Tahap 02

Tahap 02 menambahkan fondasi peserta dan kelompok:

- Peserta PKPA (`pkpa_enrollments`) dengan `core_user_id` dari Core Farmasi.
- Enam kewajiban wahana otomatis (`pkpa_enrollment_requirements`) saat peserta dibuat.
- Pemerintahan tetap satu kewajiban `choose_one`; Loka POM/Dinas Kesehatan belum dipilih pada tahap ini.
- Kelompok mahasiswa dan histori membership (`pkpa_student_groups`, `pkpa_student_group_members`).
- Import CSV/XLSX dengan template, preview validasi, batch, row, dan final import hanya untuk row valid.
- Sinkronisasi snapshot identitas dari Core tanpa mengubah `core_user_id`.

Tahap ini tidak membuat akun/password/role lokal dan belum membuat penempatan rotasi, jadwal, pembimbing, atau migrasi data `kp_*`.

## Kapasitas dan Pembimbing PKPA Tahap 03

Tahap 03 menambahkan readiness sebelum penyusunan penempatan:

- Tempat tersedia per program (`pkpa_program_sites`).
- Availability period, kapasitas, reserved slot, hari operasional, dan jam praktik (`pkpa_site_availability_periods`).
- Pembimbing Lapangan dari Core per tempat praktik (`pkpa_site_field_supervisors`).
- Eligibility Pembimbing Dalam dari Core per program-wahana (`pkpa_internal_supervisor_eligibilities`).
- Periode tidak tersedia pembimbing dan log sinkronisasi Core.
- Checklist readiness penempatan untuk admin/koordinator.

Tahap ini belum membuat assignment mahasiswa, matrix rotasi, jadwal rotasi, logbook rotasi, nilai, atau migrasi `kp_*`.

## Penyusunan Penempatan PKPA Tahap 04

Tahap 04 menambahkan rancangan penempatan draft/tervalidasi untuk Koordinator PKPA:

- Versi rancangan (`pkpa_placement_plans`) dengan satu current plan per program.
- Assignment rotasi per requirement (`pkpa_rotation_assignments`) dan pembimbing per assignment.
- Matriks mahasiswa x enam wahana, tampilan kartu mobile, timeline rotasi, issue list, bulk preview/apply/undo, dan export internal `.xlsx`.
- Validasi backend untuk tempat, availability, tanggal, durasi, kapasitas, bentrok jadwal mahasiswa, Pembimbing Dalam, Pembimbing Lapangan, beban, unavailability, serta pilihan Pemerintahan.

Tahap ini hanya menyusun rancangan internal. Jadwal belum dipublikasikan ke mahasiswa/pembimbing, notifikasi belum dikirim, dan tabel legacy `kp_*` tidak dimigrasikan.

## Publikasi Penempatan PKPA Tahap 05

Tahap 05 menambahkan jadwal resmi berbasis snapshot:

- Publication resmi (`pkpa_placement_publications`) dari plan yang tervalidasi dan terkunci.
- Snapshot assignment dan pembimbing (`pkpa_published_assignments`, `pkpa_published_assignment_supervisors`).
- Portal `PKPA Saya` untuk mahasiswa dan `Jadwal PKPA` untuk Pembimbing Dalam/Lapangan.
- Acknowledgement sebagai tanda membaca, bukan approval.
- Notification delivery database/email berbasis feature flag.
- Withdrawal, change request, publication revision, dan export Excel resmi.

Portal published tidak membaca draft planner dan Tahap 05 tetap tidak memigrasikan tabel legacy `kp_*`.

## Operasional Rotasi PKPA Tahap 06

Tahap 06 menambahkan operasional rotasi setelah jadwal resmi dipublikasikan:

- Runtime rotasi dari publikasi current (`pkpa_rotation_runs`) beserta histori status dan pembimbing.
- Aturan operasional per wahana (`pkpa_rotation_operation_rules`).
- Presensi, validasi Pembimbing Lapangan, dan koreksi presensi.
- Logbook rotasi, lampiran privat non-public, review Pembimbing Lapangan, dan monitoring Pembimbing Dalam.
- Snapshot progress dan sinkronisasi perubahan publikasi ke runtime.
- Menu baru: `Operasional Rotasi` untuk Admin/Koordinator, `Rotasi PKPA` untuk Mahasiswa, `Operasional PKPA` untuk Pembimbing Lapangan, dan `Monitoring PKPA` untuk Pembimbing Dalam.

Tahap ini tidak menghitung nilai, tidak mengubah requirement menjadi completed secara otomatis, dan tidak memigrasikan tabel legacy `kp_*`.

Konfigurasi tambahan:

```env
PKPA_ROTATION_OPERATIONS_ENABLED=true
PKPA_LOGBOOK_ATTACHMENT_DISK=local
PKPA_LOGBOOK_ATTACHMENT_MAX_KB=5120
```

## Akademik Rotasi PKPA Tahap 07

Tahap 07 menambahkan kelengkapan akademik per runtime rotasi:

- Master set kompetensi, kategori, dan item per program-wahana.
- Checklist kompetensi runtime berbasis snapshot, evidence private, verifikasi Pembimbing Lapangan, dan monitoring Pembimbing Dalam.
- Template tugas khusus, assignment tugas ke runtime, submission berversi, dan review.
- Template laporan rotasi, laporan utama per runtime, versioning file, konfirmasi Pembimbing Lapangan, dan approval Pembimbing Dalam.
- Bimbingan rotasi dan acknowledgement mahasiswa.
- Academic readiness untuk status `ready_for_assessment` tanpa nilai dan tanpa menyelesaikan requirement.
- Menu baru `Akademik Rotasi` dan `Akademik PKPA` untuk role terkait.

## Penilaian Wahana PKPA Tahap 08

Tahap 08 menambahkan penilaian per rotasi/wahana: skema, komponen, rubrik, assessor, score, moderasi, finalisasi nilai wahana, release nilai mahasiswa, dan export rekap. Nilai ini bukan nilai akhir keseluruhan Program PKPA dan tidak mengubah status requirement menjadi completed.

## Nilai Akhir dan Kelulusan PKPA Tahap 09

Tahap 09 menambahkan penyelesaian program: skema nilai akhir, agregasi nilai wahana finalized, completion requirement, remedial, kalkulasi final, keputusan kelulusan, release hasil akhir, dan portal hasil akhir mahasiswa. Hasil ini adalah hasil akademik dalam MY PSPA, bukan dokumen resmi universitas.

Konfigurasi tambahan:

```env
PKPA_COMPETENCY_ENABLED=true
PKPA_SPECIAL_TASK_ENABLED=true
PKPA_ROTATION_REPORT_ENABLED=true
PKPA_GUIDANCE_ENABLED=true
PKPA_ACADEMIC_DATABASE_NOTIFICATIONS_ENABLED=true
PKPA_ACADEMIC_EMAIL_NOTIFICATIONS_ENABLED=false
PKPA_ACADEMIC_FILE_DISK=local
PKPA_ACADEMIC_FILE_MAX_SIZE_KB=5120
```

## Development

```bash
composer install
npm install
php artisan key:generate
php artisan migrate --seed
php artisan test
npm run build
```

Gunakan database development/testing. Jangan memakai Core production untuk automated test; gunakan fake/mocking HTTP.
## Dokumen, Analytics, Hardening, dan UAT Tahap 10

MY PSPA kini memiliki fondasi Dokumen Internal PKPA, template berversi, penomoran configurable, generation DOCX/PDF/XLSX/CSV pada private storage, portal dokumen mahasiswa, Pelaporan dan Analytics, health check, security headers, rate limit download/export, queue health, orphan file audit dry-run, serta dokumen operasi dan UAT. Status production tetap `Ready with condition` sampai UAT real, queue/mail production, backup restore, dan browser matrix selesai.
