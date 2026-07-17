# MY PSPA Rotation Publication Sync Spec

Sinkronisasi publikasi menjaga runtime rotasi tetap mengikuti publikasi current tanpa menghapus histori.

## Keputusan Sinkronisasi

- Tidak ada perubahan: log `ignored`.
- Rotasi `ready/scheduled`: perubahan tempat/tanggal/pembimbing dapat diterapkan langsung.
- Rotasi aktif: perubahan pembimbing dapat diterapkan, tetapi perubahan tempat/tanggal menjadi `review_required`.
- Assignment hilang dari publikasi current: runtime ditandai `review_required`.

Semua keputusan dicatat di `pkpa_rotation_publication_sync_logs`.
