# MY PSPA Hypercare Incident Matrix

| Severity | Contoh | Tindakan | Target respons |
|---|---|---|---|
| P0 Critical | kebocoran data, seluruh login gagal, database rusak, file private terbuka, authorization bypass | hentikan layanan/fitur terdampak, maintenance mode, rollback bila perlu, informasikan Project Owner | segera |
| P1 High | alur utama satu role gagal, publication gagal, presensi/logbook tidak dapat digunakan, dokumen utama gagal, queue menggandakan data | hotfix prioritas, workaround terkontrol, monitoring ketat | hari yang sama |
| P2 Medium | ada workaround, validasi kurang, tampilan membingungkan, fitur nonkritis gagal | minor fix terjadwal, komunikasi workaround | 1-3 hari kerja |
| P3 Low/Cosmetic | label, alignment, minor UI | backlog atau batch fix | sesuai prioritas |

Aturan:
- P0/P1 harus tercatat di hypercare log.
- Hotfix P0/P1 wajib punya regression test.
- Jangan mencatat password, token, secret, atau data pribadi berlebihan.
