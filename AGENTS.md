# AGENTS.md - MY PSPA

## 1. Nama Project
MY PSPA - Sistem Informasi Program Studi Profesi Apoteker.

## 2. Tujuan Aplikasi
Aplikasi ini dibuat untuk mengelola proses Praktik Kerja Profesi Apoteker (PKPA) mulai dari program PKPA, kepesertaan, wahana, tempat praktik, rotasi, penempatan, pembimbing, logbook, laporan, ujian, penilaian, monitoring, rekap, dokumen, dan pengumuman.

## 3. Prinsip Pengembangan
- Cepat dibuat, mudah digunakan, murah dikembangkan, aman, dan mudah dihosting.
- Utamakan fitur modular, sederhana, dan siap dikembangkan bertahap.
- Ikuti pola Laravel bawaan sebelum menambah abstraksi baru.
- Hindari kompleksitas yang belum dibutuhkan tahap berjalan.

## 4. Stack Teknologi
- Laravel 12 atau versi stabil terbaru yang tersedia di environment.
- Blade, Tailwind CSS, Vite.
- MySQL/MariaDB untuk development dan production.
- PHPUnit untuk feature test.
- Storage lokal untuk tahap awal jika nanti ada file upload.

## 5. Aturan Keamanan
- Semua fitur utama wajib dilindungi autentikasi.
- Role dan izin akses wajib dicek di server-side melalui middleware atau policy.
- Password, status akun, dan role utama berasal dari Core Farmasi.
- User dengan status inactive di Core tidak boleh mengakses sistem.
- Session `active_role` wajib divalidasi terhadap role valid dari Core.
- Jangan hardcode data sensitif atau credential Core.
- Gunakan validasi request dan CSRF protection bawaan Laravel.

## 6. Aturan UI/UX
- Gunakan bahasa Indonesia pada UI.
- Desain harus bersih, profesional, responsive, dan mudah dipahami.
- Warna utama bernuansa teal/biru/hijau yang cocok untuk farmasi dan kampus.
- Sidebar, topbar, card, badge status, dan feedback pesan harus konsisten.
- Placeholder fitur boleh ditampilkan, tetapi beri label yang jelas seperti "Segera tersedia".

## 7. Aturan Penamaan File
- Gunakan nama class, controller, middleware, seeder, dan model sesuai konvensi Laravel.
- Route name harus jelas dan stabil.
- File dokumentasi Markdown menggunakan huruf besar dan underscore untuk laporan tahap.
- Hindari nama file ambigu seperti `new`, `fix`, atau `temp`.

## 8. Aturan Dokumentasi
- Simpan spesifikasi di `docs/specs`.
- Simpan prompt/ringkasan instruksi tahap di `docs/prompts`.
- Simpan laporan pengerjaan tahap di `docs/reports`.
- Dokumentasi harus ringkas, jujur, dan mudah dipakai developer berikutnya.

## 9. Catatan Perubahan Besar
Setiap perubahan besar wajib dicatat di `docs/reports`, termasuk fitur yang dibuat, file penting, cara menjalankan, cara testing, kendala, dan rekomendasi tahap berikutnya.

## 10. Modularitas
Setiap fitur harus dibuat modular dan mudah dikembangkan. Pisahkan tanggung jawab controller, middleware, model, seeder, support class, view, dan test sesuai kebutuhan.

## 11. Aturan Modul User dan Import
- Akun, password, reset password, status akun, dan role assignment utama dikelola Core Farmasi.
- MY PSPA hanya boleh menyimpan local identity reference/projection minimal seperti `core_user_id`, cache nama/email, status lokal teknis, dan waktu sinkron.
- Import lama yang membuat akun/password lokal dinonaktifkan pada Tahap 00; kebutuhan berikutnya harus menjadi import kepesertaan atau referensi pengguna Core.
- Jangan membuat akun lokal tanpa `core_user_id`, kecuali akun teknis development yang terdokumentasi.
- Profil akademik lokal boleh dipertahankan sementara untuk relasi legacy, tetapi identitas kunci harus diarahkan ke Core.

