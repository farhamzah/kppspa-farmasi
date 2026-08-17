# MY PKPA Enrollment and Group Spec

## Scope

Tahap 02 mengelola siapa peserta Program PKPA, kewajiban enam wahana, kelompok mahasiswa, histori membership, import peserta, dan sinkronisasi snapshot Core.

Tahap ini tidak mengelola penempatan tempat praktik, tanggal rotasi, kapasitas tempat, pembimbing, jadwal, logbook rotasi, kompetensi rotasi, laporan rotasi, nilai rotasi, atau migrasi data `kp_*`. Kapasitas tempat dan pembimbing mulai dikelola pada Tahap 03 sebagai fondasi readiness, tetap tanpa membuat penempatan.

## Tabel

- `pkpa_enrollments`: kepesertaan mahasiswa pada satu Program PKPA.
- `pkpa_enrollment_requirements`: kewajiban wahana per peserta.
- `pkpa_student_groups`: kelompok mahasiswa per program.
- `pkpa_student_group_members`: histori anggota kelompok.
- `pkpa_enrollment_import_batches`: batch upload dan validasi import.
- `pkpa_enrollment_import_rows`: hasil validasi per row.

## Identitas Core

`core_user_id` adalah identitas stabil. Email, nama, NPM, program studi, angkatan, status akademik, dan status akun Core disimpan sebagai snapshot untuk tampilan dan laporan.

Sistem tidak membuat akun lokal, tidak menyimpan password, tidak membuat role, dan tidak memakai email sebagai foreign key.

## Status Enrollment

Status tersedia:

- `draft`
- `active`
- `on_hold`
- `cancelled`
- `completed`
- `archived`

Tahap 02 membuat peserta baru sebagai `active` bila validasi Core berhasil. Pembatalan memakai status `cancelled`, alasan wajib, dan audit trail.

## Requirement Wahana

Saat enrollment dibuat, service membuat requirement dari `pkpa_program_domains` aktif dalam transaksi yang sama.

Program standar menghasilkan enam requirement:

- Apotek: `direct`
- Puskesmas: `direct`
- PBF: `direct`
- Rumah Sakit: `direct`
- Industri: `direct`
- Pemerintahan: `choose_one`, `required_option_count = 1`, selected option awal `null`

Loka POM dan Dinas Kesehatan adalah pilihan Pemerintahan untuk tahap penempatan, bukan requirement terpisah.

## Kelompok

Kelompok disimpan per program dengan kode bebas, nama, kapasitas opsional, status, dan flag aktif.

Membership:

- satu peserta hanya boleh memiliki satu membership aktif;
- perpindahan menutup membership lama dan membuat histori baru;
- peserta dan kelompok harus berasal dari program yang sama;
- kapasitas maksimum wajib dihormati;
- peserta boleh belum berkelompok.

## Audit

Audit dicatat pada `pkpa_master_audits` untuk enrollment, requirement, kelompok, membership, import, dan sync Core. Token, password, secret, dan payload sensitif tidak dicatat.

## Authorization

Route manajemen dilindungi `auth`, `active`, `role.selected`, dan `role:admin,koordinator_kp`.

Mahasiswa tidak dapat menambah peserta, import, membuat kelompok, atau memindahkan anggota. Dashboard mahasiswa hanya menampilkan status kepesertaan bila ada.

## Hubungan ke Tahap 03

Readiness penempatan memakai jumlah peserta aktif/on-hold dan peserta yang belum berkelompok sebagai indikator. Modul kapasitas dan pembimbing tidak mengubah enrollment, membership, atau requirement peserta. Jika Core tidak tersedia saat sync pembimbing, snapshot peserta tetap tidak disentuh.
