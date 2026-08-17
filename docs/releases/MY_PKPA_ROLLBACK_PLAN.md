# MY PKPA Rollback Plan

Rollback trigger:
- login seluruh pengguna gagal;
- authorization bocor;
- data akademik rusak;
- nilai atau kelulusan salah;
- upload/download private bocor;
- migration gagal;
- error 500 berulang pada alur utama;
- queue menggandakan data;
- integrity audit menemukan issue Critical/High.

Application rollback:
- aktifkan maintenance mode bila dampak luas;
- checkout release sebelumnya yang disetujui;
- restore asset build sebelumnya;
- restore config sebelumnya dari secret/env manager;
- jalankan `php artisan optimize:clear`;
- cache ulang config/route/view setelah sehat.

Database rollback:
- jangan memakai `migrate:fresh`;
- hindari rollback migration destruktif;
- bila data rusak, restore backup target yang tercatat di `MY_PKPA_PRE_GO_LIVE_BACKUP_RECORD.md`;
- jalankan `php artisan migrate:status`;
- jalankan `php artisan pkpa:integrity-audit --json`.

File rollback:
- restore private storage backup;
- restore document templates backup;
- restore generated documents backup;
- jalankan `php artisan pkpa:document-orphan-audit --json`.

Core fallback:
- jangan fallback ke akun lokal;
- jika Core down, tampilkan maintenance/akses tertahan;
- jangan memperluas akses dari cache lama.

Komunikasi:
- P0/P1 dilaporkan ke Project Owner segera;
- catat incident di `docs/hypercare/MY_PKPA_HYPERCARE_LOG.md`;
- catat hotfix dan retest sebelum layanan dinyatakan pulih.
