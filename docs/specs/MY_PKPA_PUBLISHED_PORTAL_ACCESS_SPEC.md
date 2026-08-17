# MY PKPA Published Portal Access Spec

Portal published schedule memakai snapshot resmi dari publication current.

## Mahasiswa

- Route: `student.pkpa-schedule.*`
- Menu: `PKPA Saya`
- Filter akses: `student_core_user_id` harus sama dengan `users.core_user_id`.
- Mahasiswa hanya dapat melihat assignment miliknya.
- Acknowledgement adalah tanda membaca jadwal, bukan approval.

## Pembimbing Dalam

- Route: `internal-supervisor.pkpa-schedule.*`
- Menu: `Jadwal PKPA`
- Filter akses: supervisor snapshot bertipe `internal` dan `core_user_id` sama dengan user login.

## Pembimbing Lapangan

- Route: `field-supervisor.pkpa-schedule.*`
- Menu: `Jadwal PKPA`
- Filter akses: supervisor snapshot bertipe `field` dan `core_user_id` sama dengan user login.

## Acknowledgement

Record `pkpa_schedule_acknowledgements` menyimpan tipe audience, tipe event `viewed` atau `acknowledged`, waktu, hash IP, dan ringkasan user-agent. Duplicate acknowledgement dicegah oleh unique key.
