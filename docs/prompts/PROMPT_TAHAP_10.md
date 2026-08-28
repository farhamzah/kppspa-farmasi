# PROMPT TAHAP 10 - Penilaian KP dan Nilai Akhir

Tahap 10 membangun modul Penilaian KP dan Nilai Akhir setelah modul sidang tersedia. Sebelum modul penilaian, tahap ini juga memperbaiki menu Berkas KP mahasiswa agar memiliki halaman khusus dan active state sidebar yang benar.

## Tujuan
- Menyediakan halaman `/mahasiswa/berkas-kp` untuk melihat, upload, re-upload, dan download dokumen persyaratan KP.
- Menyediakan komponen penilaian fleksibel per periode.
- Mendukung input nilai dari Pembimbing Dalam, Preseptor, dan Penguji.
- Menghitung weighted score dan nilai akhir berdasarkan bobot.
- Menyediakan finalisasi, publish, dan unlock nilai akhir.
- Mahasiswa hanya melihat nilai setelah dipublish.

## Batasan
Tahap ini belum membuat export nilai, berita acara, sertifikat, atau tanda tangan digital.

## Role
- Admin/Koordinator: kelola komponen, monitoring, finalisasi, publish, unlock.
- Pembimbing Dalam: input nilai mahasiswa bimbingan.
- Preseptor: input nilai mahasiswa tugas.
- Penguji: input nilai sidang yang ditugaskan.
- Mahasiswa: melihat nilai jika published.

## Testing
Wajib menjaga test tahap sebelumnya tetap passed dan menambahkan coverage untuk Berkas KP serta penilaian.

