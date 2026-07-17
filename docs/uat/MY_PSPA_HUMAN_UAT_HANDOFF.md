# MY PSPA Human UAT Handoff

Status handoff: siap masuk Human UAT dengan kondisi.

Yang perlu divalidasi pengguna:
- Kebenaran istilah akademik PKPA, nama wahana, dan status workflow.
- Kewenangan tiap role sesuai SOP fakultas.
- Alur penempatan, publikasi, operasional rotasi, akademik rotasi, penilaian, hasil akhir, dan dokumen.
- Tampilan mobile/tablet aktual pada perangkat pengguna.
- Template dokumen resmi, nomor dokumen, penandatangan, dan kop hanya setelah disetujui pengelola.
- Notifikasi email/WhatsApp bila kanal produksi sudah dikonfigurasi.

Akun UAT:
- Gunakan akun Core Farmasi atau akun demo lokal hanya untuk environment UAT.
- Jangan memakai password default pada production.

Batasan:
- Dokumen ini bukan bukti persetujuan pengguna.
- Dokumen ini bukan approval go-live.
- Catatan open dari `MY_PSPA_AI_PRE_UAT_DEFECT_LOG.md` perlu ditutup atau diterima formal oleh owner UAT.

## Update Tahap 11B

Dokumen siap pakai:
- `docs/uat/MY_PSPA_HUMAN_UAT_EXECUTION_GUIDE.md`
- `docs/uat/MY_PSPA_STAGING_UAT_CHECKLIST.md`
- `docs/uat/MY_PSPA_BROWSER_E2E_MATRIX.md`

Kondisi non-blocking:
- Akun Core test nyata untuk staging belum diberikan melalui `E2E_*`.
- Server staging HTTPS belum tersedia di sesi lokal ini.
- Scheduler production belum memiliki task aktif.
- Queue production async/worker harus dikonfirmasi ulang di staging.
