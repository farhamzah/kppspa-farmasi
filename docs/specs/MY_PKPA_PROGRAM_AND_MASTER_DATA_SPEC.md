# MY PKPA Program and Master Data Spec

## Scope Tahap 01

Tahap 01 membangun fondasi master data untuk Program PKPA, enam wahana, pilihan Pemerintahan, konfigurasi durasi per program, tempat praktik, authorization, audit, dan UI administratif.

Tahap ini belum membangun kepesertaan, rotasi, penempatan mahasiswa, kapasitas per tanggal, pembimbing, logbook rotasi, laporan rotasi, nilai rotasi, migrasi data legacy, atau algoritma penjadwalan.

## Database

### `pkpa_programs`

Menyimpan penyelenggaraan Program PKPA. `code` unik, status string tervalidasi aplikasi (`draft`, `ready`, `active`, `completed`, `archived`), soft delete aktif, dan actor Core dicatat pada kolom `created_by_core_user_id`, `updated_by_core_user_id`, `activated_by_core_user_id`.

### `pkpa_practice_domains`

Master wahana PKPA. Enam data sistem dibuat idempotent: `APT`, `PKM`, `PBF`, `RS`, `IND`, `PEM`. `code` unik, soft delete aktif, `is_system` melindungi master default dari hapus UI.

### `pkpa_practice_domain_options`

Pilihan/subjenis per wahana. Pemerintahan memiliki `LOKAPOM` dan `DINKES`. Unique composite `practice_domain_id + code` menjaga code unik per wahana.

### `pkpa_program_domains`

Konfigurasi wahana pada sebuah program. Kombinasi `pkpa_program_id + practice_domain_id` unik. Durasi disimpan di sini, bukan di master wahana. Satuan minimal: `calendar_days`, `working_days`, `weeks`, `months`, `practice_hours`.

### `pkpa_practice_sites`

Tempat praktik aktual. `practice_domain_option_id` nullable. Jika domain Pemerintahan, option wajib dan harus berasal dari domain Pemerintahan. `code` unik dan soft delete aktif.

### `pkpa_master_audits`

Audit ringan untuk aksi master. Menyimpan actor local user ID, actor Core user ID, action, entity, old values, dan new values yang sudah difilter dari field sensitif.

## Seeder

`PkpaMasterSeeder` hanya membuat master sistem yang disetujui:

- Apotek
- Puskesmas
- Pedagang Besar Farmasi
- Rumah Sakit
- Industri
- Pemerintahan
- Loka POM
- Dinas Kesehatan

Seeder idempotent, memakai `code` sebagai identitas stabil, tidak membuat tempat praktik palsu, dan tidak mengisi durasi resmi.

## Program Flow

1. Admin atau Koordinator membuat program.
2. Sistem otomatis membuat enam konfigurasi wahana default.
3. Durasi dibiarkan kosong selama draft.
4. Admin/Koordinator mengisi durasi per wahana di halaman konfigurasi.
5. Tombol Periksa Kesiapan menampilkan checklist.
6. Status `ready` atau `active` hanya diterima jika checklist valid.

Checklist readiness:

- enam wahana default aktif tersedia;
- enam konfigurasi program tersedia;
- durasi seluruh wahana wajib valid;
- Pemerintahan memiliki minimal dua option aktif;
- tanggal program valid;
- tidak ada konfigurasi duplikat.

## Authorization

Route master dilindungi `auth`, `active`, `role.selected`, dan `role:admin,koordinator_kp`. Mahasiswa dan role lain menerima 403. Active role tetap diverifikasi terhadap role user, sehingga role dari browser/session saja tidak cukup.

## UI

Menu untuk Admin dan Koordinator:

- Program PKPA
- Wahana PKPA
- Tempat Praktik

Halaman program mencakup daftar, tambah, detail, edit, konfigurasi durasi, dan checklist readiness. Halaman wahana mencakup builder domain dan option. Halaman tempat praktik mencakup daftar, tambah, detail, edit, filter domain, status, aktif, dan status kerja sama.

## Kompatibilitas Legacy

Tabel `kp_*` lama dipertahankan dan belum dimigrasikan. Mapping awal hanya terdokumentasi:

- `kp_periods` -> `pkpa_programs`
- `kp_places` -> `pkpa_practice_sites`
- `kp_places.type` -> `pkpa_practice_domains`
