# Perbaikan Peringatan Privasi Studi Kasus PBF

## Penyebab yang Ditemukan

Pemeriksaan studi kasus menggunakan pola pasien generik yang mencocokkan kata
`jalan`, `kelurahan`, `kecamatan`, `RM`, `BPJS`, dan `nama pasien` tanpa harus ada
nilai identitas. Akibatnya, istilah operasional PBF seperti `surat jalan` ditolak.
Pemicu pasti pada narasi mahasiswa belum bisa dipastikan karena isi kasusnya
tidak diberikan; salah deteksi pada istilah tersebut direproduksi lewat tes.

## Perubahan

- Pemeriksaan khusus PBF membedakan istilah umum dari nomor telepon, nomor
  identitas/rekam medis, nama pasien setelah label identitas, dan alamat rinci.
- Sebutan umum atau keterangan anonimisasi tidak otomatis dianggap nama pasien.
- Pesan menyebut bagian isian dan jenis temuan, tanpa mengulang data pribadi.
- Isian dipindai satu per satu; metadata dan sambungan antarbidang tidak menjadi
  pemicu semu. Data bersarang tetap diperiksa.
- Penyimpanan dan pemeriksaan pembimbing memakai pemeriksa PBF yang sama.
- Konfirmasi anonimisasi, otorisasi pemilik, proteksi portofolio terkirim, serta
  pemeriksaan identitas langsung tetap ada. Wahana selain PBF tidak berubah.
- Tidak ada perubahan database, pembukaan ulang portofolio, atau perubahan nilai.

## Verifikasi

Tes mencakup istilah distribusi, surat jalan, raw material, rujukan BPJS/rekam
medis generik, anonimisasi, nama dan nomor identitas nyata, telepon biasa dan
internasional, alamat rinci, metadata, data bersarang, penyimpanan dari portal,
penolakan tanpa menimpa isian lama, dan persetujuan oleh Pembimbing Dalam.

Deteksi berupa aturan tekstual, bukan jaminan deteksi semua data pribadi.
Mahasiswa dan pembimbing tetap harus memastikan kerahasiaan narasi/lampiran.

## Penerapan

```bash
cd /var/www/kppspa-farmasi
git pull --ff-only origin main
php artisan optimize:clear
```

Tidak memerlukan migrasi, seeder, atau perintah perbaikan data. Mahasiswa dapat
mencoba menyimpan kembali studi kasus setelah pembaruan; tidak perlu menghapus
isian atau mengubah istilah resmi `surat jalan`.
