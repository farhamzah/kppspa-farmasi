# MY PKPA Post-Deploy Smoke Result

Status: not executed on real target.

Alasan:
- URL server target dan credential deployment tidak tersedia pada sesi ini.
- Tidak ada instruksi eksplisit untuk deployment production.

Template hasil target:
| Area | Scenario | Result | Evidence | Notes |
|---|---|---|---|---|
| Guest | Landing HTTPS | Not tested | | |
| Guest | Login | Not tested | | |
| Health | `/health` | Not tested | | |
| Admin | Core login and dashboard | Not tested | | |
| Koordinator | Readiness and placement | Not tested | | |
| Mahasiswa | Dashboard, jadwal, dokumen | Not tested | | |
| Pembimbing Dalam | Monitoring queue | Not tested | | |
| Preseptor | Review queue | Not tested | | |
| Queue | worker and failed jobs | Not tested | | |
| Audit | integrity and orphan | Not tested | | |

Local smoke baseline:
- Playwright 16 passed pada Tahap 11B/12A gate.

