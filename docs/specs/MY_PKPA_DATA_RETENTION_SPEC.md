# MY PKPA Data Retention Spec

Default retention Tahap 10 konservatif: data akademik, dokumen published, nilai, kelulusan, logbook, presensi, dan audit tidak dihapus otomatis.

Policy awal:
- draft dokumen dapat dibatalkan, bukan dihapus;
- generated document versions disimpan sebagai histori;
- failed generation dicatat dan dapat diaudit;
- file orphan dideteksi lewat `php artisan pkpa:document-orphan-audit`;
- cleanup wajib dry-run sebelum delete;
- archived program tetap mempertahankan hasil mahasiswa.

Production retention harus disahkan oleh pengelola data sebelum automation destructive diaktifkan.