## 12. Aturan Modul KP
- Periode KP, Tempat KP, dan Kuota Tempat KP dikelola oleh Admin dan Koordinator KP.
- Setiap kuota wajib unik berdasarkan kombinasi periode dan tempat.
- Perubahan kuota wajib dicatat di `kp_quota_logs`.
- Helper kuota seperti `filledCount`, `remainingQuota`, dan `isFull` harus tetap kompatibel dengan tahap pemilihan tempat berikutnya.
- Tempat KP yang sudah memiliki kuota sebaiknya dinonaktifkan, bukan dihapus.

## 13. Aturan Pendaftaran dan Berkas KP
- Pendaftaran KP milik mahasiswa wajib dibatasi berdasarkan user login dan profil `students`.
- Mahasiswa hanya boleh mengakses, upload, dan download dokumen pendaftarannya sendiri.
- Admin dan Koordinator KP boleh mengelola persyaratan dokumen serta memverifikasi pendaftaran dan berkas.
- File upload KP wajib divalidasi server-side, disimpan di storage non-public, dan diunduh melalui route protected.
- Status pendaftaran dan dokumen tidak boleh diubah langsung dari request bebas; gunakan controller/form request yang mencatat log ke `kp_registration_logs`.
- Mahasiswa hanya eligible untuk pemilihan tempat jika pendaftaran sudah `terverifikasi` dan semua dokumen wajib disetujui.

## 14. Aturan Penempatan PKPA
- MY PSPA tidak memakai war, first come first served, waiting list aktif, atau pemilihan tempat oleh mahasiswa.
- Penempatan dibuat oleh Koordinator PKPA.
- Kode/tabel legacy pemilihan tempat dipertahankan sementara untuk migrasi, tetapi route aktif harus dikunci dengan feature flag.
- Mahasiswa melihat hasil penempatan setelah dipublikasikan.
- Penempatan berikutnya harus mendukung individual, massal, kelompok, import Excel, validasi konflik, publikasi, undo, dan audit trail.

## 14a. Aturan Master PKPA
- Master Program PKPA, wahana, option, dan tempat praktik menggunakan tabel `pkpa_*` paralel.
- Enam wahana sistem adalah Apotek, Puskesmas, PBF, Rumah Sakit, Industri, dan Pemerintahan.
- Pemerintahan memiliki pilihan sistem Loka POM dan Dinas Kesehatan.
- Wahana dan option sistem tidak boleh dihapus melalui UI/action.
- Program baru otomatis memiliki enam konfigurasi wahana dengan durasi kosong.
- Durasi ditentukan per program-wahana, bukan di master wahana, dan tidak boleh dikarang dalam seeder.
- Program hanya boleh menjadi `ready` atau `active` jika konfigurasi wajib lengkap.
- Tempat Pemerintahan wajib memakai option dari domain Pemerintahan; option domain lain wajib ditolak.
- Aktivitas master wajib dicatat di `pkpa_master_audits` tanpa token, password, secret, atau respons sensitif Core.
- Tabel `kp_*` lama belum dimigrasikan dan tidak boleh dihapus pada tahap master ini.

## 14b. Aturan Kepesertaan PKPA
- Kepesertaan mahasiswa PKPA memakai `pkpa_enrollments` dengan `core_user_id` sebagai identitas stabil dari Core Farmasi.
- Jangan memakai email sebagai foreign key, jangan membuat akun lokal, jangan menyimpan password, dan jangan melakukan assignment role lokal dari import peserta.
- Saat enrollment dibuat, sistem wajib membuat requirement berdasarkan wahana aktif program dalam satu transaksi.
- Program standar menghasilkan enam requirement: Apotek, Puskesmas, PBF, Rumah Sakit, Industri, dan satu Pemerintahan `choose_one`.
- Loka POM dan Dinas Kesehatan adalah pilihan untuk satu requirement Pemerintahan, bukan dua requirement terpisah.
- Kelompok mahasiswa memakai histori `pkpa_student_group_members`; satu peserta hanya boleh memiliki satu membership aktif dalam program yang sama.
- Import peserta wajib preview sebelum final, menolak kolom password/role, dan hanya row valid yang boleh menjadi enrollment.
- Sinkronisasi Core hanya memperbarui snapshot identitas, tidak mengubah `core_user_id`, tidak menghapus enrollment, dan Core unavailable tidak boleh mengosongkan snapshot lama.
- Tahap kepesertaan belum membuat penempatan tempat, tanggal rotasi, pembimbing, jadwal, logbook rotasi, nilai rotasi, atau migrasi legacy `kp_*`.

