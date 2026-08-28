# TAHAP 05 - Pemilihan Tempat KP / War Ticket

## 1. Ringkasan Pengerjaan
Tahap 5 menambahkan modul Pemilihan Tempat KP berbasis first come first served. Mahasiswa yang pendaftarannya terverifikasi dapat memilih tempat pada jadwal pemilihan. Admin dan Koordinator KP dapat memonitor hasil, membatalkan pilihan, memindahkan pilihan, melihat daftar tunggu, dan membaca log pemilihan.

## 2. Fitur yang Dibuat
- Halaman Pemilihan Tempat KP untuk mahasiswa.
- Service transaksi `KpPlaceSelectionService`.
- Selection aktif dengan proteksi satu mahasiswa satu pilihan aktif per periode.
- Daftar tunggu saat kuota penuh.
- Monitoring pemilihan untuk Admin/Koordinator.
- Cancel dan move selection oleh Admin/Koordinator.
- Log semua aksi penting pemilihan.
- Dashboard dan menu Tahap 5.
- Feature test Tahap 5.

## 3. Struktur File Penting
- `database/migrations/2026_05_22_000007_create_kp_place_selection_tables.php`
- `app/Models/KpPlaceSelection.php`
- `app/Models/KpSelectionLog.php`
- `app/Models/KpWaitingList.php`
- `app/Services/KpPlaceSelectionService.php`
- `app/Http/Controllers/Student/PlaceSelectionController.php`
- `app/Http/Controllers/Management/PlaceSelectionMonitoringController.php`
- `app/Http/Controllers/Management/WaitingListController.php`
- `app/Http/Controllers/Management/SelectionLogController.php`
- `resources/views/student/place-selections/*`
- `resources/views/management/place-selections/*`
- `resources/views/management/waiting-lists/index.blade.php`
- `resources/views/management/selection-logs/index.blade.php`
- `tests/Feature/KpPlaceSelectionWarTicketTest.php`

## 4. Database dan Migration
Tabel `kp_place_selections` menyimpan pilihan tempat mahasiswa, status aktif/dibatalkan/dipindahkan, waktu memilih, user pemilih, pembatal, alasan pembatalan, relasi move, dan `active_key` unique untuk mencegah pilihan aktif ganda.

Tabel `kp_selection_logs` menyimpan audit pemilihan seperti success, failed, waiting list, cancel, dan move, lengkap dengan user, mahasiswa, periode, tempat, pesan, metadata, IP address, dan user agent.

Tabel `kp_waiting_lists` menyimpan mahasiswa yang belum mendapatkan tempat karena kuota penuh. Kombinasi periode dan mahasiswa dibuat unique.

## 5. Alur Pemilihan Tempat Mahasiswa
Mahasiswa membuka menu Pemilihan Tempat KP, memilih periode yang pendaftarannya sudah terverifikasi, melihat jadwal pemilihan dan waktu server, lalu memilih tempat yang kuotanya terbuka dan masih tersisa. Setelah berhasil, selection berstatus `aktif` dan pilihan terkunci.

## 6. Aturan First Come First Served
Mahasiswa yang lebih dulu berhasil membuat selection aktif mendapatkan tempat terlebih dahulu. Keputusan memakai waktu server dan validasi server-side, bukan jam perangkat mahasiswa.

## 7. Proteksi Race Condition
Logic kritis berada di `KpPlaceSelectionService::selectPlace()`. Proses memakai `DB::transaction()`, `lockForUpdate()` pada row kuota, validasi ulang eligibility, jadwal, status kuota, pilihan aktif, dan filled count di dalam transaksi. Constraint `active_key` unique menjadi pagar database tambahan agar satu mahasiswa tidak punya dua selection aktif pada periode sama.

## 8. Alur Daftar Tunggu
Jika kuota penuh dan mahasiswa belum punya pilihan aktif, sistem membuat atau memperbarui record daftar tunggu berstatus `menunggu`. Jika mahasiswa kemudian berhasil memilih, waiting list berubah menjadi `sudah_memilih`. Admin/Koordinator dapat membatalkan waiting list.

## 9. Monitoring Admin/Koordinator
Admin dan Koordinator KP dapat melihat statistik terverifikasi, sudah memilih, belum memilih, daftar tunggu, total kuota, sisa kuota, dan tempat penuh. Tersedia filter periode, status, dan search nama/NIM/tempat.

## 10. Cancel dan Move Selection oleh Admin/Koordinator
Cancel selection wajib menyertakan alasan. Selection lama berubah menjadi `dibatalkan`, `active_key` dikosongkan, dan slot kembali tersedia. Move selection mengubah selection lama menjadi `dipindahkan`, lalu membuat selection baru pada kuota tujuan jika kuota tujuan masih tersedia.

## 11. Log Pemilihan
Log mencatat selection success, gagal karena belum terverifikasi, jadwal tertutup/belum buka, sudah memilih, kuota penuh, kuota ditutup, daftar tunggu, cancel, dan move.

## 12. Role dan Hak Akses
Mahasiswa hanya dapat memilih untuk pendaftaran miliknya sendiri. Admin dan Koordinator KP dapat monitoring, cancel, move, daftar tunggu, dan log. Pembimbing Dalam, Preseptor, dan Penguji tidak diberi akses modul ini.

## 13. UI/UX yang Diterapkan
UI memakai bahasa Indonesia, card, badge status, table responsive, filter, empty state, alert sukses/error, waktu server, status jadwal, ringkasan kuota, dan konfirmasi sebelum memilih/cancel/move.

## 14. Keamanan yang Diterapkan
- Route dilindungi `auth`, `active`, `role.selected`, dan middleware role.
- Pemilihan divalidasi server-side dan hanya lewat service.
- Semua cek kritis diulang dalam transaction.
- Row kuota dikunci saat pemilihan.
- Constraint `active_key` mencegah selection aktif ganda.
- Mahasiswa tidak memiliki route untuk cancel/move.
- Semua aksi penting dicatat di `kp_selection_logs`.

## 15. Testing
Hasil verifikasi:
- `php artisan migrate`: berhasil, tidak ada migration tertinggal.
- `php artisan test`: berhasil, 37 passed, 134 assertions.
- `npm.cmd run build`: berhasil, Vite build selesai.
- `git status`: bersih setelah commit Tahap 5.
- Commit Tahap 5 dibuat dengan pesan `Add KP place selection war ticket`.

Test Tahap 5 mencakup mahasiswa terverifikasi membuka halaman, mahasiswa belum terverifikasi ditolak memilih, pilihan sukses, filled count/sisa kuota, anti pilih dua kali, jadwal tertutup, kuota ditutup, kuota penuh dan daftar tunggu, monitoring Admin/Koordinator, forbidden untuk mahasiswa, cancel, move, log success/failed, dan simulasi quota=1 untuk race condition berurutan.

## 16. Cara Menjalankan
```bash
composer install
npm install
php artisan migrate --seed
npm run dev
php artisan serve
```

Untuk build asset:

```bash
npm run build
```

Di PowerShell Windows, gunakan `npm.cmd run build` jika `npm` ditolak execution policy.

## 17. Catatan Kendala
Daftar tunggu untuk kuota penuh sempat dibuat di dalam transaction yang rollback saat validation exception. Implementasi diperbaiki agar waiting list dibuat setelah rollback path untuk kasus kuota penuh, sehingga data tunggu tetap tersimpan.

## 18. Rekomendasi Tahap Berikutnya
Tahap 6 - Penentuan Pembimbing dan Penempatan KP.

