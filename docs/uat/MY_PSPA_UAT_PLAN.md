# MY PSPA UAT Plan

Role:
- Admin PKPA
- Koordinator PKPA
- Mahasiswa
- Pembimbing Dalam
- Pembimbing Lapangan
- Penguji bila digunakan
- Viewer/Auditor bila tersedia

Setiap role diuji untuk login Core, role selection, dashboard, menu, alur utama, access denial, dan logout.

Browser matrix minimal:
- Chrome desktop;
- Edge/Chromium desktop;
- mobile 390x844;
- tablet 768x1024;
- desktop 1366x768.

Data UAT harus dummy/safe dan tidak memakai data production.

## Baseline AI-Assisted Pre-UAT Tahap 11A

Pre-UAT otomatis pada 2026-07-17 memberi status "Layak masuk UAT pengguna dengan catatan".

Sebelum human UAT dimulai, tim wajib membaca:
- `docs/uat/MY_PSPA_AI_ASSISTED_PRE_UAT_RESULT.md`
- `docs/uat/MY_PSPA_AI_PRE_UAT_DEFECT_LOG.md`
- `docs/uat/MY_PSPA_HUMAN_UAT_HANDOFF.md`

Human UAT tetap harus memvalidasi istilah akademik, SOP role, tampilan mobile/tablet aktual, template dokumen resmi, dan kanal notifikasi production.

## Baseline Tahap 11B

Browser E2E Playwright lulus 16/16 pada empat viewport. Human UAT dapat dimulai untuk alur inti dengan kondisi staging yang tercatat di `MY_PSPA_STAGING_UAT_CHECKLIST.md`.
