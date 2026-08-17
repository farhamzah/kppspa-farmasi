# MY PKPA Security Regression Report

Tanggal: 2026-07-17

Hasil utama:
- Protected route PKPA memakai auth dan role middleware sesuai coverage feature test.
- Mahasiswa yang mencoba akses `/management/pkpa-documents` mendapatkan forbidden pada regression test.
- Download dokumen mahasiswa divalidasi ownership/recipient; mahasiswa lain forbidden.
- Public `/health` tidak membocorkan nama database dan memiliki header `x-content-type-options: nosniff`.
- Upload dokumen menolak nama executable terselubung seperti `.pdf.exe`.
- Export CSV mencegah formula injection dengan prefix aman.

Command:
- `php artisan test --stop-on-failure`: pass, 298 tests.
- `php artisan route:list --name=pkpa`: pass, 215 routes.

Residual risk:
- Penetration test manual belum dilakukan.
- Rate limiting login dan header production perlu diverifikasi ulang di staging HTTPS.
