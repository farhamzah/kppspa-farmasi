# MY PSPA Placement Validation Rules

## Kelengkapan

Peserta aktif harus memiliki assignment untuk seluruh requirement aktif. Pemerintahan tetap satu requirement `choose_one` dan harus memiliki selected option dari tempat yang dipilih.

## Tempat dan Availability

Tempat program harus aktif/ready, tempat praktik aktif, kerja sama belum berakhir, availability aktif, dan tanggal assignment berada di dalam availability serta rentang program.

## Durasi

Durasi dihitung dari konfigurasi `pkpa_program_domains`. `calendar_days` inklusif. `working_days` memakai `operational_days`; kalender libur nasional belum dihitung otomatis. `weeks` dan `months` memakai operasi kalender. `practice_hours` memakai jam operasional jika lengkap.

## Kapasitas

Kapasitas dihitung per availability:

```text
maximum_students - reserved_slots - assignment overlap aktif pada plan yang sama
```

Assignment `cancelled` dan `superseded` tidak dihitung.

## Pembimbing

Pembimbing Dalam harus eligible untuk program-wahana. Pembimbing Lapangan harus milik tempat. Status, masa efektif, unavailability, dan batas beban divalidasi backend.

## Jadwal Mahasiswa

Overlap terdeteksi bila:

```text
assignment_a.start_date <= assignment_b.end_date
AND assignment_a.end_date >= assignment_b.start_date
```

Sistem memberi issue dan saran, tidak memindahkan jadwal otomatis.

## Final Review Publication

Sebelum publish, final review memastikan plan tervalidasi, terkunci, tidak memiliki error aktif, seluruh requirement peserta aktif terisi, pilihan Pemerintahan lengkap, dan setiap assignment memiliki Pembimbing Dalam serta Pembimbing Lapangan. Warning boleh tercatat, tetapi error aktif memblokir publication.
