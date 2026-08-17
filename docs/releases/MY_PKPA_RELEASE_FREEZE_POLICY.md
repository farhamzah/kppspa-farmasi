# MY PKPA Release Freeze Policy

Mulai berlaku: sebelum controlled go-live.

Yang dibekukan:
- fitur baru;
- perubahan formula akademik;
- perubahan struktur role;
- perubahan workflow inti tanpa change approval;
- migrasi destruktif;
- perubahan integrasi Core tanpa persetujuan.

Yang diizinkan:
- bug fix;
- security fix;
- data integrity fix;
- production configuration fix;
- UX blocker fix;
- dokumentasi operasional/hypercare.

Aturan hotfix:
- wajib punya issue/defect record;
- wajib punya regression test sesuai dampak;
- wajib menjalankan test/build relevan;
- migration harus additive dan reversible sejauh memungkinkan;
- backup wajib tersedia sebelum deploy;
- retest wajib dicatat.

Fitur baru selama hypercare:
- masuk backlog;
- hanya boleh dikerjakan bila Project Owner menyetujui secara eksplisit.
