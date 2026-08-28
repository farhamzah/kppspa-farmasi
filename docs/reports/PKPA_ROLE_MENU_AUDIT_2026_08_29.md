# Audit Menu Per Role PKPA

Tanggal audit: 29 Agustus 2026
Basis audit:
- Panduan PKPA 2026
- Portofolio PKPA Apotek
- Struktur route dan sidebar aplikasi lokal `kppspa-farmasi`

## 1. Tujuan Audit

Audit ini dibuat untuk memeriksa apakah menu per role di aplikasi sudah sejalan dengan proses PKPA yang diinginkan:

- Program PKPA berjalan 1 tahun.
- Ada 5 wahana utama, dengan Pemerintahan memiliki 3 sub wahana.
- Penempatan mahasiswa ditentukan koordinator/admin, bukan war ticket mahasiswa.
- Plotting dapat bertahap per wahana dan per periode.
- Setelah assignment dipublikasikan atau dikunci, mahasiswa, Pembimbing Dalam, dan Preseptor dapat langsung melihat jadwalnya.
- Portofolio dan logbook mengikuti konteks wahana, dimulai dari Apotek.

## 2. Sumber Teknis yang Dicek

- `app/Support/RoleDashboard.php`
- `resources/views/layouts/app.blade.php`
- `routes/web.php`
- `docs/specs/MY_PKPA_MASTER_SPEC.md`
- `docs/specs/MY_PKPA_LOGBOOK_WORKFLOW_SPEC.md`
- `docs/specs/SPESIFIKASI_AWAL_APLIKASI.md`

## 3. Ringkasan Temuan Utama

Secara umum, struktur PKPA baru sudah ada dan cukup kuat. Namun aplikasi masih membawa dua lapis sekaligus:

1. lapis PKPA baru yang sudah sesuai arah,
2. lapis warisan KP lama yang masih muncul dalam nama menu, route alias, spesifikasi awal, dan beberapa istilah operasional.

Masalah utamanya bukan hanya label, tetapi juga urutan mental model pengguna. Saat ini beberapa role masih melihat campuran:

- menu master PKPA baru,
- menu alias KP lama,
- menu evaluasi lama,
- menu yang sebetulnya sudah bukan alur utama PKPA 2026.

## 4. Temuan Per Role

### 4.1 Mahasiswa

Menu saat ini:
- Dashboard
- Profil Saya
- Pendaftaran PKPA
- Berkas PKPA
- Pembekalan
- PKPA Saya
- Penempatan PKPA
- Rotasi PKPA
- Logbook PKPA
- Akademik Rotasi
- Laporan Akhir
- Portofolio PKPA
- Ujian
- Nilai PKPA
- Hasil Akhir PKPA
- Dokumen PKPA

Penilaian:
- Sudah ada jalur PKPA inti yang benar: `PKPA Saya`, `Rotasi PKPA`, `Portofolio PKPA`, `Nilai PKPA`, `Hasil Akhir PKPA`.
- Untuk model PKPA 2026, menu mahasiswa sebaiknya berpusat pada status penempatan, rotasi aktif, logbook, portofolio, dan hasil.
- `Pendaftaran PKPA`, `Berkas PKPA`, dan `Pembekalan` masih masuk akal, tetapi bukan pusat pengalaman setelah mahasiswa ditarik dan diplot oleh koordinator.
- `Laporan Akhir` terdengar seperti pola KP lama yang satu laporan besar. Untuk PKPA, istilah yang lebih kuat adalah laporan/artefak per rotasi atau per wahana bila memang masih dipakai.

Kesimpulan:
- Menu mahasiswa paling dekat dengan target, tetapi masih perlu penyederhanaan urutan dan penamaan agar fokus ke alur rotasi PKPA.

### 4.2 Admin dan Koordinator PKPA

Menu saat ini sangat panjang dan mencampur beberapa lapis:
- Master PKPA
- Penjadwalan
- Peserta & Administrasi
- Penempatan
- Pelaksanaan
- Akademik & Portofolio
- Evaluasi
- Pelaporan & Akhir

Penilaian:
- Secara cakupan, role ini paling lengkap dan sesuai kebutuhan admin/koordinator.
- Masalahnya ada pada kepadatan dan campuran sumber modul.
- `Kapasitas Tempat` dan `Log Kapasitas` masih dipetakan ke route legacy `kp-place-quotas` dan `kp-quota-logs`.
- Menu `Preseptor` masih diarahkan ke halaman `Tempat Tersedia`, belum ke modul khusus daftar preseptor.
- Ada bagian evaluasi yang masih terasa dari pola KP umum, padahal kebutuhan PKPA lebih spesifik: penilaian per wahana, nilai akhir program, dan publikasi hasil.

Kesimpulan:
- Role admin/koordinator secara fungsi sudah paling siap, tetapi perlu dirapikan menjadi alur kerja, bukan daftar panjang semua halaman.

