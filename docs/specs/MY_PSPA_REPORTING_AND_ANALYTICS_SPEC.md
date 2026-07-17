# MY PSPA Reporting and Analytics Spec

Modul `Pelaporan dan Analytics` menampilkan agregat aktual dari tabel PKPA:
- program dan peserta;
- penempatan dan publication;
- operasional rotasi;
- penilaian wahana dan nilai akhir;
- kelulusan dan remedial;
- dokumen.

Filter awal: program. Export CSV menerapkan formula-injection guard untuk sel yang dimulai `=`, `+`, `-`, atau `@`.

Prinsip:
- Tidak menghitung draft akademik sebagai hasil final mahasiswa.
- Tidak memperluas authorization lewat cache/export.
- Indikator hanya ditampilkan bila dapat ditelusuri ke tabel sumber.
- Analytics production tetap perlu performance profiling dengan data besar sebelum UAT luas.
