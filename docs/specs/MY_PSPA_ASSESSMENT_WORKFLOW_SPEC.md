# MY PSPA Assessment Workflow Spec

Tahap 08 menambahkan penilaian akademik per rotasi/wahana PKPA. Modul ini tidak menghitung nilai akhir keseluruhan Program PKPA, tidak menerbitkan huruf mutu akhir, dan tidak mengubah status requirement menjadi completed.

## Struktur

- `pkpa_assessment_schemes` menyimpan versi skema per program domain.
- `pkpa_assessment_components` menyimpan komponen dan bobot.
- `pkpa_assessment_rubrics`, `pkpa_assessment_rubric_criteria`, dan `pkpa_assessment_rubric_levels` menyimpan struktur rubrik.
- `pkpa_rotation_assessments` menyimpan instance assessment per runtime rotasi dengan snapshot skema.
- `pkpa_rotation_assessment_assessors` menyimpan penilai yang ditetapkan dari supervisor aktif.
- `pkpa_rotation_component_scores` dan `pkpa_rotation_rubric_scores` menyimpan nilai dan snapshot komponen/rubrik.
- `pkpa_assessment_moderations` menyimpan review/moderasi koordinator.
- `pkpa_rotation_grade_results` menyimpan hasil final per wahana.
- `pkpa_grade_releases` memisahkan finalisasi dari release kepada mahasiswa.
- `pkpa_grade_change_requests` menyimpan workflow perubahan nilai.

## Aturan Inti

- Skema aktif harus mempunyai total bobot komponen aktif tepat 100%.
- Skema aktif yang sudah menjadi histori tidak diedit destruktif.
- Assessment dibuat idempotent dari rotasi yang sudah `ready_for_assessment`.
- Pembimbing hanya dapat mengisi score assignment miliknya.
- Score submitted dikunci; perubahan berikutnya lewat workflow perubahan nilai.
- Finalisasi hanya dilakukan Koordinator PKPA setelah assessment complete.
- Release nilai terpisah dari finalisasi.
- Portal mahasiswa hanya menampilkan grade release miliknya.
- Nilai yang ditampilkan adalah nilai wahana PKPA, bukan nilai akhir keseluruhan Program PKPA.

## Perhitungan

Kalkulasi menggunakan integer scaling empat desimal:

```text
normalized_score = raw_score / maximum_raw_score * maximum_score
weighted_score = normalized_score * weight_percentage / 100
final_score = sum(weighted_score), dibulatkan sesuai scheme
```

Formula, rounding, dan component snapshot disimpan pada `pkpa_rotation_grade_results`.
