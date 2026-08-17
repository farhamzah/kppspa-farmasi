# Spesifikasi Pembuat Portofolio MY PKPA

## Tujuan

Pembuat Portofolio adalah lapisan agregasi per `pkpa_rotation_runs`. Satu rotasi menghasilkan maksimal satu portofolio aktif melalui `PkpaPortfolioBuilderService::ensureForRun()`.

## Database

Tabel utama:

- `pkpa_portfolio_templates`
- `pkpa_portfolio_template_sections`
- `pkpa_rotation_portfolios`
- `pkpa_portfolio_section_records`
- `pkpa_portfolio_case_reports`
- `pkpa_portfolio_weekly_reflections`
- `pkpa_portfolio_self_assessments`
- `pkpa_portfolio_documentation_items`
- `pkpa_portfolio_reviews`
- `pkpa_portfolio_publications`
- `pkpa_portfolio_export_versions`

## Pemetaan Data Yang Sudah Ada

Portofolio tidak menggandakan data operasional. Bagian otomatis menyimpan referensi:

- identitas: `pkpa_enrollments`
- penempatan: `pkpa_rotation_runs`, `pkpa_published_assignments`
- presensi: `pkpa_attendance_records`
- logbook: `pkpa_logbook_entries`
- kompetensi: `pkpa_rotation_competency_records`
- tugas khusus: `pkpa_rotation_special_tasks`
- laporan rotasi: `pkpa_rotation_reports`
- assessment dan nilai: `pkpa_rotation_assessments`, `pkpa_rotation_grade_results`

Snapshot hanya dibuat pada `identity_snapshot`, `placement_snapshot`, `progress_snapshot`, dan publication snapshot.

## Alur Kerja

Kode status internal portofolio:

`draft`, `in_progress`, `submitted_to_field_supervisor`, `field_revision_requested`, `field_verified`, `submitted_to_internal_supervisor`, `internal_revision_requested`, `approved`, `locked`, `published`, `superseded`, `cancelled`.

Mahasiswa mengisi bagian manual, menyetujui pakta integritas, lalu mengirim ke PL. PL memvalidasi lapangan dan privasi pasien. Mahasiswa melanjutkan pengiriman ke PD setelah PL melakukan verifikasi. PD menyetujui tahap akhir. Admin/Koordinator dapat membuka ulang dengan alasan dan menerbitkan/mengunci portofolio.

## Unduhan

Unduhan akhir tersedia sebagai DOCX dan PDF di penyimpanan privat melalui `pkpa_portfolio_export_versions`. Unduhan versi terbit memakai snapshot penerbitan dan tidak ditimpa.

DOCX dibuat sebagai dokumen Office yang valid. PDF dibuat sebagai acuan internal awal melalui `SimplePdfReport`. Kedua format harus tetap disimpan privat dan direferensikan melalui versi unduhan.

## Acuan QA

Baseline final Tahap 14:

- Playwright final: 16 lulus, 0 gagal, 0 dilewati, 3.9 menit.
- PHPUnit final: 303 lulus, 2097 asersi.
- Build: lulus.
- Integrity audit: `issue_count=0`.
- Audit dokumen tanpa berkas: `missing_count=0`.
- Kesehatan antrean: `failed_jobs=0`.