## 14c. Aturan Kapasitas, Pembimbing, dan Readiness PKPA
- Program-site PKPA memakai `pkpa_program_sites` sebagai penghubung program, tempat praktik, wahana, dan option tempat.
- Availability tempat memakai `pkpa_site_availability_periods` dengan rentang tanggal, kapasitas minimal/maksimal, reserved slot, hari operasional, jam praktik, dan status.
- Pembimbing Lapangan dan Pembimbing Dalam wajib divalidasi dari Core Farmasi memakai `core_user_id`; sistem hanya menyimpan snapshot nama/email/status/role, bukan akun/password/role lokal.
- Pembimbing Lapangan terikat ke `pkpa_practice_sites`; Pembimbing Dalam memiliki eligibility per program dan wahana.
- Ketidaktersediaan pembimbing dicatat di `pkpa_supervisor_unavailability_periods` dan tidak boleh menghapus histori pembimbing.
- Sinkronisasi pembimbing dicatat di `pkpa_supervisor_sync_logs`; Core unavailable tidak boleh mengosongkan snapshot lama.
- Readiness penempatan hanya berupa checklist fondasi: program-site aktif, availability, kapasitas, pembimbing, pilihan Pemerintahan, dan status peserta/kelompok.
- Tahap ini belum membuat penempatan mahasiswa, urutan rotasi, tanggal rotasi individual, matrix jadwal, assignment supervisor ke mahasiswa, logbook rotasi, nilai, migrasi legacy, atau perubahan tabel `kp_*`.

## 14d. Aturan Publikasi Penempatan PKPA
- Publikasi PKPA hanya boleh dibuat dari `pkpa_placement_plans` yang sudah `locked`, tervalidasi, dan tanpa error aktif.
- Koordinator PKPA adalah role final untuk publish, withdrawal, approval, dan apply change request.
- Portal mahasiswa dan pembimbing wajib membaca `pkpa_published_assignments`, bukan `pkpa_rotation_assignments`.
- Salinan published tidak boleh diedit untuk revisi; buat publication revision baru dan ubah publication lama menjadi `superseded`.
- Acknowledgement jadwal adalah tanda membaca, bukan approval mahasiswa atau pembimbing.
- Notifikasi wajib dicatat di `pkpa_notification_deliveries`; failure/skip notifikasi tidak boleh rollback publication.
- Email notification harus dikontrol feature flag dan default aman adalah nonaktif.
- Withdrawal mencabut current publication tanpa menghapus snapshot historis.
- Tahap publikasi tidak boleh membuat logbook, presensi, nilai, surat, WhatsApp, atau migrasi legacy `kp_*`.

## 14e. Aturan Operasional Rotasi PKPA
- Operasional rotasi hanya boleh dibentuk dari `pkpa_published_assignments` pada publikasi resmi current, bukan dari draft planner.
- Runtime rotasi memakai `pkpa_rotation_runs`; operational complete tidak boleh otomatis mengubah requirement akademik menjadi completed atau membuat nilai.
- Aturan operasional per program-wahana disimpan di `pkpa_rotation_operation_rules` dan wajib aktif sebelum rotasi diaktifkan.
- Presensi PKPA memakai `pkpa_attendance_records`; mahasiswa hanya mengisi miliknya sendiri, Pembimbing Lapangan aktif yang memvalidasi, dan Pembimbing Dalam tidak boleh approve presensi.
- Koreksi presensi wajib melalui `pkpa_attendance_correction_requests`, tidak melalui edit bebas setelah approved/rejected.
- Logbook rotasi memakai `pkpa_logbook_entries`, lampiran privat di `pkpa_logbook_attachments`, dan review di `pkpa_logbook_reviews`.
- Lampiran logbook wajib disimpan non-public dan hanya diunduh melalui route protected oleh mahasiswa terkait, Pembimbing Lapangan/Dalam aktif, Admin, atau Koordinator.
- Progress operasional memakai snapshot `pkpa_rotation_progress_snapshots`; perhitungan ini untuk monitoring, bukan nilai akhir.
- Sinkronisasi perubahan publikasi ke runtime memakai `pkpa_rotation_publication_sync_logs`; perubahan pada rotasi aktif yang berdampak tempat/tanggal wajib masuk status perlu review.
- Tahap operasional tidak membuat rubrik penilaian, nilai akhir, sertifikat, QR/GPS/biometrik, WhatsApp, pembayaran, war, atau migrasi tabel legacy `kp_*`.

