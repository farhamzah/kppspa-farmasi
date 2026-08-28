# PROMPT TAHAP 01 - Setup dan Login Multi Role

Tahap 1 membangun fondasi awal aplikasi SI-KP Farmasi UBP, yaitu Sistem Informasi Kerja Praktek Farmasi UBP.

Target utama:
- Membuat project Laravel di folder `si-kp-farmasi-ubp`.
- Mengatur `APP_NAME` menjadi `SI-KP Farmasi UBP`.
- Menggunakan database default `sikp_farmasi_ubp`.
- Membuat autentikasi login.
- Membuat multi-role user dengan role mahasiswa, admin, koordinator KP, pembimbing dalam, preseptor, dan penguji.
- Membuat alur login single-role langsung ke dashboard dan multi-role ke halaman pilih akses.
- Menyimpan role aktif pada session `active_role`.
- Membuat middleware status user, pemilihan role, dan pengecekan role.
- Membuat dashboard awal per role dengan placeholder fitur.
- Membuat halaman Profil Saya.
- Membuat UI awal yang modern, rapi, responsive, dan berbahasa Indonesia.
- Membuat struktur dokumentasi `docs/reports`, `docs/prompts`, dan `docs/specs`.
- Membuat `AGENTS.md` sebagai instruksi permanen untuk tahap berikutnya.

Batasan tahap ini:
- Belum membuat import Excel.
- Belum membuat pendaftaran KP.
- Belum membuat upload berkas.
- Belum membuat pemilihan tempat KP, kuota, daftar tunggu, logbook, laporan akhir, sidang, dan penilaian.
- Modul tersebut hanya ditampilkan sebagai card placeholder "Segera tersedia".