### 4.3 Pembimbing Dalam

Menu saat ini:
- Dashboard
- Profil Saya
- Jadwal PKPA
- Mahasiswa Bimbingan
- Pemantauan PKPA
- Logbook Mahasiswa
- Akademik PKPA
- Capaian Kompetensi
- Pemeriksaan Laporan
- Review Portofolio
- Jadwal Sidang
- Penilaian Pembimbing
- Penilaian PKPA

Penilaian:
- Jalur inti sudah benar: melihat jadwal, mahasiswa bimbingan, monitoring, akademik, portofolio, dan penilaian.
- Label role masih `Pembimbing Dalam / Dosen`, padahal di PKPA sebaiknya cukup `Pembimbing Dalam`.
- Ada potensi tumpang tindih antara `Penilaian Pembimbing` dan `Penilaian PKPA`.
- `Jadwal Sidang` mungkin tetap dibutuhkan, tetapi posisinya nanti perlu dipastikan dari panduan PKPA final, apakah menjadi fase utama atau fase akhir opsional.

Kesimpulan:
- Role ini hampir sesuai, tetapi perlu konsolidasi istilah dan mengurangi duplikasi fungsi penilaian.

### 4.4 Preseptor

Menu saat ini:
- Dashboard
- Profil Saya
- Jadwal PKPA
- Mahasiswa PKPA
- Operasional PKPA
- Validasi Logbook
- Akademik PKPA
- Daftar Kompetensi
- Pemeriksaan Laporan
- Review Portofolio
- Penilaian Lapangan
- Penilaian PKPA

Penilaian:
- Struktur fungsi sudah sesuai peran lapangan: jadwal, mahasiswa, operasional, logbook, akademik, portofolio.
- Label `Penilaian Lapangan` masih belum seragam dengan istilah `Preseptor`.
- Beberapa alias route dan controller masih memakai nama `field-supervisor` dan `pembimbing-lapangan`; ini aman untuk teknis, tetapi jangan dibiarkan merembes ke UI/dokumen utama.
- Dari sisi mental model, role ini paling penting dibersihkan karena akan sering dipakai mitra eksternal.

Kesimpulan:
- Role preseptor sudah cukup pas secara fungsi, tetapi masih butuh penyamaan bahasa total agar tidak ada dua identitas dalam satu portal.

### 4.5 Penguji

Menu saat ini:
- Dashboard
- Profil Saya
- Jadwal Sidang
- Penilaian Sidang

Penilaian:
- Menu ini masih sederhana dan konsisten.
- Belum ada masalah besar pada role ini.

## 5. Temuan Lintas Sistem

### 5.1 Sidebar sudah memakai grouping PKPA, tetapi route map masih campuran

`resources/views/layouts/app.blade.php` sudah mengelompokkan menu ke:
- Awal
- Master PKPA
- Penjadwalan
- Peserta & Administrasi
- Penempatan
- Pelaksanaan
- Akademik & Portofolio
- Evaluasi
- Pelaporan & Akhir

Ini bagus sebagai fondasi. Namun route map di file yang sama masih memuat:
- alias `KP`,
- alias `Mahasiswa KP`,
- alias `Logbook KP`,
- halaman lama seperti `Penilaian Lapangan`.

Artinya fondasi tampilannya sudah PKPA, tetapi pemetaan teknis masih menahan istilah lama.

### 5.2 Route masih menyimpan alias kompatibilitas lama

Di `routes/web.php` masih ada banyak route alias lama seperti:
- `mahasiswa-kp`
- `logbook`
- `jurnal-pkpa`
- `laporan-akhir`
- `penilaian`

Ini tidak salah, bahkan berguna untuk kompatibilitas dan transisi. Namun secara produk:
- alias lama sebaiknya dianggap internal/legacy,
- navigasi aktif sebaiknya diarahkan ke naming PKPA yang konsisten.

### 5.3 Spesifikasi awal masih sangat dipengaruhi model KP lama

`docs/specs/SPESIFIKASI_AWAL_APLIKASI.md` masih kuat memuat:
- war ticket,
- kuota tempat KP sebagai inti,
- pemilihan tempat oleh mahasiswa,
- narasi KP umum,
- laporan akhir KP tunggal.

Ini bertentangan dengan model PKPA saat ini yang sudah disepakati:
- mahasiswa ditarik dari Core,
- penempatan ditentukan koordinator/admin,
- plotting bertahap per wahana,
- portofolio dan logbook per rotasi/wahana.

Dokumen ini masih berguna sebagai sejarah awal, tetapi tidak boleh lagi menjadi acuan produk utama.

## 6. Target Struktur Menu yang Direkomendasikan

### 6.1 Mahasiswa

