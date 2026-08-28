# KP-15 - Cross-App Contract Design & Core Role Alignment

Tanggal: 2026-06-01

## Ringkasan
KP-15 memformalkan kontrak lintas aplikasi setelah KP-14. Fokusnya adalah role translation Core ke KP, policy field authority, spesifikasi mapping coverage, kontrak dokumen KP ke TU, dan whitelist informasi publik KP untuk SAFA.

Tahap ini tetap tidak membuat write bridge aktif, tidak menulis ke Core/TU/SAFA, tidak membuat SSO/autologin/token URL, dan tidak memindahkan semua data sekaligus.

## Implementasi Kode
Dibuat helper:

```text
app/Support/CoreRoleTranslator.php
```

Fungsi utama:
- `toKp()`
- `toCore()`
- `coreRolesToKp()`
- `kpRolesToCore()`

Dibuat command read-only:

```bash
php artisan kp:core-mapping-coverage
```

Output command:
- total user
- mapped user
- unmapped user
- possible duplicate email
- role mismatch
- missing identifier
- read-only counts unchanged

Test:
- `tests/Feature/CoreRoleTranslatorTest.php`
- `tests/Feature/CoreMappingCoverageCommandTest.php`

## Mapping Role Core ke KP
| Core | KP |
|---|---|
| `admin-kp` | `admin` |
| `mahasiswa` | `mahasiswa` |
| `dosen` | `pembimbing_dalam` |
| `koordinator-kp` | `koordinator_kp` |
| `pembimbing-dalam` | `pembimbing_dalam` |
| `pembimbing-lapangan` | `pembimbing_lapangan` |
| `penguji` | `penguji` |

`admin-core` sengaja ditolak dan tidak diterjemahkan menjadi admin KP.

Detail kontrak ada di:

```text
docs/integration/KP-CORE-ROLE-TRANSLATION-CONTRACT.md
```

## Policy Field Authority
Keputusan utama:
- Core authoritative untuk user identity, email, NIM, NIDN/NIP, prodi, dosen, dan status aktif lintas aplikasi.
- KP authoritative untuk data operasional KP: periode, tempat, kuota, pendaftaran, dokumen KP, selection, assignment, logbook, laporan akhir, sidang, nilai, dan audit logs.
- KP boleh menyimpan snapshot transaksi untuk dokumen/arsip historis.
- Pembimbing lapangan tetap profil operasional KP sampai Core punya model external supervisor khusus.

Detail policy ada di:

```text
docs/integration/KP-CORE-FIELD-AUTHORITY-POLICY.md
```

## Mapping Coverage Diagnostic
Spec report ada di:

```text
docs/integration/KP-CORE-MAPPING-COVERAGE-REPORT-SPEC.md
```

Hasil lokal saat KP-15:
- total user: 8
- mapped user: 8
- unmapped user: 0
- possible duplicate email: 0
- role mismatch: 0
- missing identifier: 0
- read-only counts unchanged: yes

## Kontrak Document Bridge KP ke TU
Dokumen yang dipetakan:
- surat penempatan KP
- surat tugas pembimbing
- surat tugas penguji
- undangan sidang KP
- berita acara sidang KP
- rekap nilai KP
- arsip laporan akhir/final

Prinsip:
- KP mengirim payload/reference bila bridge dibuat nanti.
- TU menjadi pemilik generate/archive/nomor surat.
- KP tidak menggandakan semua upload ke TU.
- Download file privat tetap melalui controller aplikasi pemilik.

Detail kontrak ada di:

```text
docs/integration/KP-TU-DOCUMENT-BRIDGE-CONTRACT.md
```

## Whitelist Public Info KP untuk SAFA
Boleh publik:
- portal card KP;
- periode aktif;
- timeline;
- persyaratan umum;
- pengumuman;
- kontak/admin info umum.

Tidak boleh publik:
- nilai mahasiswa;
- dokumen mahasiswa;
- logbook;
- status individual mahasiswa;
- assignment personal;
- data preseptor sensitif;
- file internal;
- token/secret.

Detail kontrak ada di:

```text
docs/integration/KP-SAFA-PUBLIC-INFO-WHITELIST-CONTRACT.md
```

## File Dibuat/Diubah
Dibuat:
- `app/Support/CoreRoleTranslator.php`
- `app/Console/Commands/CoreMappingCoverageCommand.php`
- `tests/Feature/CoreRoleTranslatorTest.php`
- `tests/Feature/CoreMappingCoverageCommandTest.php`
- `docs/reports/KP-15-CROSS-APP-CONTRACT-DESIGN-CORE-ROLE-ALIGNMENT.md`
- `docs/prompts/PROMPT_KP_15_CROSS_APP_CONTRACT_DESIGN.md`
- `docs/integration/KP-CORE-ROLE-TRANSLATION-CONTRACT.md`
- `docs/integration/KP-CORE-FIELD-AUTHORITY-POLICY.md`
- `docs/integration/KP-CORE-MAPPING-COVERAGE-REPORT-SPEC.md`
- `docs/integration/KP-TU-DOCUMENT-BRIDGE-CONTRACT.md`
- `docs/integration/KP-SAFA-PUBLIC-INFO-WHITELIST-CONTRACT.md`

Diubah:
- `bootstrap/app.php` untuk mendaftarkan command `kp:core-mapping-coverage`.

## Rekomendasi KP-16
KP-16 sebaiknya tetap aman dan bertahap:
1. Buat read-only public-info DTO/export preview untuk SAFA, belum write ke SAFA.
2. Buat draft migration lokal KP untuk external document reference hanya setelah disetujui.
3. Buat dry-run payload generator untuk TU document contracts tanpa mengirim ke TU.
4. Tambahkan UI/admin report mapping coverage jika dibutuhkan UAT.
5. Jalankan staging check dengan `kp:integration-gap-check`, `kp:core-mapping-coverage`, dan `kp:core-mode-preflight`.

## Status Validasi
Validasi akhir:
- `php -l app/Support/CoreRoleTranslator.php`: berhasil, no syntax errors.
- `php -l app/Console/Commands/CoreMappingCoverageCommand.php`: berhasil, no syntax errors.
- `php artisan test --filter=CoreRoleTranslatorTest`: berhasil, 3 passed, 12 assertions.
- `php artisan test --filter=CoreMappingCoverageCommandTest`: berhasil, 1 passed, 9 assertions.
- `php artisan kp:integration-gap-check`: berhasil, read-only counts unchanged.
- `php artisan kp:core-mapping-coverage`: berhasil; total user 8, mapped user 8, unmapped user 0, duplicate 0, role mismatch 0, missing identifier 0.
- `php artisan route:list`: berhasil, 205 routes.
- `php artisan test`: berhasil, 134 passed, 639 assertions.
- `npm run build`: berhasil.

