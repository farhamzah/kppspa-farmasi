# MY PKPA Security Hardening Spec

Tahap 10 menambahkan:
- security headers dasar: nosniff, same-origin frame, referrer policy, permissions policy, CSP moderat, HSTS hanya HTTPS production;
- rate limit health, export, dan download;
- health public minimal tanpa host/path/secret;
- admin health protected;
- private document storage;
- authorized download dan IDOR prevention;
- formula-injection guard untuk CSV/XLSX;
- upload guard untuk executable, double extension, invalid PDF, invalid Office ZIP;
- queue/job failure message redaction.

Belum diklaim tersedia:
- antivirus aktif;
- digital signature tersertifikasi;
- external token link untuk recipient luar;
- CSP final ketat.

Hal tersebut perlu diuji di pre-UAT.
