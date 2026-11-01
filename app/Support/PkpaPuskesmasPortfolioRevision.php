<?php

namespace App\Support;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PkpaPuskesmasPortfolioRevision
{
    public const TEMPLATE_CODE = 'PORT-PKM-v2';

    public static function reference(): array
    {
        static $data;

        return $data ??= json_decode(file_get_contents(database_path('data/pkpa_puskesmas_portfolio_2026_11.json')), true, 512, JSON_THROW_ON_ERROR);
    }

    public static function sections(): array
    {
        $t = fn ($name, $label, $columns, $minimum = 1, $required = true) => compact('name', 'label', 'columns', 'minimum', 'required') + ['type' => 'table', 'rows' => 3];
        $f = fn ($name, $label, $required = true) => compact('name', 'label', 'required') + ['type' => 'textarea', 'rows' => 4];
        $definitions = [
            'regulation_organization' => ['PORTOFOLIO I - Aspek Regulasi dan Struktur Organisasi', [
                $t('regulations', 'Tugas 1 - Peraturan dan perundang-undangan farmasi', ['Nama/No. Peraturan', 'Mengatur Tentang']),
                $f('organization', 'Tugas 2 - Struktur organisasi Puskesmas dan tautan bagan'),
                $t('roles', 'Tugas 3 - Tugas pokok dan fungsi bagian Puskesmas', ['Nama bidang', 'Tugas pokok dan fungsi', 'Uraian kegiatan kefarmasian']),
                $t('pharmacy_roles', 'Tugas 4 - Organisasi ruang farmasi (N/A dengan alasan jika tidak ada)', ['Nama bidang', 'Tugas pokok dan fungsi', 'Uraian kegiatan kefarmasian']),
            ]],
            'pharmaceutical_supplies' => ['PORTOFOLIO II - Aspek Pengelolaan Perbekalan Farmasi', [
                $f('planning', 'Perencanaan - Acuan pemilihan, formularium dan tautannya, peninjauan/perubahan, metode, data, frekuensi pengadaan, simulasi LPLPO dan evaluasi ABC/VEN'),
                $t('annual_plan', 'Perencanaan 10 obat terbanyak dari LPLPO 3 bulan terakhir untuk 1 tahun', ['Nama obat', 'Bulan 1', 'Bulan 2', 'Bulan 3', 'Total', 'Rata-rata bulanan', 'Sisa stok', 'Rencana 1 tahun'], 10),
                $f('request', 'Permintaan - Metode, 10 contoh rutin, 10 contoh mandiri, obat program, dan tautan dokumen'),
                $f('receipt', 'Penerimaan - Sumber obat, aspek pemeriksaan, dan tautan dokumen penerimaan'),
                $t('receipt_checks', 'Penerimaan obat - Kesesuaian jenis, jumlah, ED, dan suhu pengiriman', ['Nama barang', 'Jenis sesuai/tidak', 'Jumlah sesuai/tidak', 'ED sesuai/tidak', 'Suhu pengiriman sesuai/tidak']),
                $f('storage', 'Penyimpanan - Metode, foto lemari narkotika/psikotropika, dan bagan penyimpanan (tautan)'),
                $t('storage_locations', 'Penyimpanan - Tempat dan masing-masing 10 jenis obat, alkes, atau BMHP', ['Tempat penyimpanan', 'Daftar 10 jenis obat/alkes/BMHP']),
                $t('storage_problems', 'Penyimpanan - Permasalahan dan solusi (N/A dengan alasan jika tidak ada)', ['Nama obat', 'Masalah penyimpanan', 'Solusi']),
                $f('distribution', 'Distribusi - Jaringan, subunit, sistem distribusi, dan tautan dokumen'),
                $t('destruction', 'Pemusnahan - Alasan, metode, dokumentasi, dan tautan dokumen (N/A dengan alasan bila tidak ada)', ['Nama barang', 'Jumlah', 'Alasan', 'Metode', 'Tautan dokumen/bukti']),
                $f('recall', 'Penarikan - Alasan, surat/perintah, dan tanda terima barang (tautan atau N/A dengan alasan)'),
                $f('stock_control', 'Pengendalian - Kartu stok dan dokumen stok opname (tautan)'),
                $t('stock_problems', 'Pengendalian - Permasalahan stok opname dan tindak lanjut', ['Permasalahan', 'Tindak lanjut']),
                $t('special_medicines', 'Administrasi - Narkotika, psikotropika, prekursor, OOT, dan obat program; identitas wajib disamarkan', ['Kategori', 'Tanggal', 'Nama obat', 'BSO', 'Jumlah', 'Kode dokter', 'Kode pasien', 'Umur/JK']),
                $t('damaged_stock', 'Administrasi - Obat rusak atau kedaluwarsa', ['Nama obat', 'BSO', 'Keadaan rusak/kedaluwarsa', 'Jumlah', 'Tindak lanjut']),
                $f('procedures', 'Pemantauan - Daftar seluruh SPO ruang farmasi'),
                $t('fornas', 'Pemantauan - Evaluasi penggunaan Fornas', ['Tahun', 'Item sesuai Fornas', 'Total item tersedia', 'Persentase kesesuaian']),
                $f('other_evaluation', 'Pemantauan - Evaluasi lain di ruang farmasi'),
            ]],
            'clinical_services' => ['PORTOFOLIO III - Aspek Pelayanan Farmasi Klinik', [
                $t('prescriptions', 'Tugas 1-8 untuk 10 resep berbeda; gunakan kode pasien, bukan nama/nomor rekam medis', ['Kode resep/pasien', 'Tautan resep tersamarkan', 'Pengkajian administratif/farmasetik/klinis dan penyelesaian masalah', 'Perhitungan dosis standar, sekali dan sehari', 'Perhitungan harga satuan/jumlah/subtotal', 'Komposisi, indikasi, mekanisme, kontraindikasi, interaksi, bentuk dan kekuatan', 'Etiket dan tautan foto', 'Copy resep atau alasan tidak diperlukan', 'Rekam penggunaan obat dan data klinis anonim'], 10),
                $t('pio', 'Pelayanan Informasi Obat - 5 pasien', ['Kode pasien/PIO dan tanggal', 'Metode dan status penanya anonim', 'Data klinis relevan', 'Pertanyaan dan jenis pertanyaan', 'Jawaban, referensi, dan tindak lanjut'], 5),
                $t('counselling', 'Konseling - 5 pasien', ['Kode pasien dan tanggal', 'Diagnosis, obat, dosis dan cara pakai', 'Materi, hambatan dan konfirmasi pemahaman', 'Tindak lanjut pasien/apoteker'], 5),
                $t('ward_round', 'Visite - 3 pasien hanya bila tersedia rawat inap; pilih N/A dengan alasan jika tidak tersedia', ['Kode pasien/tanggal/jenis visite', 'Data klinis anonim dan diagnosis', 'Kegiatan, temuan dan rekomendasi'], 3),
                $t('meso', 'Monitoring efek samping obat - Kasus dan Naranjo assessment', ['Kasus anonim dan obat', 'Penilaian Naranjo per pertanyaan dan total', 'Kesimpulan/tindak lanjut', 'Tautan screenshot e-MESO tersamarkan']),
                $t('pto', 'Pemantauan Terapi Obat - 3 pasien', ['Kode pasien dan tanggal', 'Diagnosis, alergi dan riwayat penyakit', 'Riwayat obat dan keluhan', 'Analisis, rekomendasi, pemantauan dan tindak lanjut'], 3),
                $t('drug_use_evaluation', 'Evaluasi Penggunaan Obat - Indikator peresepan, batasan dan hasil', ['Indikator', 'Batasan/target', 'Hasil', 'Analisis dan tindak lanjut']),
            ]],
            'pharmacy_resources' => ['PORTOFOLIO IV - Sumber Daya Kefarmasian', [
                $t('staff', 'Jumlah tenaga kefarmasian', ['Kategori apoteker/TTK/TVF/asisten', 'Jumlah', 'Tugas']),
                $t('monthly_service', 'Pasien/resep per bulan selama 6 bulan terakhir', ['Bulan', 'Jumlah pasien', 'Jumlah resep'], 6),
                $f('staff_analysis', 'Analisis kesesuaian tenaga dengan rata-rata resep per bulan dan perhitungan tambahan tenaga jika kurang'),
            ]],
            'service_quality' => ['PORTOFOLIO V - Mutu Pelayanan Kefarmasian', [
                $t('quality', 'Evaluasi mutu pelayanan kefarmasian', ['Indikator/evaluasi', 'Target', 'Hasil', 'Analisis kesenjangan', 'Rencana perbaikan']),
            ]],
            'patient_safety' => ['PORTOFOLIO VI - Keselamatan Pasien', [
                $t('incidents', 'Klasifikasi insiden nyata atau kasus simulasi; tanpa identitas pasien', ['Uraian kasus anonim', 'Klasifikasi insiden', 'Keterangan dan pencegahan']),
            ]],
            'infection_prevention' => ['PORTOFOLIO VII - Pencegahan dan Pengendalian Infeksi', [
                $f('pharmacist_role', 'Peranan apoteker dalam pencegahan dan pengendalian infeksi di Puskesmas'),
            ]],
            'puskesmas_self_assessment' => ['Self Assessment dan Refleksi Diri Puskesmas', [
                ['name' => 'ratings', 'label' => '20 kompetensi - Skor 1-4 atau N/A disertai alasan', 'type' => 'puskesmas_ratings', 'required' => true],
                $f('strength', '1. Kompetensi/kegiatan yang paling dikuasai'), $f('weakness', '2. Kompetensi/kegiatan yang perlu ditingkatkan'),
                $f('meaningful_experience', '3. Pengalaman/kasus paling bermakna dan pembelajaran'), $f('obstacles', '4. Kendala dan cara mengatasinya'),
                $t('improvement_plan', '5. Rencana tindak lanjut pengembangan kompetensi', ['Kompetensi', 'Strategi/kegiatan', 'Target waktu', 'Indikator keberhasilan']),
            ]],
        ];

        return collect($definitions)->map(fn ($d) => ['title' => $d[0], 'description' => 'Revisi 1 November 2026. Identitas pasien wajib disamarkan. N/A harus disertai alasan.', 'source_type' => 'structured_form', 'reviewer_type' => 'internal', 'is_required' => true, 'fields' => $d[1]])->all();
    }

