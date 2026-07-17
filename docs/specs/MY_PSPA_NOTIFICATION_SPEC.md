# MY PSPA Notification Spec

Notifikasi Tahap 05 dicatat sebagai delivery record yang dapat diproses ulang.

## Channel

- `database`: in-app delivery record. Default aktif melalui `PKPA_DATABASE_NOTIFICATIONS_ENABLED=true`.
- `mail`: email. Default nonaktif melalui `PKPA_EMAIL_NOTIFICATIONS_ENABLED=false`.

## Event

- `placement_published`
- `placement_revised`
- `placement_withdrawn`

## Aturan

- Kegagalan notifikasi tidak boleh rollback publication.
- Delivery memakai `notification_key` hash agar idempotent.
- Email disabled atau email kosong menjadi `skipped`, bukan error fatal.
- Retry memproses status `pending` dan `failed`.
- Failure message dipotong dan tidak menyimpan token/password/secret.
