# Route Permission Audit

Audit dilakukan pada Tahap 12 menggunakan `php artisan route:list`.

## Ringkasan Route Group

| Area | Prefix | Middleware Utama | Catatan |
|---|---|---|---|
| Auth | `/login`, `/logout` | `guest` untuk login, `auth` untuk logout | Login menggunakan CSRF dan logout invalidates session serta regenerate token. |
| Role Selection | `/pilih-role`, `/set-role/{role}` | `auth`, `active` | User multi-role memilih role aktif sebelum masuk dashboard. |
| Dashboard dan Profil | `/dashboard`, `/profil-saya`, `/profile` | `auth`, `active`, `role.selected` | Role aktif divalidasi oleh middleware. |
| Admin | `/admin/*` | `auth`, `active`, `role.selected`, `role:admin` | Manajemen user dan import hanya untuk Admin. |
| Management KP | `/management/*` | `auth`, `active`, `role.selected`, `role:admin,koordinator_kp` | Periode, kuota, verifikasi, assignment, logbook, laporan, sidang, nilai, rekap, dan export dilindungi Admin/Koordinator. |
| Mahasiswa | `/mahasiswa/*` | `auth`, `active`, `role.selected`, `role:mahasiswa` | Pendaftaran, berkas, pemilihan, penempatan, logbook, laporan, sidang, nilai hanya untuk Mahasiswa. |
| Pembimbing Dalam | `/pembimbing-dalam/*` | `auth`, `active`, `role.selected`, `role:pembimbing_dalam` | Akses mahasiswa bimbingan, logbook, laporan, sidang, dan nilai sesuai pembimbing. |
| Preseptor | `/pembimbing-lapangan/*` | `auth`, `active`, `role.selected`, `role:pembimbing_lapangan` | Akses mahasiswa tugas lapangan, validasi logbook, dan nilai lapangan. |
| Penguji | `/penguji/*` | `auth`, `active`, `role.selected`, `role:penguji` | Akses jadwal sidang dan penilaian penguji. |

## Catatan Route Berisiko

- Route `/management/*` tidak terbuka untuk mahasiswa karena berada di group `role:admin,koordinator_kp`.
- Route download dokumen, bukti logbook, laporan akhir, dan export berada di controller dengan middleware auth/role sesuai area.
- Route `storage/{path}` adalah route bawaan Laravel untuk local storage development. File upload modul KP tetap disimpan di disk non-public dan diakses melalui route controller protected, bukan direct URL public.
- Route `/up` adalah health check bawaan framework dan tidak memuat data aplikasi.

## Perbaikan yang Dilakukan

- Menambahkan handling `TokenMismatchException` agar 419 diarahkan ke login dengan pesan ramah.
- Menambahkan halaman error 403, 404, 419, dan 500 yang konsisten dengan UI aplikasi.
- Menambahkan header no-cache pada halaman login untuk mengurangi risiko token lama dari back/forward browser.
- Memperbarui `.env.example` agar konfigurasi session lokal lebih aman untuk demo: `APP_URL=http://127.0.0.1:8000`, `SESSION_DRIVER=file`, `SESSION_DOMAIN=null`, dan `SESSION_SECURE_COOKIE=false`.

## Kesimpulan

Tidak ditemukan route management utama yang terbuka untuk role mahasiswa/pembimbing/penguji. Route role-specific sudah berada di group middleware yang sesuai. Area yang tetap perlu dijaga pada pengembangan berikutnya adalah setiap route download baru wajib tetap melewati controller protected dan validasi ownership.

## Tahap 13 Final Route Check

Audit ulang dilakukan pada Tahap 13 dengan `php artisan route:list`. Command berhasil dijalankan dan menampilkan 202 route.

Hasil final:
- Route `/management/*` tetap berada di group `auth`, `active`, `role.selected`, dan `role:admin,koordinator_kp`.
- Route `/mahasiswa/*` tetap berada di group `role:mahasiswa`.
- Route `/pembimbing-dalam/*` tetap berada di group `role:pembimbing_dalam`.
- Route `/pembimbing-lapangan/*` tetap berada di group `role:pembimbing_lapangan`.
- Route `/penguji/*` tetap berada di group `role:penguji`.
- Route download dokumen KP, bukti logbook, laporan akhir, dan export berada pada controller di dalam group auth/role.
- Tidak ditemukan route debug/test custom yang terbuka untuk publik.
- Route framework seperti `/up` dan `storage/{path}` dicatat sebagai route bawaan. File upload aplikasi KP tetap diarahkan melalui storage non-public dan controller download protected.

Tidak ada perubahan route yang diperlukan pada Tahap 13. Checklist keamanan route ditambahkan ke dokumentasi production readiness agar route baru pada fase berikutnya tetap wajib memakai middleware sesuai area.