Urutan target yang lebih pas:
- Dashboard
- Profil Saya
- Pembekalan
- PKPA Saya
- Jadwal/Rotasi PKPA
- Logbook PKPA
- Portofolio PKPA
- Akademik PKPA
- Nilai PKPA
- Hasil Akhir PKPA
- Dokumen PKPA

Menu opsional:
- Pendaftaran PKPA
- Berkas PKPA
- Ujian

### 6.2 Admin dan Koordinator

Urutan kerja yang lebih natural:
- Dashboard
- Profil Saya
- Program PKPA
- Wahana PKPA
- Tempat Praktik
- Tempat Tersedia
- Kapasitas Tempat
- Peserta PKPA
- Kelompok PKPA
- Pembimbing Dalam
- Preseptor
- Kesiapan Penempatan
- Penyusunan Penempatan
- Publikasi Penempatan
- Operasional Rotasi
- Akademik Rotasi
- Portofolio PKPA
- Penilaian PKPA
- Penyelesaian PKPA
- Dokumen PKPA
- Rekap PKPA
- Pelaporan Analitik

Menu yang perlu ditinjau ulang:
- Log Kapasitas
- Log Penempatan
- Log Aktivitas Logbook
- Log Laporan
- Log Ujian
- Log Nilai
- Pemeriksaan Integrasi

Menu-menu log tetap berguna, tetapi lebih cocok sebagai submenu utilitas atau area admin lanjutan, bukan menu utama harian.

### 6.3 Pembimbing Dalam

Urutan target:
- Dashboard
- Profil Saya
- Jadwal PKPA
- Mahasiswa Bimbingan
- Pemantauan PKPA
- Logbook Mahasiswa
- Akademik PKPA
- Review Portofolio
- Penilaian PKPA
- Jadwal Ujian

Menu yang perlu digabung/dirapikan:
- `Penilaian Pembimbing` dan `Penilaian PKPA`
- `Capaian Kompetensi` bila isinya sebenarnya bagian dari `Akademik PKPA`

### 6.4 Preseptor

Urutan target:
- Dashboard
- Profil Saya
- Jadwal PKPA
- Mahasiswa PKPA
- Operasional PKPA
- Validasi Logbook
- Akademik PKPA
- Review Portofolio
- Penilaian Preseptor

Menu yang perlu diganti label:
- `Penilaian Lapangan` menjadi `Penilaian Preseptor`

## 7. Prioritas Pembenahan

### Prioritas 1 - Penyamaan istilah aktif

Rapikan label aktif di UI dan menu agar konsisten:
- `Pembimbing Lapangan` -> `Preseptor`
- `Penilaian Lapangan` -> `Penilaian Preseptor`
- `Pembimbing Dalam / Dosen` -> `Pembimbing Dalam`
- `Mahasiswa KP` -> `Mahasiswa PKPA`

### Prioritas 2 - Menetapkan dokumen acuan utama

Mulai saat ini acuan produk utama sebaiknya:
- Panduan PKPA 2026
- Portofolio PKPA Apotek
- spesifikasi PKPA baru di `docs/specs/MY_PKPA_*`

Sedangkan `SPESIFIKASI_AWAL_APLIKASI.md` diposisikan sebagai dokumen sejarah transisi, bukan acuan final.

### Prioritas 3 - Rapikan menu utama per role

Kurangi duplikasi dan kepadatan menu:
- gabungkan fungsi yang serupa,
- turunkan halaman log ke area sekunder,
- tampilkan urutan kerja yang mengikuti proses PKPA nyata.

### Prioritas 4 - Pisahkan legacy internal dari produk aktif

Pertahankan route/alias lama bila perlu untuk kompatibilitas, tetapi:
- jangan dipakai sebagai label utama,
- jangan muncul sebagai bahasa panduan,
- jangan memimpin mental model pengguna.

## 8. Rekomendasi Langkah Berikutnya

Langkah paling tepat setelah audit ini:

1. rapikan label menu aktif per role,
2. sederhanakan daftar menu `RoleDashboard`,
3. rapikan grouping dan route map sidebar,
4. audit halaman mahasiswa, Pembimbing Dalam, dan Preseptor berdasarkan alur nyata Apotek lebih dulu,
5. lanjut siapkan portofolio Apotek sebagai modul akademik pertama yang paling nyata dipakai.

## 9. Kesimpulan

Aplikasi sudah berada di jalur PKPA, bukan lagi aplikasi KP biasa. Struktur inti untuk program, wahana, penempatan, publikasi, operasional rotasi, akademik, dan portofolio sudah terbentuk.

Pekerjaan berikutnya bukan membangun ulang dari nol, tetapi membersihkan sisa pola KP lama agar:
- bahasa per role konsisten,
- menu mengikuti alur kerja nyata,
- pengguna tidak melihat dua sistem dalam satu portal,
- dan pengembangan portofolio Apotek bisa menjadi fondasi rapi untuk wahana lain.
