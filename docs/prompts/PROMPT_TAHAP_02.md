# PROMPT TAHAP 02 - Manajemen User, Import Excel, dan Profil Pengguna

Tahap 2 melanjutkan aplikasi SI-KP Farmasi UBP dari fondasi Tahap 1 tanpa mengulang setup login, role, middleware, dashboard, dan layout.

Target utama:
- Membuat modul Manajemen User khusus Admin.
- Admin dapat melihat, mencari, memfilter, membuat, mengedit, menghapus, reset password, dan mengaktifkan/nonaktifkan user.
- Admin dapat memberi satu atau banyak role pada user.
- Membuat tabel profil `students`, `lecturers`, dan `field_supervisors`.
- Membuat halaman Profil Saya dan form edit profil sesuai tipe profil.
- Membuat import user massal dengan `maatwebsite/excel`.
- Membuat template import untuk mahasiswa, dosen, preseptor, dan mixed.
- Membuat preview validasi import dan proses import baris valid.
- Menyimpan riwayat import ke `user_import_batches` dan error per baris ke `user_import_errors`.
- Menambahkan test untuk akses admin, CRUD user, import validation, dan update profil.
- Memperbarui dokumentasi dan spesifikasi aplikasi.

Batasan tahap ini:
- Belum membuat periode KP.
- Belum membuat tempat KP dan kuota.
- Belum membuat pendaftaran KP.
- Belum membuat upload berkas, pemilihan tempat, daftar tunggu, logbook, laporan akhir, sidang, dan penilaian.