    public static function templateSections(): array
    {
        $sections = array_slice(PkpaPuskesmasPortfolio::templateSections(), 0, 7);
        foreach (self::sections() as $code => $d) {
            $sections[] = [$code, $d['title'], 'structured_form', 'internal', true];
        }

        return array_merge($sections, [
            ['daily_logbook', 'Logbook Harian', 'auto_logbook', 'internal', true],
            ['documentation', 'Dokumentasi Kegiatan', 'evidence_gallery', 'internal', true],
            ['bibliography', 'Daftar Pustaka', 'structured_form', 'internal', false],
            ['attachments', 'Lampiran', 'attachment_list', 'all', false],
        ]);
    }

    public static function clean(string $code, array $payload): array
    {
        $fields = self::sections()[$code]['fields'] ?? [];
        $rules = [];
        foreach ($fields as $field) {
            $key = $field['name'];
            if ($field['type'] === 'table') {
                $rules[$key] = ['nullable', 'array', 'max:100'];
                $rules[$key.'.*'] = ['array:'.implode(',', array_keys($field['columns']))];
                foreach (array_keys($field['columns']) as $column) {
                    $rules[$key.'.*.'.$column] = ['nullable', 'string', 'max:10000'];
                }
                $rules[$key.'_na'] = ['nullable', 'string', 'max:2000'];
            } elseif ($field['type'] === 'puskesmas_ratings') {
                $rules[$key] = ['nullable', 'array:'.implode(',', array_column(self::reference()['self_assessment'], 'code'))];
                foreach (self::reference()['self_assessment'] as $criterion) {
                    $base = $key.'.'.$criterion['code'];
                    $rules[$base] = ['nullable', 'array:score,evidence,reason'];
                    $rules[$base.'.score'] = ['nullable', Rule::in(['1', '2', '3', '4', 'NA'])];
                    $rules[$base.'.evidence'] = ['nullable', 'string', 'max:4000'];
                    $rules[$base.'.reason'] = ['nullable', 'string', 'max:2000'];
                }
            } else {
                $rules[$key] = ['nullable', 'string', 'max:30000'];
            }
        }
        $clean = Validator::make($payload, $rules)->validate();
        foreach ($fields as $field) {
            if ($field['type'] === 'table') {
                $clean[$field['name']] = array_values(array_filter($clean[$field['name']] ?? [], fn ($row) => count(array_filter($row, fn ($v) => filled($v))) > 0));
            }
        }
        if ($code === 'puskesmas_self_assessment') {
            $numeric = collect($clean['ratings'] ?? [])->filter(fn ($r) => in_array((string) ($r['score'] ?? ''), ['1', '2', '3', '4'], true));
            $clean['_result'] = ['assessed' => $numeric->count(), 'sum' => $numeric->sum('score'), 'score' => $numeric->isNotEmpty() ? round($numeric->sum('score') / ($numeric->count() * 4) * 100, 2) : null];
        }

        return $clean;
    }

