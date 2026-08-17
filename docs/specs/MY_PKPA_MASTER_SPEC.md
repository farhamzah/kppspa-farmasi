# MY PKPA Master Spec

## Identitas

- Nama aplikasi: MY PKPA
- Nama lengkap: MY PKPA - Sistem Informasi Praktik Kerja Profesi Apoteker
- Kegiatan praktik: Praktik Kerja Profesi Apoteker (PKPA)
- Bahasa antarmuka: Bahasa Indonesia
- Stack: Laravel 12, Blade, Tailwind CSS, Vite, MySQL/MariaDB, PHPUnit

## Domain MY PKPA

MY PKPA mengelola program PKPA, kepesertaan mahasiswa, enam wahana, tempat praktik, rotasi, penempatan, jadwal, durasi, kelompok, pembimbing, logbook, kompetensi, laporan, tugas, ujian, penilaian, monitoring, rekap, dokumen PKPA, pengumuman, dan audit aktivitas bisnis.

## Wahana Default

1. Apotek
2. Puskesmas
3. Pedagang Besar Farmasi atau PBF
4. Rumah Sakit
5. Industri
6. Pemerintahan: Loka POM atau Dinas Kesehatan

## Prinsip Akun

Core Farmasi menjadi sumber akun, password, status akun, dan role. MY PKPA hanya menyimpan projection lokal minimal. Email tidak boleh menjadi foreign key domain.

## Tahap 00

Tahap 00 menetapkan baseline, rebranding, landing page, adapter Core, guardrail akun lokal, guardrail war, dan dokumen target. Tahap ini belum mengimplementasikan seluruh sistem enam rotasi.

## Tahap 01

Tahap 01 membangun fondasi master data PKPA secara paralel terhadap tabel `kp_*` legacy.

Tabel yang aktif pada tahap ini:

- `pkpa_programs`: penyelenggaraan Program PKPA per angkatan atau tahun akademik.
- `pkpa_practice_domains`: master wahana PKPA.
- `pkpa_practice_domain_options`: pilihan/subjenis wahana, termasuk Loka POM dan Dinas Kesehatan untuk Pemerintahan.
- `pkpa_program_domains`: konfigurasi wahana pada program dan durasi per wahana.
- `pkpa_practice_sites`: tempat praktik aktual.
- `pkpa_master_audits`: audit aktivitas master program, wahana, option, dan tempat praktik.

Durasi resmi tidak di-hardcode. Program baru otomatis memiliki enam konfigurasi wahana default dengan durasi kosong, lalu hanya dapat menjadi `ready` atau `active` setelah checklist kesiapan valid.

Tahap 01 belum membangun kepesertaan mahasiswa, kelompok rotasi, penempatan, kapasitas per tanggal, pembimbing, logbook rotasi, laporan rotasi, nilai rotasi, migrasi data legacy, atau algoritma penjadwalan.

## Tahap 02

Tahap 02 membangun fondasi kepesertaan mahasiswa dan kelompok PKPA.

Tabel yang aktif pada tahap ini:

- `pkpa_enrollments`: kepesertaan mahasiswa pada program, keyed by `core_user_id` dari Core Farmasi.
- `pkpa_enrollment_requirements`: kewajiban wahana per peserta, dibuat otomatis dari `pkpa_program_domains`.
- `pkpa_student_groups`: kelompok mahasiswa per program.
- `pkpa_student_group_members`: histori anggota kelompok.
- `pkpa_enrollment_import_batches`: batch preview/final import peserta.
- `pkpa_enrollment_import_rows`: hasil validasi per row import.

Peserta baru berstatus `active` setelah lolos validasi Core: user ditemukan, akun aktif, role mahasiswa, app access MY PKPA, dan belum terdaftar di program yang sama. Status Core, NPM, nama, email, program studi, angkatan, dan status akademik disimpan sebagai snapshot.

