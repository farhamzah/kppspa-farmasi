# MY PSPA Known Limitations

- Human UAT formal dilewati berdasarkan keputusan Project Owner.
- Penggunaan awal harus dipantau sebagai controlled go-live dengan intensive hypercare.
- Akun Core nyata seluruh role belum diuji melalui E2E pada server target dalam sesi lokal ini.
- HTTPS target belum diverifikasi dalam sesi lokal ini.
- Queue worker target belum diverifikasi pada server nyata.
- Scheduler target belum dikonfigurasi dengan task nyata.
- Email production direkomendasikan tetap nonaktif sampai SMTP/sandbox/recipient/retry diverifikasi.
- Antivirus/file scanning belum terverifikasi tersedia.
- Backup-restore server target belum dibuktikan.
- Integrasi eksternal di luar Core Farmasi belum tersedia.
- Beberapa aktivitas operasional awal tetap membutuhkan pemantauan manual.
- Izin eksplisit commit/push diberikan pada Tahap 13B, tetapi deployment belum diizinkan.
- Remote Git MY PSPA dikonfigurasi pada Tahap 13B; remote lama SI-KP dipertahankan sebagai upstream historis.
