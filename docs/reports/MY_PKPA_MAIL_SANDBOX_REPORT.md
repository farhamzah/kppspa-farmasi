# MY PKPA Mail Sandbox Report

Mailer:
- `MAIL_MAILER=log`
- `PKPA_EMAIL_NOTIFICATIONS_ENABLED=true` hanya untuk proses pengujian.
- Tidak ada SMTP/email production yang dipakai.

Event:
- `placement_published` mail delivery sandbox.

Result:
- sent: 1
- skipped: 0
- failed: 0
- status delivery: sent

Checks:
- subject memakai Bahasa Indonesia.
- body tidak memuat password atau token login.

Catatan:
- Feature flag email di `.env` tetap default aman/nonaktif.
- SMTP sandbox staging perlu diverifikasi ulang saat server staging tersedia.
