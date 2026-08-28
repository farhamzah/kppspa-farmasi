# Prompt Tahap 03 - Kapasitas, Pembimbing, dan Readiness

## Instruksi Utama

Bangun fondasi sebelum placement untuk Program PKPA: program sites, availability period dan kapasitas, validasi kerja sama, hari operasional, Preseptor dari Core, eligibility Pembimbing Dalam dari Core per program/wahana, batas beban, unavailability, sync log, readiness checklist, dashboard, UI, audit, test, dan dokumentasi.

## Larangan

Jangan membuat actual placement mahasiswa, tanggal/urutan rotasi, matrix, jadwal, assignment supervisor ke mahasiswa, logbook, nilai, migrasi legacy, atau perubahan `kp_*`.

## Core

Gunakan `core_user_id`. Jangan membuat local account/password/role. Test wajib fake Core.

## QA Target

- `php artisan optimize:clear`
- `php artisan migrate:fresh --seed --force` hanya pada database disposable
- `php artisan test`
- `npm run build`
- `php artisan route:list --path=management/pkpa`
- Browser QA desktop, tablet, mobile

