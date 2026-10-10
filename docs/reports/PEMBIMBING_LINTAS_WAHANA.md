# Pergantian Pembimbing Dalam Lintas Wahana

## Perubahan

- Form penggantian memiliki pilihan aktif secara default untuk meneruskan penggantian ke semua penempatan resmi mahasiswa yang dipilih, dengan pembimbing lama yang sama.
- Cakupan tambahan hanya penempatan yang belum berakhir sebelum tanggal serah terima. Penugasan dosen lain tidak ditimpa.
- Ringkasan permintaan perubahan tetap harus diperiksa dan dikonfirmasi sebelum penerapan.
- Command perbaikan mengambil pengganti per mahasiswa dari riwayat wahana sumber, bukan menyamakan seluruh mahasiswa ke satu dosen.
- Draf nilai diteruskan melalui layanan pergantian yang sudah ada; nilai final, presensi, logbook, dan snapshot publikasi lama tidak dihapus.

## Perbaikan Data VPS

Setelah kode tersedia di VPS, jalankan preview:

```bash
php artisan pkpa:carry-supervisor-replacement --program=PKPA-2026-G1 --old-core-id=416
```

Periksa bahwa pengganti mengikuti Apotek masing-masing mahasiswa (Core 392: Andi; Core 369: Anggun). Tanggal efektif wahana berikutnya memakai tanggal mulai wahana bila lebih akhir dari serah terima Apotek.

Setelah backup database dan preview diperiksa:

```bash
php artisan pkpa:carry-supervisor-replacement --program=PKPA-2026-G1 --old-core-id=416 --apply
php artisan pkpa:carry-supervisor-replacement --program=PKPA-2026-G1 --old-core-id=416
```

Command hanya menangani penempatan yang sudah ada di publikasi resmi terkini program tersebut. Wahana yang belum direncanakan/dipublikasikan tidak diciptakan oleh command; saat penempatan baru disusun, gunakan pembimbing pengganti mahasiswa yang sama.

## Pengamanan

- Default read-only; `--apply` wajib eksplisit.
- Tidak menebak bila riwayat pengganti hilang atau ganda.
- Memblokir pengganti yang tidak eligible/akun Core inactive di wahana tujuan.
- Revisi seluruh kelompok dibungkus transaksi; publikasi dan penugasan dicek ulang sebelum penerapan.
- Menggunakan audit/revisi/sinkronisasi runtime yang sudah ada.
- Dapat dijalankan ulang tanpa menciptakan revisi tambahan bila tidak ada sasaran.
- Tidak mematikan akun Derry atau mengubah program lain.

## Verifikasi

Feature tests pada `Tahap05PkpaPublicationPortalTest`: preview tanpa perubahan, penerapan lintas wahana, idempotensi, riwayat/draf penilaian, pembimbing lain tidak tertimpa, dan blokir mapping/eligibility tidak valid.

Data produksi belum diterapkan dari workspace lokal; hasil akhir harus diperiksa dari output command VPS.