Program standar menghasilkan enam requirement. Lima wahana langsung memakai `direct`. Pemerintahan memakai satu requirement `choose_one` dengan `selected_practice_domain_option_id = null`; Loka POM dan Dinas Kesehatan tidak menjadi dua kewajiban terpisah.

Tahap 02 belum membangun penempatan tempat, tanggal rotasi, kapasitas per tempat, pembimbing, jadwal, logbook rotasi, nilai rotasi, algoritma penjadwalan, atau migrasi data legacy.

## Tahap 03

Tahap 03 membangun fondasi kapasitas tempat, pembimbing, dan readiness penempatan.

Tabel yang aktif pada tahap ini:

- `pkpa_program_sites`: tempat praktik yang tersedia pada program tertentu.
- `pkpa_site_availability_periods`: periode ketersediaan tempat, kapasitas, reserved slot, hari operasional, dan jam praktik.
- `pkpa_site_field_supervisors`: Pembimbing Lapangan dari Core Farmasi yang terhubung ke tempat praktik.
- `pkpa_internal_supervisor_eligibilities`: eligibility Pembimbing Dalam dari Core Farmasi per program-wahana.
- `pkpa_supervisor_unavailability_periods`: periode pembimbing tidak tersedia.
- `pkpa_supervisor_sync_logs`: log sinkronisasi snapshot pembimbing dari Core.

Readiness penempatan memeriksa enam wahana aktif, tempat aktif, availability, kapasitas, pembimbing, pilihan Pemerintahan, peserta, dan kelompok. Tahap 03 belum membuat penempatan mahasiswa, matrix rotasi, tanggal rotasi individual, assignment supervisor ke peserta, logbook rotasi, nilai rotasi, atau migrasi legacy.

## Tahap 04

Tahap 04 membangun penyusunan penempatan draft/tervalidasi.

Tabel yang aktif pada tahap ini:

- `pkpa_placement_plans`: versi rancangan per program, termasuk current plan, status, dan ringkasan validasi.
- `pkpa_rotation_assignments`: penempatan satu requirement mahasiswa dalam satu plan.
- `pkpa_rotation_assignment_supervisors`: Pembimbing Dalam/Lapangan yang melekat pada assignment.
- `pkpa_placement_action_batches` dan `pkpa_placement_action_batch_items`: preview, apply, dan undo aksi massal.
- `pkpa_placement_validation_runs` dan `pkpa_placement_validation_issues`: hasil validasi rancangan dan issue yang dapat diklik dari UI.

Tahap 04 belum mempublikasikan jadwal, belum mengirim notifikasi, belum membuat surat, dan belum menghubungkan logbook/laporan/nilai rotasi.

## Tahap 05

Tahap 05 membangun publication snapshot dan portal jadwal resmi.

Tabel yang aktif pada tahap ini:

- `pkpa_placement_publications`: header publication, revision, current flag, publish/withdraw metadata.
- `pkpa_published_assignments`: snapshot resmi assignment mahasiswa.
- `pkpa_published_assignment_supervisors`: snapshot resmi PD/PL.
- `pkpa_schedule_acknowledgements`: tanda viewed/acknowledged dari mahasiswa dan pembimbing.
- `pkpa_placement_change_requests` dan item: workflow revisi publication.
- `pkpa_notification_deliveries`: delivery notification database/email.

Tahap 05 belum membuat logbook rotasi, presensi, nilai, surat, WhatsApp, atau migrasi legacy `kp_*`.
# Catatan Tahap 07

Master kompetensi, tugas khusus, dan template laporan rotasi dibuat per program-wahana, bukan pada master wahana global. Seeder production tidak mengisi daftar kompetensi atau tugas resmi palsu.
## Tahap 10 - Dokumen dan Readiness

Master PKPA kini menjadi sumber snapshot untuk Dokumen Internal MY PKPA. Perubahan master setelah dokumen published tidak boleh mengubah file lama. Jenis dokumen sistem disiapkan oleh `PkpaMasterSeeder`, sementara isi template, aturan nomor, dan penandatangan wajib dikonfigurasi sebelum digunakan dalam proses resmi internal.
