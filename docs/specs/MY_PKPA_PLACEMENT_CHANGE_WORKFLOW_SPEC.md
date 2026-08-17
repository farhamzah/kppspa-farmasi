# MY PKPA Placement Change Workflow Spec

Change request dipakai untuk perubahan setelah jadwal resmi dipublikasikan.

## Status

- `draft`: dibuat admin/koordinator dan masih dapat dilengkapi.
- `submitted`: diajukan untuk review.
- `approved`: disetujui Koordinator PKPA.
- `rejected`: ditolak dengan alasan.
- `applied`: sudah menghasilkan publication revision baru.
- `failed`: apply gagal dan perlu ditangani manual.

## Aturan

- Change request selalu terkait publication sumber.
- Item menyimpan `before_snapshot` dan `proposed_snapshot`.
- Apply hanya boleh dari status `approved`.
- Apply membuat publication baru dengan `revision_number` berikutnya.
- Publication sumber menjadi `superseded`; publication revision menjadi current.
- Snapshot publication sumber tidak berubah.

## Scope Tahap 05

UI tahap ini menyediakan perubahan tanggal/catatan dasar. Struktur tabel sudah siap diperluas untuk perubahan tempat atau pembimbing tanpa mengubah snapshot lama.
