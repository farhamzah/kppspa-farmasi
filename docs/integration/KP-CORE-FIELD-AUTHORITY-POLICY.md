# KP-Core Field Authority Policy

Tanggal: 2026-06-01

## Tujuan
Menentukan field mana yang authoritative dari Core dan field mana yang tetap dimiliki KP. Policy ini mencegah duplikasi data yang tidak terkendali saat KP bergerak dari mode legacy menuju mode Core-preferred.

## Prinsip
- Core adalah sumber identitas utama lintas aplikasi.
- KP tetap sumber data operasional Kerja Praktek.
- KP boleh menyimpan snapshot transaksi agar dokumen dan arsip historis tetap valid.
- KP tidak menulis perubahan master identity ke Core.
- Saat Core mode aktif, field identity di KP sebaiknya read-only atau diarahkan ke profile portal Core.

## Hirarki Unit Akademik Core
Urutan unit akademik mengikuti Core:

1. Fakultas
2. Program studi
3. Department

Policy penting:
- Fakultas bukan department.
- Label `Fakultas Farmasi` hanya boleh dipakai sebagai unit fakultas, bukan nilai canonical untuk `lecturers.department`.
- Label prodi legacy KP `Farmasi` diperlakukan sebagai alias menuju canonical Core `Farmasi S1`.
- Label department legacy KP `Farmasi Klinis` diperlakukan sebagai alias menuju canonical Core `Farmakologi dan Farmasi Klinik`.
- KP tidak melakukan koreksi otomatis ke data Core atau data lokal dari diagnostic; perbaikan data harus lewat proses admin/mapping yang disetujui.

## User Identity
| Field | Authority | KP Usage | Catatan |
|---|---|---|---|
| nama user | Core | display, snapshot dokumen | KP fallback legacy saat Core unavailable sesuai mode |
| email | Core | login/mapping/display | harus unik dan stabil untuk bridge |
| username | Core | optional display/login Core | tidak wajib di KP legacy |
| identity_type | Core | klasifikasi user | read-only |
| identity_number | Core | mapping/picker | read-only |
| active/status | Core + KP local guard | login authorization | Core inactive harus fail closed pada Core bridge |
| password/hash/token | Core/KP auth internals | tidak diekspos | tidak boleh masuk report/log |
| avatar | KP sementara | UI lokal | kandidat pindah ke Core profile setelah profile portal final |

## Mahasiswa
| Field | Authority | KP Usage | Catatan |
|---|---|---|---|
| NIM/student_number | Core | mapping, display, dokumen | KP `students.nim` legacy jadi fallback/snapshot |
| nama mahasiswa | Core | display/snapshot | jangan diedit bebas di KP saat Core mode |
| email mahasiswa | Core | display/contact | fallback KP bila legacy |
| prodi | Core | display/snapshot | KP menyimpan snapshot pendaftaran bila dibutuhkan |
| semester/kelas | Core jika tersedia, KP fallback | eligibility/display | tentukan setelah Core field lengkap |
| kontak/alamat | Core profile jika tersedia | komunikasi | bila belum lengkap, KP lokal boleh jadi operational contact sementara |

## Dosen/Pembimbing Dalam/Penguji
| Field | Authority | KP Usage | Catatan |
|---|---|---|---|
| NIDN/NIP/lecturer_number | Core | mapping dan dokumen | KP legacy fallback |
| nama dosen | Core | display/snapshot dokumen | read-only saat Core mode |
| fakultas/prodi/department | Core | display/report | KP fallback legacy; urutan Core adalah fakultas > program studi > department |
| expertise | Core jika tersedia, KP fallback | optional display | bukan blocker KP |
| role pembimbing/penguji | Core app access + KP role lokal | authorization | role translator canonical |

## Preseptor
| Field | Authority | KP sementara | Catatan |
|---|---|---|---|
| user identity | Core user/app access bila tersedia | mapping `field_supervisors.core_user_id` | Core belum terlihat punya external supervisor profile khusus |
| institusi | KP | assignment/logbook | tetap operasional KP |
| posisi | KP | dokumen/assignment | tetap operasional KP |
| kontak/alamat | KP | komunikasi | jangan expose publik |

## Data Operasional KP Tetap di KP
- Periode KP.
- Tempat KP dan kuota.
- Persyaratan dokumen KP.
- Pendaftaran KP dan status verifikasi.
- File berkas KP.
- Pemilihan tempat dan waiting list.
- Assignment/penempatan.
- Logbook dan bukti kegiatan.
- Laporan akhir dan versi file.
- Pengajuan/jadwal sidang.
- Nilai, komponen nilai, final score.
- Audit logs KP.

## Snapshot/Reference
KP boleh menyimpan snapshot untuk:
- dokumen yang sudah diverifikasi/diarsipkan;
- assignment yang sudah berjalan;
- surat/dokumen TU;
- laporan akhir/final score;
- rekap historis.

Snapshot harus diberi konteks waktu dan tidak menjadi master identity baru.

## UI Policy Saat Core Mode
- Field identity utama ditampilkan read-only.
- Link profile portal Core boleh ditampilkan sebagai browser link biasa tanpa token.
- Field operasional KP tetap dapat diedit sesuai role dan workflow.
- Import user KP harus dibatasi untuk fallback/mapping exception, bukan master import utama.

