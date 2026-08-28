# MY PKPA Logbook Workflow Spec

## Alur

1. Mahasiswa menyimpan draft logbook per tanggal/periode.
2. Lampiran dapat diunggah sebelum submit dan disimpan pada disk non-public.
3. Mahasiswa submit logbook ke Preseptor.
4. Preseptor approve, minta revisi, atau reject.
5. Pembimbing Dalam memberi catatan monitoring setelah approved lapangan.

## Keamanan Lampiran

Download lampiran wajib melalui controller protected. Akses hanya untuk mahasiswa pemilik rotasi, Preseptor/Dalam aktif, Admin, dan Koordinator. Path storage tidak diekspos sebagai URL publik.

## Relasi Ke Kompetensi

Logbook dapat dijadikan referensi evidence kompetensi hanya jika berasal dari runtime rotasi yang sama. Evidence tidak menduplikasi file fisik logbook.