## 14f. Aturan Akademik Rotasi PKPA
- Akademik rotasi berjalan di atas `pkpa_rotation_runs` dan tidak boleh membaca draft planner sebagai sumber utama.
- Master kompetensi per program-wahana memakai `pkpa_competency_sets`, kategori, dan item; production seeder tidak boleh membuat daftar kompetensi palsu.
- Checklist kompetensi runtime menyimpan snapshot item agar perubahan master tidak mengubah rotasi berjalan.
- Pembimbing Lapangan memverifikasi capaian kompetensi; Pembimbing Dalam hanya monitoring/komentar kecuali workflow lain di tahap berikutnya.
- Tugas khusus memakai template per program-wahana dan submission berversi; versi lama tidak boleh ditimpa.
- Laporan rotasi memakai template, report utama per runtime, dan versioning file private.
- Approval laporan dan readiness akademik berarti siap dinilai, bukan nilai, bukan lulus, dan tidak menyelesaikan requirement.
- Bimbingan rotasi dicatat sebagai histori akademik dan acknowledgement mahasiswa hanya tanda membaca.
- Semua evidence, tugas, dan laporan wajib private storage dan download via controller protected.
- Tahap akademik rotasi tidak boleh membuat skor, bobot, nilai angka, huruf mutu, sertifikat, tanda tangan digital, plagiarism checker, AI detector, WhatsApp, pembayaran, war, atau migrasi legacy `kp_*`.

## 14g. Aturan Penilaian Wahana PKPA
- Penilaian Tahap 08 hanya menghasilkan nilai per rotasi/wahana PKPA, bukan nilai akhir keseluruhan Program PKPA.
- Assessment wajib memakai skema aktif per program-wahana dan snapshot skema/komponen saat assessment dibuat.
- Skema aktif wajib berbobot 100%; skema aktif atau historis tidak boleh diedit destruktif.
- Assessment final hanya boleh setelah academic readiness `ready_for_assessment` dan seluruh komponen wajib submitted.
- Finalisasi nilai wahana tidak boleh mengubah requirement menjadi completed.
- Release nilai ke mahasiswa terpisah dari finalisasi dan mahasiswa hanya boleh melihat release miliknya.
- Perubahan setelah submit/final/release wajib melalui grade change workflow teraudit.

## 14h. Aturan Nilai Akhir dan Kelulusan PKPA
- Nilai akhir Program PKPA hanya dihitung dari skema akhir aktif yang berversi dan berbobot 100%.
- Jangan hardcode bobot enam wahana, batas lulus, huruf mutu, atau kebijakan remedial resmi.
- Source nilai wahana harus grade result current yang finalized/released sesuai policy; draft tidak boleh diagregasi.
- Completion requirement dilakukan eksplisit oleh Koordinator setelah operational complete, readiness ready, grade finalized, dan tanpa remedial aktif.
- Keputusan kelulusan terpisah dari kalkulasi nilai akhir dan wajib punya actor, waktu, alasan, serta snapshot readiness.
- Release hasil akhir terpisah dari keputusan; mahasiswa hanya melihat hasil released miliknya.
- Tahap 09 tidak membuat dokumen resmi universitas, transkrip resmi, sertifikat, SIAKAD, PDDikti, pembayaran, WhatsApp, war, atau migrasi legacy.

