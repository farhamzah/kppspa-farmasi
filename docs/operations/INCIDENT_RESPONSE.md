# MY PSPA Incident Response

Langkah awal:
1. Aktifkan maintenance mode jika data/file berisiko.
2. Cabut access token/credential terdampak melalui secret manager.
3. Simpan log/audit terkait tanpa menyebarkan data sensitif.
4. Identifikasi scope akun, dokumen, file, dan perubahan data.
5. Restore dari backup terverifikasi bila perlu.
6. Jalankan regression security dan browser smoke.
7. Dokumentasikan RCA dan tindakan permanen.

Insiden dokumen:
- jangan hapus published document;
- cancel/supersede dengan alasan;
- simpan nomor lama sebagai histori.
