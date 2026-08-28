# PROMPT TAHAP 09 - Pengajuan dan Penjadwalan Sidang KP

Tahap 9 melanjutkan aplikasi SI-KP Farmasi UBP setelah modul laporan akhir selesai. Fokus tahap ini adalah memperbaiki dua bug UI dan membangun modul Pengajuan serta Penjadwalan Sidang KP.

## Tujuan Utama
- Memperbaiki active state sidebar mahasiswa agar menu Pendaftaran KP dan Berkas KP tidak aktif bersamaan.
- Merapikan halaman login agar fit satu viewport desktop/laptop umum.
- Mahasiswa dapat mengajukan sidang setelah laporan akhir disetujui.
- Admin/Koordinator dapat memonitor pengajuan sidang dan menjadwalkan sidang.
- Koordinator/Admin dapat menentukan satu Penguji Sidang.
- Pembimbing Dalam dan Penguji dapat melihat jadwal sidang sesuai penugasannya.
- Semua perubahan penting dicatat pada log sidang.

## Batasan
Tahap ini belum membuat input nilai sidang, perhitungan nilai akhir, berita acara, atau export nilai. Modul tersebut disiapkan untuk Tahap 10.

## Role Terkait
- Mahasiswa: mengajukan sidang dan melihat jadwal.
- Admin/Koordinator KP: monitoring, review pengajuan, jadwal, cancel, complete.
- Pembimbing Dalam: melihat jadwal mahasiswa bimbingannya.
- Penguji: melihat jadwal sidang yang ditugaskan.
- Preseptor: belum punya akses sidang.

## Acceptance Utama
- Pengajuan hanya bisa dilakukan jika laporan akhir sudah disetujui.
- Satu assignment hanya memiliki satu pengajuan/sidang aktif sederhana.
- Penguji wajib role `penguji` dan tidak boleh sama dengan Pembimbing Dalam.
- Validasi jadwal dilakukan server-side.
- Test dan build tetap berjalan.