## 15. Aturan Penempatan KP
- Penempatan KP resmi dibuat dari selection tempat yang masih aktif.
- Satu mahasiswa hanya boleh memiliki satu assignment non-batal per periode.
- Penentuan dan perubahan pembimbing wajib melalui `KpAssignmentService`.
- Pembimbing Dalam harus berasal dari `lecturers` yang user-nya memiliki role `pembimbing_dalam`.
- Pembimbing Lapangan harus berasal dari `field_supervisors` yang user-nya memiliki role `pembimbing_lapangan`.
- Jika pembimbing lapangan dipilih, mapping tempat-pembimbing di `kp_place_field_supervisors` harus dijaga.
- Mahasiswa hanya melihat penempatannya sendiri; pembimbing hanya melihat assignment yang ditugaskan kepadanya.
- Semua perubahan assignment wajib dicatat di `kp_assignment_logs`.

## 16. Aturan Logbook KP
- Logbook KP hanya dapat dibuat oleh mahasiswa yang memiliki assignment aktif atau berjalan.
- Mahasiswa hanya boleh mengakses dan mengubah logbook miliknya sendiri.
- Logbook status `disetujui` tidak boleh diedit oleh mahasiswa.
- Validasi logbook wajib melalui `KpLogbookService`.
- Pembimbing Lapangan hanya boleh validasi logbook assignment yang ditugaskan kepadanya.
- Pembimbing Dalam hanya boleh memantau dan memberi komentar pada logbook mahasiswa bimbingannya.
- Admin dan Koordinator KP boleh memonitor semua logbook.
- Bukti kegiatan wajib divalidasi server-side, disimpan di storage non-public, dan diunduh lewat route protected.
- Semua perubahan status, submit, validasi, revisi, penolakan, upload/ganti bukti, dan komentar wajib dicatat di `kp_logbook_logs`.

## 17. Aturan Laporan Akhir KP
- Laporan akhir hanya dapat dibuat oleh mahasiswa yang memiliki assignment aktif atau berjalan.
- Satu assignment hanya memiliki satu record `kp_final_reports`, sedangkan revisi disimpan sebagai versi baru di `kp_final_report_files`.
- Mahasiswa hanya boleh mengelola laporan miliknya sendiri dan tidak boleh upload setelah laporan disetujui.
- Review laporan akhir wajib melalui `KpFinalReportService`.
- Pembimbing Dalam hanya boleh approve/revisi/tolak laporan mahasiswa bimbingannya.
- Admin dan Koordinator KP hanya melakukan monitoring pada Tahap 8.
- File laporan wajib divalidasi server-side, disimpan di storage non-public, dan diunduh melalui route protected.
- Semua upload, submit, review, revisi, penolakan, approval, dan download penting harus dicatat di `kp_final_report_logs` bila relevan.

## 18. Aturan Sidang KP
- Pengajuan sidang hanya dapat dilakukan jika assignment aktif/berjalan dan laporan akhir sudah disetujui.
- Mahasiswa hanya boleh mengajukan dan melihat sidang miliknya sendiri.
- Penjadwalan sidang wajib melalui `KpExamService`.
- Pembimbing Dalam otomatis berasal dari assignment.
- Penguji wajib berasal dari `lecturers` yang user-nya memiliki role `penguji`.
- Penguji tidak boleh sama dengan Pembimbing Dalam.
- Admin dan Koordinator KP dapat memonitor, menjadwalkan, menjadwalkan ulang, membatalkan, dan menandai sidang selesai.
- Pembimbing Dalam dan Penguji hanya boleh melihat jadwal sidang yang terkait dengan profil lecturer masing-masing.
- Semua submit, review, schedule, reschedule, cancel, dan complete wajib dicatat di `kp_exam_logs`.

## 19. Aturan Penilaian KP
- Komponen penilaian dikelola per periode oleh Admin/Koordinator.
- Input nilai wajib melalui `KpAssessmentService`.
- Pembimbing Dalam hanya boleh menilai assignment dengan `internal_supervisor_id` miliknya.
- Pembimbing Lapangan hanya boleh menilai assignment dengan `field_supervisor_id` miliknya.
- Penguji hanya boleh menilai exam dengan `examiner_id` miliknya.
- Nilai harus divalidasi 0-100 dan weighted score dihitung dari bobot komponen.
- Nilai final/published tidak boleh diubah penilai; unlock hanya Admin/Koordinator.
- Mahasiswa hanya melihat nilai setelah dipublish.
- Semua perubahan penting nilai wajib dicatat di `kp_score_logs`.

