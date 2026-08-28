# KP-Core Integration Gap Matrix

Tanggal: 2026-06-01

## Guardrails
- KP tidak boleh menulis ke database Core.
- Core tetap sumber master untuk identity, role/app access, mahasiswa, dosen, prodi, dan profil terpusat.
- KP tetap wajib punya authorization lokal dan session `active_role`.
- Tidak ada SSO, auto-login, signed login URL, token URL, atau password/hash/token di report/log.
- Mode production cutover harus feature-flagged dan bisa fallback.

## Kondisi Saat Ini
KP sudah memiliki:
- `KP_AUTH_MODE`: `legacy`, `core_bridge`, `core_bridge_with_legacy_fallback`.
- `KP_MASTER_DATA_READ_MODE`: `legacy`, `core_preferred`, `core_only`.
- `CoreBridgeAuthService`, `CoreIdentityService`, `CoreFarmasiClient`, dan model Core read-only.
- Mapping lokal: `users.core_user_id`, `students.core_student_id`, `lecturers.core_lecturer_id`, `field_supervisors.core_user_id`.
- Command read-only/preflight: `kp:core-health-check`, `kp:core-smoke-test`, `kp:auth-bridge-check`, `kp:auth-bridge-smoke-test`, `kp:master-data-read-check`, `kp:display-adapter-check`, `kp:core-mode-preflight`.
- Command mapping lokal `kp:sync-core-mapping` yang hanya menulis kolom mapping KP saat `--execute --confirm-execute`.

Core sudah menyediakan:
- Internal API read-only untuk directory users/students/lecturers/employees/study-programs/departments.
- App access endpoint `GET /api/v1/internal/apps/{app_code}/users/{user}/access`.
- App-client credentials dengan ability, audit log, dan rate limit.
- Profile portal dan centralized profile planning.

## Matrix
| Area | Sumber Kebenaran Target | KP Saat Ini | Gap | Risiko | Rekomendasi KP-15 |
|---|---|---|---|---|---|
| User identity | Core `users` | KP masih memiliki `users` lokal untuk auth/session dan mapping `core_user_id` | Belum ada coverage report mapping per role untuk data real UAT | Login ganda, status aktif tidak seragam, duplikasi email | Buat mapping coverage report per email/role; pastikan inactive Core dan inactive KP sama-sama diuji |
| Role/app access | Core roles + `user_app_accesses` app `kp-farmasi` | KP memakai role lokal snake_case dan Core app role kebab-case | Normalisasi role belum terdokumentasi sebagai contract formal | Salah mapping `admin-kp` vs `admin-core`, pembimbing tidak punya akses KP | Buat role translation contract: `admin-kp -> admin`, `koordinator-kp -> koordinator_kp`, `pembimbing-dalam -> pembimbing_dalam`, `pembimbing-lapangan -> pembimbing_lapangan` |
| Mahasiswa | Core `students` | KP `students` lokal masih menyimpan NIM, prodi, semester, kelas, kontak, mapping `core_student_id` | Belum ada keputusan field mana authoritative dan mana snapshot KP | Data mahasiswa berubah di Core tetapi KP memakai data lama untuk dokumen aktif | Jadikan Core authoritative untuk display; KP menyimpan snapshot transaksi per pendaftaran/assignment bila diperlukan |
| Dosen/pembimbing | Core `lecturers` | KP `lecturers` lokal menyimpan NIDN/NIP, prodi, departemen, expertise, mapping `core_lecturer_id` | Pembimbing lapangan belum punya master Core khusus selain `core_user_id` | Pembimbing Dalam/Penguji bisa dobel antara KP dan Core; preseptor berada di batas Core/KP | Tetapkan pembimbing dalam/penguji dari Core lecturer; preseptor tetap profil KP sampai ada Core external supervisor model |
| Profil terpusat | Core profile portal | KP punya profil lokal dan avatar lokal | Belum ada aturan field edit di KP saat Core mode aktif | User mengubah data di KP yang seharusnya berubah di Core | Buat UI policy: field identity locked saat Core mode, tampilkan link profile portal Core tanpa token |
| Mode read-only | Core DB/API read-only | Model Core `ReadOnlyCoreModel`, service display adapter, HTTP client disabled by default | Belum ada matrix kapan pakai DB direct vs HTTP API | Direct DB coupling sulit deploy lintas server | KP-15 memilih satu jalur staging utama: HTTP API untuk server terpisah, DB read-only hanya lokal/staging terbatas |
| Preflight command | KP command suite | Preflight sudah menghitung mapping, app access, sample display, read-only count | Belum mencakup TU/SAFA readiness | Go/no-go Core hanya melihat KP-Core, belum workspace | Tambahkan report gabungan dari `kp:integration-gap-check` sebagai preflight KP-15 |
| Fallback mode | `legacy` dan `core_preferred` | `core_preferred` fallback ke legacy; `core_only` fail controlled | Belum ada policy kapan fallback boleh di production | Silent stale data jika Core unavailable | Production awal gunakan `core_preferred` hanya dengan alert; `core_only` setelah mapping coverage dan uptime Core jelas |
| Duplikasi data lokal | Core master, KP snapshot transaksi | KP masih menyimpan profil lokal lengkap | Belum ada lifecycle deprecate/lock field | Conflict saat import user KP masih dipakai | Freeze import master baru saat Core mode aktif; import KP hanya mapping/profile exception |

## Readiness Criteria Sebelum Core Cutover
- Mapping user/student/lecturer minimal 100% untuk akun UAT aktif.
- Semua role KP punya app access aktif di Core.
- `admin@sikp.test` atau admin produksi memiliki `admin-kp`, bukan `admin-core` saja.
- `kp:core-mode-preflight --auth-mode=core_bridge --master-data-mode=core_preferred` PASS di staging.
- Download/upload file KP tetap lewat route KP, bukan Core.
- Profile portal link hanya browser link biasa tanpa token.
- Fallback behavior disepakati: fail closed untuk auth, fallback legacy hanya bila mode explicitly mengizinkan.


