# TAHAP 10 - Penilaian KP dan Nilai Akhir

## 1. Ringkasan Pengerjaan
Tahap 10 menambahkan halaman Berkas KP mahasiswa, modul komponen penilaian fleksibel, input nilai per penilai, perhitungan nilai akhir berbasis bobot, finalisasi/publish/unlock nilai, halaman nilai mahasiswa, dashboard summary, test, dan dokumentasi.

## 2. Bugfix/Polish Berkas KP Mahasiswa
- Route baru: `/mahasiswa/berkas-kp`.
- Menu Berkas KP di sidebar mahasiswa sudah bisa diklik.
- Halaman menampilkan dokumen wajib/opsional, status, file, catatan revisi, tanggal upload, tombol upload/re-upload/download.
- Active-state sidebar sudah eksplisit: `/mahasiswa/pendaftaran-kp` aktif di Pendaftaran KP, `/mahasiswa/berkas-kp` aktif di Berkas KP.
- Upload dokumen yang sudah disetujui/terverifikasi diblokir server-side.
- Commit terpisah: `bb31c91 Add student KP documents page`.

## 3. Fitur Penilaian yang Dibuat
- CRUD komponen penilaian per periode.
- Input nilai Pembimbing Dalam.
- Input nilai Preseptor.
- Input nilai Penguji.
- Submit nilai per role penilai.
- Hitung nilai akhir dari weighted score.
- Finalisasi/kunci nilai.
- Publish nilai ke mahasiswa.
- Unlock nilai oleh Admin/Koordinator.
- Log aktivitas penilaian.

## 4. Struktur File Penting
- `database/migrations/2026_05_22_000012_create_kp_assessment_tables.php`
- `app/Models/KpAssessmentComponent.php`
- `app/Models/KpScore.php`
- `app/Models/KpFinalScore.php`
- `app/Models/KpScoreLog.php`
- `app/Services/KpAssessmentService.php`
- `app/Http/Controllers/Management/AssessmentComponentController.php`
- `app/Http/Controllers/Management/ScoreMonitoringController.php`
- `app/Http/Controllers/InternalSupervisor/AssessmentController.php`
- `app/Http/Controllers/FieldSupervisor/AssessmentController.php`
- `app/Http/Controllers/Examiner/AssessmentController.php`
- `app/Http/Controllers/Student/ScoreController.php`
- `tests/Feature/KpAssessmentAndFinalScoreTest.php`

## 5. Database dan Migration
- `kp_assessment_components`: komponen nilai per periode, jenis penilai, bobot, max score, status.
- `kp_scores`: nilai komponen per assignment, penilai, score, weighted score, status draft/submitted/locked.
- `kp_final_scores`: nilai akhir, grade, status calculated/locked/published.
- `kp_score_logs`: audit aktivitas komponen, input nilai, submit, finalisasi, publish, unlock.

## 6. Alur Komponen Penilaian
Admin/Koordinator membuat komponen nilai per periode dan assessor type. UI menampilkan warning jika total bobot aktif belum 100%.

## 7. Alur Input Nilai Pembimbing Dalam
Pembimbing Dalam membuka menu Penilaian Pembimbing, memilih mahasiswa bimbingan, mengisi komponen `pembimbing_dalam`, menyimpan draft, lalu submit.

## 8. Alur Input Nilai Preseptor
Preseptor membuka menu Penilaian Lapangan, mengisi nilai mahasiswa yang ditugaskan, menyimpan draft, lalu submit.

## 9. Alur Input Nilai Penguji
Penguji membuka menu Penilaian Sidang, memilih sidang yang ditugaskan, mengisi komponen `penguji`, menyimpan draft, lalu submit.

## 10. Alur Perhitungan Nilai Akhir
Setiap nilai menghitung `weighted_score = score * weight / 100`. Nilai akhir dijumlahkan dari weighted score semua komponen submitted/locked.

## 11. Finalisasi, Publish, dan Unlock Nilai
Admin/Koordinator dapat finalisasi jika semua komponen wajib sudah submitted. Finalisasi mengunci nilai, publish menampilkan nilai ke mahasiswa, unlock membuka kembali nilai jika ada koreksi.

## 12. Halaman Nilai Mahasiswa
Mahasiswa melihat empty state jika nilai belum tersedia, pesan proses jika belum published, dan nilai akhir/grade jika sudah published.

## 13. Role dan Hak Akses
- Mahasiswa hanya melihat nilainya sendiri.
- Pembimbing Dalam hanya menilai mahasiswa bimbingannya.
- Preseptor hanya menilai mahasiswa tugasnya.
- Penguji hanya menilai sidang yang ditugaskan.
- Admin/Koordinator melakukan monitoring, finalisasi, publish, dan unlock.

## 14. UI/UX yang Diterapkan
UI menggunakan card, badge, table responsive, empty state, form input nilai yang sederhana, warning bobot, dan konfirmasi pada aksi finalisasi/publish/unlock.

## 15. Keamanan yang Diterapkan
- Validasi nilai 0-100 server-side.
- Validasi assessor type sesuai role dan assignment.
- Nilai locked/published tidak dapat diubah oleh penilai.
- Unlock hanya Admin/Koordinator.
- Semua perubahan penting dicatat dalam log.
- CSRF protection tetap aktif.

## 16. Testing
- `php artisan migrate`: berhasil.
- `php artisan test`: 69 passed, 313 assertions.
- `npm run build`: berhasil.
- `git status`: dicek sebelum commit.

## 17. Cara Menjalankan
```bash
composer install
npm install
npm run dev
php artisan migrate --seed
php artisan serve
```

Untuk build asset:
```bash
npm run build
```

## 18. Catatan Kendala
Tidak ada kendala blocker. Grade masih aturan sementara: A >= 85, B >= 75, C >= 65, D >= 50, E < 50.

## 19. Rekomendasi Tahap Berikutnya
Tahap 11 - Rekap, Export, dan Polishing Akhir.

