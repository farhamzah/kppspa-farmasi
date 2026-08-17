# MY PKPA Site Capacity and Supervisor Spec

## Scope

Tahap 03 mengelola tempat tersedia per Program PKPA, periode availability, kapasitas, Pembimbing Lapangan, Pembimbing Dalam, unavailability pembimbing, dan sinkronisasi snapshot dari Core Farmasi.

Tahap ini tidak membuat penempatan mahasiswa, urutan rotasi, jadwal rotasi, assignment supervisor ke peserta, logbook rotasi, nilai, atau migrasi legacy `kp_*`.

## Tabel

- `pkpa_program_sites`: menghubungkan program dengan tempat praktik dan wahana.
- `pkpa_site_availability_periods`: rentang availability tempat, kapasitas, reserved slot, hari operasional, jam praktik, dan status.
- `pkpa_site_field_supervisors`: Pembimbing Lapangan dari Core per tempat praktik.
- `pkpa_internal_supervisor_eligibilities`: Pembimbing Dalam dari Core per program-wahana.
- `pkpa_supervisor_unavailability_periods`: periode tidak tersedia Pembimbing Dalam atau Pembimbing Lapangan.
- `pkpa_supervisor_sync_logs`: log sinkronisasi snapshot pembimbing.

## Aturan Tempat Program

Tempat hanya dapat ditambahkan bila:

- program belum `completed` atau `archived`;
- tempat praktik aktif;
- kerja sama tempat belum berakhir;
- wahana tempat aktif pada program;
- program dan tempat belum pernah terhubung sebelumnya.

Tempat Pemerintahan tetap memakai option Loka POM atau Dinas Kesehatan dari master tempat praktik.

## Aturan Availability

Availability harus berada di dalam rentang program dan rentang kerja sama mitra. Periode tidak boleh overlap dengan availability aktif lain pada tempat program yang sama. Reserved slot tidak boleh melebihi kapasitas maksimum.

Status availability:

- `draft`
- `available`
- `full`
- `closed`
- `cancelled`

## Aturan Pembimbing

Pembimbing wajib berasal dari Core Farmasi dan divalidasi melalui `core_user_id`.

Pembimbing Dalam valid bila akun Core aktif, role termasuk `pembimbing_dalam`/dosen, dan memiliki app access MY PKPA.

Pembimbing Lapangan valid bila akun Core aktif, role termasuk `pembimbing_lapangan`/preseptor, dan memiliki app access MY PKPA.

MY PKPA hanya menyimpan snapshot nama, email, status akun, role, identitas profesi/dosen, batas beban, dan waktu sync. Password, token, dan assignment role lokal tidak disimpan.

## Unavailability dan Sync

Unavailability mencatat rentang tanggal dan alasan. Pembatalan mengubah status menjadi `cancelled`, bukan menghapus record.

Sync pembimbing memperbarui snapshot jika Core tersedia. Jika Core tidak tersedia atau data tidak valid, snapshot lama tidak dikosongkan dan status sync dicatat.

## Dipakai oleh Planner Tahap 04

Penyusunan Penempatan menghitung kapasitas terhadap `pkpa_site_availability_periods`:

```text
slot tersedia = maximum_students - reserved_slots - assignment overlap aktif pada plan yang sama
```

Assignment `cancelled` dan `superseded` tidak dihitung. Plan lain belum dianggap slot aktif pada Tahap 04 kecuali aturan publikasi tahap berikutnya menentukannya.

Pembimbing Dalam harus berasal dari `pkpa_internal_supervisor_eligibilities` yang sesuai program dan wahana. Pembimbing Lapangan harus berasal dari `pkpa_site_field_supervisors` pada tempat yang dipilih. Planner memvalidasi status, masa efektif, unavailability, dan beban sebelum menyimpan assignment.
