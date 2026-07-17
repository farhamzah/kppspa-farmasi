# MY PSPA Document Numbering Spec

Penomoran dokumen dikonfigurasi melalui `pkpa_document_numbering_rules`. MY PSPA tidak menyediakan format nomor surat resmi default.

Token teknis yang didukung:
- `{sequence}`
- `{type}`
- `{program}`
- `{month}`
- `{year}`
- `{prefix}`
- `{suffix}`

Alokasi nomor dilakukan saat publish untuk jenis dokumen yang `requires_number=true`. Service memakai transaction dan row lock pada dokumen/rule agar sequence tidak dipakai ganda. Nomor yang sudah dialokasikan tidak dikurangi ulang walaupun publish/generation bermasalah.

Draft boleh belum bernomor. Dokumen cancelled tetap menyimpan nomor sebagai histori.
