# MY PKPA Document Automation Spec

Tahap 10 menyediakan fondasi Dokumen Internal MY PKPA untuk PKPA. Dokumen dibuat dari snapshot, disimpan di private disk, mempunyai histori versi, dan tidak menggantikan dokumen resmi universitas.

Jenis awal: surat penempatan mahasiswa, surat pengantar mitra, surat tugas Pembimbing Dalam, daftar mahasiswa mitra, rekap jadwal mahasiswa/tempat/pembimbing, surat perubahan penempatan, rekap hasil wahana, hasil akhir PKPA internal, dan Transkrip Internal PKPA.

Prinsip:
- Template berversi dan hanya satu current per jenis/program.
- Placeholder whitelist, tanpa eval, PHP, atau expression user.
- Published document tidak ditimpa; revisi membuat versi/dokumen baru.
- File DOCX/PDF/XLSX/CSV disimpan privat dan diunduh lewat route authorized.
- Email distribusi memakai feature flag dan kegagalan email tidak membatalkan publish.

Status dokumen: `draft`, `generating`, `generated`, `under_review`, `approved`, `published`, `failed`, `cancelled`, `superseded`, `archived`.
