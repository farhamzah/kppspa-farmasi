# MY PKPA Role Access Matrix

| Modul | Admin | Koordinator | Mahasiswa | PD | PL |
| --- | --- | --- | --- | --- | --- |
| Program PKPA | Ya | Ya | Tidak | Tidak | Tidak |
| Penempatan | Ya | Ya | Baca jadwal published | Baca terkait | Baca terkait |
| Operasional | Ya | Ya | Milik sendiri | Bimbingan | Bimbingan |
| Akademik | Ya | Ya | Milik sendiri | Bimbingan | Bimbingan |
| Penilaian | Ya | Ya | Hasil released | Input terkait | Input terkait |
| Penyelesaian PKPA | Ya | Ya | Hasil released | Tidak | Tidak |
| Dokumen PKPA | Ya | Ya | Dokumen recipient | Dokumen recipient | Dokumen recipient |
| Analytics | Ya | Ya | Tidak | Tidak | Tidak |

Semua download file akademik dan dokumen wajib melalui route authorized.
# Catatan AI-Assisted Pre-UAT

Role access lintas Admin PKPA, Koordinator, Mahasiswa, Pembimbing Dalam, Preseptor, dan Penguji telah tercakup regression test. Pada human UAT, matrix ini tetap harus dikonfirmasi terhadap keputusan fakultas dan assignment role dari Core Farmasi.

## Browser E2E Tahap 11B

Playwright menutup smoke utama:
- Guest: landing, login, health, protected redirect.
- Admin: dashboard, master, peserta, planner, publikasi, operasional, akademik, penilaian, final, dokumen, analytics, admin health.
- Koordinator multi-role: role selector dan route koordinator.
- Mahasiswa: dashboard, jadwal/rotasi, akademik, nilai, hasil akhir, dokumen.
- Pembimbing Dalam: monitoring, akademik, penilaian; tidak dapat memakai route PL.
- Preseptor: operasional, akademik, penilaian.
- Authorization: mahasiswa tidak dapat mengakses dokumen management/export.

