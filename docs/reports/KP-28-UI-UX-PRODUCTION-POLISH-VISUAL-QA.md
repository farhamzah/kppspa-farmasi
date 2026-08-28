# KP-28 - UI/UX Production Polish & Visual QA

Tanggal: 2026-06-04

## Ringkasan
KP-28 melakukan polish UI/UX production readiness untuk memastikan aplikasi KP Farmasi tidak hanya sehat secara backend/integrasi, tetapi juga layak UAT dari sisi tampilan, navigasi, dan guard responsive.

## File Dibuat/Diubah
Dibuat:
- `app/Console/Commands/UiReadinessCheckCommand.php`
- `tests/Feature/UiReadinessCheckCommandTest.php`
- `docs/reports/KP-28-UI-UX-PRODUCTION-POLISH-VISUAL-QA.md`
- `docs/prompts/PROMPT_KP_28_UI_UX_PRODUCTION_POLISH.md`

Diubah:
- `app/Support/RoleDashboard.php`
- `bootstrap/app.php`
- `tests/Feature/KpRecapExportAndDashboardTest.php`

## Polish Yang Dikerjakan
- Menghapus menu placeholder production dari role:
  - `Catatan Lapangan` pada Preseptor.
  - `Detail Mahasiswa` pada Penguji.
- Menambahkan test agar dashboard semua role tidak menampilkan badge `Segera`.
- Menambahkan command read-only `php artisan kp:ui-readiness-check`.
- Menambahkan audit otomatis untuk:
  - viewport meta,
  - guard horizontal overflow,
  - sidebar scroll guard,
  - topbar/title truncation,
  - responsive table CSS,
  - focus visible keyboard state,
  - login error state,
  - login mobile height,
  - role menu bebas placeholder yang diketahui.

## Command

```bash
php artisan kp:ui-readiness-check
```

Output command sengaja menyisakan warning `visual_browser_screenshot_required` karena screenshot desktop/mobile tetap wajib dilakukan di browser normal sebelum go-live final.

## Status UI/UX
Layak untuk UAT/staging rehearsal:
- Navigasi role sudah stabil.
- Placeholder menu production yang diketahui sudah dihapus.
- Tabel besar punya guard responsive.
- Topbar dan nama user punya truncation guard.
- Focus keyboard tersedia.
- Login punya error state jelas.

## Screenshot Evidence
Screenshot visual desktop/mobile sudah diambil menggunakan Microsoft Edge headless pada 2026-06-04 dan disimpan di:

`docs/reports/kp-28-screenshots/`

Evidence yang tersedia:
- `login-desktop.png`
- `login-mobile.png`
- `admin-dashboard-desktop.png`
- `admin-dashboard-mobile.png`
- `koordinator-dashboard-desktop.png`
- `koordinator-dashboard-mobile.png`
- `mahasiswa-dashboard-desktop.png`
- `mahasiswa-dashboard-mobile.png`
- `pembimbing-dalam-dashboard-desktop.png`
- `pembimbing-dalam-dashboard-mobile.png`
- `pembimbing-lapangan-dashboard-desktop.png`
- `pembimbing-lapangan-dashboard-mobile.png`
- `penguji-dashboard-desktop.png`
- `penguji-dashboard-mobile.png`

Hasil inspeksi visual:
- Login desktop dan mobile tampil proporsional.
- Dashboard Admin desktop tampil padat tetapi rapi untuk operasional.
- Dashboard role mobile tidak menunjukkan overlap fatal.
- Menu placeholder `Segera` tidak muncul pada dashboard role.
- Sidebar mobile berubah menjadi navigasi horizontal yang bisa discroll.

Catatan: screenshot mobile full-page memang terlihat sangat panjang karena seluruh halaman dashboard direkam dari atas sampai footer.

## Guardrails
- Tidak ada fitur besar baru.
- Tidak ada write ke Core/TU/SAFA.
- Tidak ada runtime bridge TU/SAFA yang diaktifkan.
- Tidak ada SSO/autologin/token URL.
- Tidak commit `.env`, `.env.production`, `vendor`, `node_modules`, build output, upload storage, cache, atau log.

## Rekomendasi KP-29
KP-29 sebaiknya menjalankan visual QA manual di browser normal:
1. Login page desktop dan mobile.
2. Dashboard semua role.
3. Halaman tabel padat: pendaftaran, penempatan, logbook, nilai, recap.
4. Form upload dokumen/laporan/logbook.
5. Halaman review integrasi TU/SAFA.
6. Capture screenshot evidence dan catat sign-off UAT.

