# KP-SAFA Public Info Whitelist Contract

Tanggal: 2026-06-01

## Tujuan
Menentukan informasi KP yang boleh ditampilkan di SAFA sebagai portal publik/pengumuman. SAFA bukan auth bridge dan bukan tempat data operasional private KP.

## Guardrails
- Tidak ada data individual mahasiswa.
- Tidak ada nilai, berkas, logbook, assignment personal, atau file internal.
- Tidak ada token URL, SSO, auto-login, password, atau secret.
- Info publik sebaiknya melalui approval admin sebelum publish.

## Whitelist Publik
| Kategori | Field Boleh Publik | Sumber KP | Catatan |
|---|---|---|---|
| Portal card | nama aplikasi, deskripsi, URL login/dashboard normal, status umum | config/deployment | URL tidak boleh membawa token |
| Periode aktif | nama periode, tahun akademik, semester, status umum | `KpPeriod` | hanya status publik seperti dibuka/ditutup |
| Timeline | tanggal pendaftaran, verifikasi, pemilihan, pelaksanaan KP | `KpPeriod` | gunakan timezone jelas |
| Persyaratan umum | nama persyaratan, deskripsi, wajib/opsional, tipe file, ukuran maksimal | `KpDocumentRequirement` | tanpa status dokumen mahasiswa |
| Pengumuman | judul, isi ringkas, tanggal tampil, link ke KP | draft admin/period | perlu approval |
| Kontak/admin info | nama unit, email/telepon umum, jam layanan | config/manual | jangan tampilkan kontak personal sensitif |

## Denylist Publik
| Data | Alasan |
|---|---|
| nilai mahasiswa dan final score | data akademik private |
| dokumen mahasiswa | file private |
| logbook dan bukti kegiatan | data proses dan file private |
| status pendaftaran individual | data personal mahasiswa |
| assignment mahasiswa/pembimbing personal | data operasional internal |
| data preseptor sensitif | kontak/relasi eksternal perlu dibatasi |
| file internal, berita acara internal, catatan verifikasi | dokumen administrasi private |
| token, signed URL, password, credential | secret/auth data |

## Bentuk Payload Publik Awal
```json
{
  "source_app": "kp-farmasi",
  "contract_version": "kp-safa-public-v1",
  "period": {
    "name": "KP Farmasi 2026",
    "academic_year": "2025/2026",
    "semester": "genap",
    "status": "dibuka"
  },
  "timeline": [],
  "requirements": [],
  "announcements": [],
  "contact": {}
}
```

## Readiness Criteria
- Ada approval admin untuk pengumuman publik.
- Payload hanya berisi whitelist.
- Link SAFA menuju KP memakai URL normal tanpa credential.
- SAFA card KP tidak memberi hak akses otomatis.
- Tidak ada data individual mahasiswa di SAFA.


