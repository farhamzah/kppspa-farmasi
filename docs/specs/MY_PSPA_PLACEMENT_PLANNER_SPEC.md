# MY PSPA Placement Planner Spec

## Scope

Planner menyusun rancangan penempatan internal PKPA. Output hanya `draft`, `needs_revision`, `validated`, atau `locked`; tidak ada status `published`.

## Data Utama

- `pkpa_placement_plans`: versi rancangan per program.
- `pkpa_rotation_assignments`: assignment individual per requirement.
- `pkpa_rotation_assignment_supervisors`: PD/PL per assignment.
- `pkpa_placement_action_batches`: preview/apply/undo bulk.
- `pkpa_placement_validation_runs`: eksekusi validasi.
- `pkpa_placement_validation_issues`: issue yang dapat diklik dari matriks.

## Versioning

Nomor versi unik per program. Satu current plan dijaga melalui `current_key = PROGRAM:{id}` dalam transaction. Plan locked tidak diedit; revisi dibuat dengan clone.

## Assignment

Assignment wajib konsisten dengan program, enrollment, requirement, domain, tempat, availability, tanggal, dan pilihan Pemerintahan. Bulk/group tetap membuat assignment individual.

## Bulk dan Undo

Bulk action selalu membuat preview. Apply dapat all-or-nothing atau valid-only. Undo hanya aman jika plan belum locked dan data target belum berubah setelah batch diterapkan.

## Export

Export `.xlsx` berisi matriks, detail, kapasitas, pembimbing, dan issue validasi dengan label "Rancangan Internal - Belum Dipublikasikan".

## Handoff ke Publication

Tahap 05 memakai plan `locked` sebagai input publication resmi. Planner tetap menjadi area draft internal; setelah publish, mahasiswa dan pembimbing membaca `pkpa_published_assignments`, bukan assignment planner.