## 20. Aturan Rekap, Export, dan QA
- Rekap dan export hanya boleh diakses Admin/Koordinator.
- Export Excel harus memiliki header jelas dan tidak membuka data ke role lain.
- Setiap tahap besar wajib menjalankan `php artisan migrate`, `php artisan test`, dan `npm run build`.
- UI/UX harus dijaga konsisten: route aktif tidak overlap, menu yang sudah dibuat tidak diberi badge "Segera", table responsive, dan empty state informatif.
- Report tahap wajib mencatat hasil test/build, kendala, dan status git.

## 21. Aturan Stabilisasi dan Demo
- Sebelum commit, wajib menjalankan test dan build yang relevan; untuk tahap akhir gunakan `php artisan test` dan `npm run build`.
- Bugfix tidak boleh menghapus atau melemahkan fitur existing tanpa alasan yang dicatat di report.
- Error page 403, 404, 419, dan 500 harus user-friendly, berbahasa Indonesia, dan tidak menampilkan detail teknis.
- Route baru wajib memiliki middleware `auth` dan role middleware sesuai kebutuhan.
- Seeder demo wajib idempotent, aman dijalankan berulang, dan tidak menjadi rekomendasi password production.
- Route download file baru wajib tetap melewati controller protected dan validasi ownership/role.

## 22. Aturan Production Readiness
- Route baru wajib dilindungi middleware `auth`, `active`, `role.selected`, dan role spesifik jika route tersebut hanya untuk area tertentu.
- File upload wajib disimpan non-public dan hanya boleh diakses melalui route protected, kecuali asset publik yang memang dirancang untuk publik.
- Deployment production wajib memakai `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` domain resmi, dan cookie secure saat memakai HTTPS.
- Dokumentasi report wajib dibuat untuk setiap tahap atau patch rilis agar keputusan teknis dan hasil QA tetap terlacak.
- Sebelum commit rilis, wajib menjalankan `php artisan test` dan `npm run build`; untuk tahap production readiness juga jalankan `php artisan optimize:clear` dan `php artisan migrate`.
- Akun demo dan password default hanya untuk development/UAT internal; production wajib mengganti atau menonaktifkannya.

## 23. Aturan Avatar dan Identitas User
- Upload avatar wajib divalidasi server-side sebagai gambar JPG, JPEG, PNG, atau WebP dengan ukuran maksimal yang ditentukan fitur.
- SVG tidak boleh diizinkan sebagai avatar karena berisiko membawa konten aktif.
- Avatar user disimpan melalui storage yang aman dan path file tidak boleh diekspos langsung ke UI.
- Jika avatar tidak tersedia, UI wajib menampilkan inisial user yang rapi.
- Topbar wajib menjaga nama user dan role panjang dengan truncate agar tidak overlap dengan tombol aksi/logout.

## 24. Aturan Penyusunan Penempatan PKPA Draft
- Penempatan PKPA baru memakai `pkpa_placement_plans` dan `pkpa_rotation_assignments`; jangan menghubungkannya ke `kp_assignments` legacy.
- Satu program hanya boleh memiliki satu current plan melalui `current_key`, bukan hanya boolean `is_current`.
- Mahasiswa tidak boleh melihat rancangan draft dan tidak boleh membuat assignment.
- Assignment harus dibuat per requirement mahasiswa; bulk/group action tetap menghasilkan assignment individual.
- Pemerintahan tetap satu requirement `choose_one`; pilihan Loka POM/Dinas Kesehatan berasal dari tempat yang dipilih.
- Simpan assignment wajib memvalidasi program, requirement, tempat, availability, tanggal, kapasitas, konflik jadwal mahasiswa, Pembimbing Dalam, Pembimbing Lapangan, masa efektif, beban, dan unavailability.
- Plan `locked` atau `archived` tidak boleh diedit; buat versi baru untuk revisi.
- Bulk action wajib melalui preview, batch item, validasi baris, dan undo aman berbasis snapshot/row version.
- Export rancangan wajib diberi label "Rancangan Internal - Belum Dipublikasikan".
- Publikasi jadwal, notifikasi, surat, logbook rotasi, presensi, laporan rotasi, nilai, dan perubahan setelah publikasi bukan scope Tahap 04.
### 14i. Aturan Dokumen dan Production Hardening PKPA

