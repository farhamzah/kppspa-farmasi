# TAHAP 09 - Pengajuan dan Penjadwalan Sidang KP

## 1. Ringkasan Pengerjaan
Tahap 9 menambahkan modul pengajuan sidang, penjadwalan sidang, penentuan penguji, halaman jadwal untuk mahasiswa/pembimbing dalam/penguji, monitoring Admin/Koordinator, dan log aktivitas sidang.

## 2. UI Bugfix yang Dikerjakan
- Sidebar Mahasiswa diperbaiki dengan active-state berbasis route name yang spesifik. Halaman `/mahasiswa/pendaftaran-kp` hanya mengaktifkan menu Pendaftaran KP, sedangkan halaman detail/upload berkas mengaktifkan Berkas KP.
- Halaman login dipadatkan agar desktop/laptop umum tidak scroll berlebihan. Hero copy dipersingkat, spacing dikurangi, dan panel login dibuat lebih proporsional.
- Bugfix UI sudah dibuat pada commit terpisah: `f54783a Fix student sidebar and login layout UI`.

## 3. Fitur Sidang yang Dibuat
- Mahasiswa mengajukan sidang setelah laporan akhir disetujui.
- Admin/Koordinator memonitor dan mereview pengajuan sidang.
- Admin/Koordinator menjadwalkan sidang, menentukan penguji, reschedule, cancel, dan complete.
- Mahasiswa melihat status pengajuan dan jadwal sidangnya.
- Pembimbing Dalam melihat sidang mahasiswa bimbingannya.
- Penguji melihat sidang yang ditugaskan.
- Log aktivitas pengajuan dan jadwal sidang tersimpan.

## 4. Struktur File Penting
- `database/migrations/2026_05_22_000011_create_kp_exam_tables.php`
- `app/Models/KpExamRequest.php`
- `app/Models/KpExam.php`
- `app/Models/KpExamLog.php`
- `app/Services/KpExamService.php`
- `app/Http/Controllers/Student/ExamRequestController.php`
- `app/Http/Controllers/Management/ExamRequestController.php`
- `app/Http/Controllers/Management/ExamScheduleController.php`
- `app/Http/Controllers/Management/ExamLogController.php`
- `app/Http/Controllers/InternalSupervisor/ExamScheduleController.php`
- `app/Http/Controllers/Examiner/ExamScheduleController.php`
- `resources/views/student/exams`
- `resources/views/management/exam-requests`
- `resources/views/management/exams`
- `resources/views/management/exam-logs`
- `resources/views/internal-supervisor/exams`
- `resources/views/examiner/exams`
- `tests/Feature/KpExamSchedulingTest.php`

## 5. Database dan Migration
- `kp_exam_requests`: menyimpan pengajuan sidang per assignment, status pengajuan, catatan, reviewer, dan waktu submit/review.
- `kp_exams`: menyimpan jadwal sidang, pembimbing dalam sebagai supervisor, penguji, tanggal, jam, mode, ruangan/link, status, dan penjadwal.
- `kp_exam_logs`: audit log untuk submit, approve, revisi, tolak, schedule, reschedule, cancel, dan complete.

## 6. Alur Pengajuan Sidang Mahasiswa
Mahasiswa membuka menu Sidang. Sistem mengecek assignment aktif/berjalan dan laporan akhir berstatus `disetujui`. Jika memenuhi syarat, mahasiswa dapat mengajukan sidang. Pengajuan berstatus `diajukan` dan dicatat pada log.

## 7. Alur Penjadwalan Sidang Admin/Koordinator
Admin/Koordinator membuka daftar pengajuan, melihat detail, lalu menjadwalkan sidang dengan penguji, tanggal, jam, mode, ruangan/link, dan catatan. Setelah dijadwalkan, status pengajuan menjadi `dijadwalkan`.

## 8. Penentuan Penguji
Penguji dipilih dari profil lecturer yang user-nya memiliki role `penguji`. Sistem menolak penguji yang sama dengan Pembimbing Dalam.

## 9. Jadwal Sidang Mahasiswa
Mahasiswa melihat kartu jadwal berisi tanggal, jam, mode, ruangan/link, Pembimbing Dalam, Penguji, dan status sidang.

## 10. Jadwal Sidang Pembimbing Dalam
Pembimbing Dalam hanya melihat sidang dengan `supervisor_id` sesuai profil lecturer miliknya.

## 11. Jadwal Sidang Penguji
Penguji hanya melihat sidang dengan `examiner_id` sesuai profil lecturer miliknya.

## 12. Status Sidang dan Pengajuan
Status pengajuan: `draft`, `diajukan`, `disetujui`, `dijadwalkan`, `revisi`, `ditolak`, `dibatalkan`.
Status sidang: `dijadwalkan`, `selesai`, `dibatalkan`, `ditunda`.

## 13. Role dan Hak Akses
- Mahasiswa: mengajukan dan melihat jadwal miliknya.
- Admin/Koordinator KP: monitoring, review pengajuan, jadwal, cancel, complete, log.
- Pembimbing Dalam: melihat jadwal mahasiswa bimbingannya.
- Penguji: melihat jadwal yang ditugaskan.
- Preseptor: tidak memiliki akses modul sidang.

## 14. UI/UX yang Diterapkan
UI menggunakan card status, badge, table responsive, filter, empty state, progress step mahasiswa, form jadwal yang jelas, dan pesan validasi berbahasa Indonesia.

## 15. Keamanan yang Diterapkan
- Validasi eligibility laporan akhir disetujui.
- Validasi server-side untuk jadwal sidang.
- Role middleware membatasi akses.
- Pembimbing/Penguji hanya bisa melihat jadwal terkait dirinya.
- Penguji wajib role `penguji`.
- Semua perubahan penting dicatat pada log.

## 16. Testing
- `php artisan migrate`: berhasil.
- `php artisan test`: 62 passed, 269 assertions.
- `npm run build`: berhasil.
- `git status`: dicek sebelum commit Tahap 9.

## 17. Cara Menjalankan
```bash
composer install
npm install
npm run dev
php artisan migrate --seed
php artisan serve
```

Untuk verifikasi production asset:
```bash
npm run build
```

## 18. Catatan Kendala
Tidak ada kendala blocker. Pengajuan ulang setelah request ditolak/dibatalkan belum dibuat kompleks karena skema tahap ini memakai satu request sederhana per assignment.

## 19. Rekomendasi Tahap Berikutnya
Tahap 10 - Penilaian KP dan Nilai Akhir.

