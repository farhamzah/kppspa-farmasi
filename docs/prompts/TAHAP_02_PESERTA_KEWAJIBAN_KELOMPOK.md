# TAHAP 02 - Peserta, Kewajiban Wahana, Kelompok, dan Import Core

Instruksi tahap:

- Implementasi dilakukan di `apps/kppspa-farmasi`.
- `apps/core-farmasi` hanya boleh dibaca untuk kontrak integrasi.
- Enrollment memakai `core_user_id`, bukan email.
- Jangan membuat akun lokal, password, atau role.
- Buat enam requirement otomatis untuk setiap enrollment.
- Pemerintahan adalah satu requirement `choose_one`; Loka POM dan Dinas Kesehatan bukan dua requirement terpisah.
- Kelompok mahasiswa harus menyimpan histori membership.
- Import CSV/XLSX wajib preview sebelum final, menolak password/role, dan hanya memproses row valid.
- Sinkronisasi Core memperbarui snapshot tanpa mengubah `core_user_id`.
- Fitur war tetap terkunci.
- Tabel legacy `kp_*` tidak dihapus atau dimigrasikan.
- Jalankan QA: `php artisan optimize:clear`, `php artisan migrate:fresh --seed --force`, `php artisan test`, `npm run build`, dan `php artisan route:list --path=management/pkpa`.