- Dokumen PKPA adalah Dokumen Internal MY PSPA kecuali nomor, template, dan penandatangan sudah dikonfigurasi oleh pengelola.
- Jangan membuat kop, pejabat, nomor surat, tanda tangan, atau klaim dokumen resmi universitas secara palsu.
- File dokumen dan file akademik harus disimpan di private disk dan diunduh melalui route authorized.
- Published document tidak boleh ditimpa; gunakan versi baru, cancelled, atau superseded.
- Export spreadsheet harus mencegah formula injection.
- Health public tidak boleh membocorkan host, path, nama database, stack trace, token, atau secret.

### 14j. Aturan AI-Assisted Pre-UAT PKPA

- Tahap 11A adalah AI-Assisted Pre-UAT, bukan UAT pengguna dan bukan approval production.
- Keputusan akhir hanya boleh memakai salah satu status: "Layak masuk UAT pengguna", "Layak masuk UAT pengguna dengan catatan", atau "Belum layak masuk UAT pengguna".
- Jangan menjalankan `migrate:fresh` pada MySQL. Clean install hanya boleh memakai database sementara SQLite yang terdokumentasi.
- Audit integritas wajib melalui `php artisan pkpa:integrity-audit --json`, bersifat dry-run, tidak auto-fix, dan exit code gagal bila ada blocker Critical/High.
- Pre-UAT wajib mencatat hasil regression, route access, browser smoke, queue/document audit, backup-restore simulation, dan gap yang masih perlu human UAT.
- Jangan commit, push, tag, merge, release, deploy, atau mengubah aplikasi Core Farmasi saat menjalankan Tahap 11A.

### 14k. Aturan Penutupan Catatan Pre-UAT PKPA

- Tahap 11B menutup catatan teknis Pre-UAT, bukan menggantikan human UAT dan bukan approval production.
- Browser E2E memakai Playwright dengan `E2E_BASE_URL`; jangan hardcode URL production.
- Credential E2E nyata wajib berasal dari environment `E2E_*` dan tidak boleh disimpan di repository atau laporan.
- Fixture E2E lokal hanya boleh berjalan di non-production dan tidak boleh membuat route login bypass.
- Queue async, scheduler, email sandbox, dan restore MySQL disposable wajib dilaporkan jujur dengan batas environment.
- Critical/High defect terbuka harus 0 sebelum status "Siap Human UAT" atau "Siap Human UAT dengan kondisi".

### 14l. Aturan Controlled Go-Live dan Hypercare PKPA

- Status resmi Tahap 12A adalah "Human UAT: Waived by Project Owner", "Release approach: Controlled Go-Live", dan "Post-release mode: Intensive Hypercare".
- Jangan menyatakan klaim UAT diterima, selesai, disetujui pengguna, atau seluruh pengguna telah menerima aplikasi.
- Controlled go-live hanya boleh diklaim berhasil jika approval Project Owner, backup target, HTTPS target, Core login nyata, role utama, queue/scheduler target, smoke test target, monitoring, rollback, dan known limitations sudah tervalidasi.
- Jika server target atau credential tidak tersedia, status yang benar adalah "Persiapan selesai, deployment belum dilakukan".
- Selama release freeze, hanya bug fix, security fix, data integrity fix, production configuration fix, atau UX blocker fix yang boleh dikerjakan.
- Jangan commit, push, tag, merge, release, deploy, atau mengubah `apps/core-farmasi` tanpa instruksi eksplisit Project Manager.
- `pkpa:hypercare-status --json` harus read-only, cepat, aman, tidak membocorkan secret, dan exit code mengikuti status health.
- Clean installation hanya boleh memakai SQLite temporary; jangan menjalankan `migrate:fresh` pada MySQL development atau target.
