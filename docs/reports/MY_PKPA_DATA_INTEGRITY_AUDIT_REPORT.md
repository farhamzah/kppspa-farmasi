# MY PKPA Data Integrity Audit Report

Command: `php artisan pkpa:integrity-audit --json`

Karakter command:
- Dry-run.
- Tidak melakukan auto-fix.
- Output JSON terstruktur.
- Exit code gagal bila ada Critical/High issue.

Hasil MySQL lokal:
- status: passed
- checks_total: 25
- issue_count: 0
- critical_open: 0
- high_open: 0
- medium_open: 0
- low_open: 0

Check yang dicakup meliputi peserta tanpa requirement, duplicate requirement, group member aktif ganda, assignment orphan, duplicate assignment, current plan/publication ganda, runtime tanpa publication, logbook/kompetensi orphan, attachment hilang, assessment/grade/final result orphan, dokumen tanpa versi, nomor dokumen ganda, current key ganda, dan duplicate notification key.