    public static function completed(string $code, array $payload): bool
    {
        foreach (self::sections()[$code]['fields'] ?? [] as $field) {
            $key = $field['name'];
            if ($field['type'] === 'table') {
                if (filled($payload[$key.'_na'] ?? null)) {
                    continue;
                }
                $rows = collect($payload[$key] ?? [])->filter(fn ($row) => count(array_filter($row, fn ($v) => filled($v))) === count($field['columns']));
                if ($rows->count() < $field['minimum']) {
                    return false;
                }
            } elseif ($field['type'] === 'puskesmas_ratings') {
                foreach (self::reference()['self_assessment'] as $criterion) {
                    $rating = $payload[$key][$criterion['code']] ?? [];
                    if (($rating['score'] ?? '') === 'NA') {
                        if (blank($rating['reason'] ?? null)) {
                            return false;
                        }
                    } elseif (! in_array((string) ($rating['score'] ?? ''), ['1', '2', '3', '4'], true) || blank($rating['evidence'] ?? null)) {
                        return false;
                    }
                }
                if (! data_get($payload, '_result.assessed')) {
                    return false;
                }
            } elseif (blank($payload[$key] ?? null)) {
                return false;
            }
        }

        return true;
    }

    public static function lines(string $code, array $payload): array
    {
        $lines = [];
        foreach (self::sections()[$code]['fields'] ?? [] as $field) {
            $value = $payload[$field['name']] ?? null;
            $lines[] = $field['label'];
            if ($field['type'] === 'table') {
                if (filled($payload[$field['name'].'_na'] ?? null)) {
                    $lines[] = 'N/A - '.$payload[$field['name'].'_na'];
                }
                foreach ($value ?? [] as $i => $row) {
                    foreach ($field['columns'] as $j => $label) {
                        $lines[] = ($i + 1).'. '.$label.': '.($row[$j] ?? '-');
                    }
                }
            } elseif ($field['type'] === 'puskesmas_ratings') {
                foreach (self::reference()['self_assessment'] as $criterion) {
                    $r = $value[$criterion['code']] ?? [];
                    $lines[] = $criterion['title'].' | '.($r['score'] ?? '-').(($r['score'] ?? '') === 'NA' ? ' | Alasan: '.($r['reason'] ?? '-') : '/4 | Bukti: '.($r['evidence'] ?? '-'));
                }
                $lines[] = 'Nilai self-assessment: '.(data_get($payload, '_result.score') ?? '-').'/100; jumlah aspek dinilai: '.data_get($payload, '_result.assessed', 0).'; N/A dikeluarkan dari penyebut.';
            } else {
                $lines[] = filled($value) ? $value : 'Belum diisi.';
            }
        }

        return $lines;
    }
}
