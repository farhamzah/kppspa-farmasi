<?php

namespace App\Support;

class PkpaApotekPortfolio
{
    public static function isApotekCode(?string $code): bool
    {
        return strtoupper((string) $code) === 'APT';
    }

    public static function editableSections(): array
    {
        return [
            'site_profile' => [
                'title' => 'Profil Tempat PKPA',
                'description' => 'Ringkas profil apotek sesuai format portofolio PKPA Apotek.',
                'source_type' => 'structured_form',
                'reviewer_type' => 'field_internal',
                'is_required' => true,
                'fields' => [
                    ['name' => 'overview', 'label' => 'Gambaran Umum Apotek', 'type' => 'textarea', 'rows' => 5],
                    ['name' => 'history', 'label' => 'Sejarah Singkat Apotek', 'type' => 'textarea', 'rows' => 4],
                    ['name' => 'vision', 'label' => 'Visi', 'type' => 'textarea', 'rows' => 3],
                    ['name' => 'mission', 'label' => 'Misi', 'type' => 'textarea', 'rows' => 4],
                    ['name' => 'facilities', 'label' => 'Sarana dan Prasarana', 'type' => 'textarea', 'rows' => 4],
                    ['name' => 'human_resources', 'label' => 'Sumber Daya Manusia', 'type' => 'textarea', 'rows' => 4],
                    ['name' => 'operational_hours', 'label' => 'Jam Operasional', 'type' => 'text'],
                    ['name' => 'pharmacy_services', 'label' => 'Pelayanan Kefarmasian', 'type' => 'textarea', 'rows' => 5],
                    ['name' => 'supply_management', 'label' => 'Pengelolaan Perbekalan Farmasi', 'type' => 'textarea', 'rows' => 5],
                    ['name' => 'information_system', 'label' => 'Sistem Informasi Apotek', 'type' => 'textarea', 'rows' => 4],
                    ['name' => 'prescription_service_types', 'label' => 'Jenis Pelayanan Resep', 'type' => 'textarea', 'rows' => 4],
                    ['name' => 'available_medicines', 'label' => 'Jenis Obat Yang Tersedia', 'type' => 'textarea', 'rows' => 5],
                    ['name' => 'site_analysis', 'label' => 'Analisis Singkat Tempat PKPA', 'type' => 'textarea', 'rows' => 6],
                ],
            ],
            'supply_management' => [
                'title' => 'Laporan Kegiatan: Pengelolaan Sediaan Farmasi',
                'description' => 'Catat tujuan, kegiatan, dan hasil pengelolaan sediaan farmasi di apotek.',
                'source_type' => 'structured_form',
                'reviewer_type' => 'field',
                'is_required' => true,
                'activity_hint' => 'Pilih semua kegiatan pengelolaan sediaan yang dikerjakan.',
                'fields' => self::reportFields([
                    'Perencanaan', 'Pengadaan', 'Penerimaan', 'Penyimpanan', 'FEFO/FIFO', 'Stock Opname', 'Pemusnahan',
                ]),
            ],
            'prescription_services' => [
                'title' => 'Laporan Kegiatan: Pelayanan Resep',
                'description' => 'Isi proses pelayanan resep sesuai praktik di tempat PKPA.',
                'source_type' => 'structured_form',
                'reviewer_type' => 'field',
                'is_required' => true,
                'activity_hint' => 'Pilih semua tahapan pelayanan resep yang dikerjakan.',
                'fields' => self::reportFields([
                    'Skrining Administrasi', 'Skrining Farmasetik', 'Skrining Klinis', 'Penyiapan Obat', 'Pembuatan Etiket', 'Penyerahan Obat', 'Edukasi Pasien',
                ]),
            ],
            'self_medication' => [
                'title' => 'Laporan Kegiatan: Pelayanan Swamedikasi',
                'description' => 'Dokumentasikan kegiatan swamedikasi selama PKPA.',
                'source_type' => 'structured_form',
                'reviewer_type' => 'field',
                'is_required' => true,
                'activity_hint' => 'Pilih semua tahapan pelayanan swamedikasi yang dikerjakan.',
                'fields' => self::reportFields([
                    'Identifikasi Keluhan', 'Anamnesis', 'Identifikasi Red Flag', 'Assessment', 'Rekomendasi Obat', 'Edukasi Pasien', 'Follow-up',
                ]),
            ],
            'counselling' => [
                'title' => 'Laporan Kegiatan: Konseling Pasien',
                'description' => 'Ringkas aktivitas konseling pasien yang dilakukan.',
                'source_type' => 'structured_form',
                'reviewer_type' => 'field',
                'is_required' => true,
                'activity_hint' => 'Pilih semua unsur konseling yang dilakukan.',
                'fields' => self::reportFields([
                    'Identifikasi Kebutuhan Konseling', 'Penjelasan Tujuan Terapi', 'Cara Penggunaan Obat', 'Efek Samping', 'Penyimpanan Obat', 'Konfirmasi Pemahaman', 'Follow-up',
                ]),
            ],
            'pio' => [
                'title' => 'Laporan Kegiatan: Pelayanan Informasi Obat',
                'description' => 'Catat layanan informasi obat yang diberikan selama PKPA.',
                'source_type' => 'structured_form',
                'reviewer_type' => 'field',
                'is_required' => true,
                'activity_hint' => 'Pilih semua tahapan pelayanan informasi obat yang dikerjakan.',
                'fields' => self::reportFields([
                    'Penerimaan Pertanyaan', 'Penggalian Informasi', 'Penelusuran Referensi', 'Analisis Informasi', 'Penyampaian Informasi', 'Dokumentasi', 'Follow-up',
                ]),
            ],
            'narcotics_psychotropics' => [
                'title' => 'Laporan Kegiatan: Pengelolaan Narkotika dan Psikotropika',
                'description' => 'Dokumentasikan penyimpanan, pelaporan, dan dokumentasi narkotika/psikotropika.',
                'source_type' => 'structured_form',
                'reviewer_type' => 'field',
                'is_required' => true,
                'activity_hint' => 'Pilih semua kegiatan pengelolaan narkotika dan psikotropika yang dikerjakan.',
                'fields' => self::reportFields([
                    'Penerimaan', 'Penyimpanan', 'Pencatatan', 'Pelayanan', 'Pelaporan', 'Stock Opname', 'Pemusnahan',
                ]),
            ],
            'administration' => [
                'title' => 'Laporan Kegiatan: Administrasi Kefarmasian',
                'description' => 'Catat administrasi operasional apotek yang dipelajari.',
                'source_type' => 'structured_form',
                'reviewer_type' => 'field',
                'is_required' => true,
                'activity_hint' => 'Pilih semua kegiatan administrasi yang dikerjakan.',
                'fields' => self::reportFields([
                    'Administrasi Penjualan', 'Administrasi Pembelian', 'Pengarsipan Resep', 'Pelaporan', 'Administrasi BPJS', 'Rekonsiliasi Stok',
                ]),
            ],
            'bibliography' => [
                'title' => 'Daftar Pustaka',
                'description' => 'Isi referensi ilmiah atau regulasi yang dipakai dalam portofolio Apotek.',
                'source_type' => 'structured_form',
                'reviewer_type' => 'internal',
                'is_required' => false,
                'fields' => [
                    ['name' => 'references', 'label' => 'Daftar Referensi', 'type' => 'textarea', 'rows' => 5],
                ],
            ],
            'attachments' => [
                'title' => 'Lampiran',
                'description' => 'Catat lampiran yang melengkapi portofolio, bila ada.',
                'source_type' => 'attachment_list',
                'reviewer_type' => 'all',
                'is_required' => false,
                'fields' => [
                    ['name' => 'items', 'label' => 'Daftar Lampiran', 'type' => 'textarea', 'rows' => 5],
                ],
            ],
        ];
    }

    public static function reportSectionCodes(): array
    {
        return [
            'supply_management',
            'prescription_services',
            'self_medication',
            'counselling',
            'pio',
            'narcotics_psychotropics',
            'administration',
        ];
    }

    public static function reportFields(array $activityOptions): array
    {
        return [
            ['name' => 'purpose', 'label' => 'Tujuan', 'type' => 'textarea', 'rows' => 2],
            ['name' => 'selected_activities', 'label' => 'Kegiatan yang Dilaksanakan', 'type' => 'multiselect', 'options' => $activityOptions],
            ['name' => 'activities', 'label' => 'Uraian Kegiatan', 'type' => 'textarea', 'rows' => 4],
            ['name' => 'result', 'label' => 'Hasil', 'type' => 'textarea', 'rows' => 3],
        ];
    }

    public static function sectionDefinition(string $code): ?array
    {
        $sections = self::editableSections();
        return $sections[$code] ?? null;
    }

    public static function templateSections(): array
    {
        return [
            ['cover', 'Sampul', 'static_content', 'all', false, 'Dokumen portofolio PKPA Apotek mengikuti format resmi PSPPA UBP Karawang.'],
            ['approval', 'Lembar Pengesahan', 'approval', 'field_internal', false, 'Lembar pengesahan ditempatkan di bagian depan portofolio dan memerlukan persetujuan Preseptor serta Pembimbing Dalam.'],
            ['vision_mission', 'Visi, Misi, Tujuan, dan Sasaran', 'static_content', 'all', false, 'Mengacu pada Panduan PKPA 2026 Program Studi Pendidikan Profesi Apoteker UBP Karawang.'],
            ['rules', 'Tata Tertib PKPA', 'static_content', 'all', false, 'Mahasiswa mengikuti tata tertib PKPA 2026 dan menyelesaikan laporan sebelum berpindah wahana.'],
            ['identity', 'Identitas Mahasiswa', 'auto_identity', 'all', false, null],
            ['integrity_pact', 'Pakta Integritas Mahasiswa PKPA', 'approval', 'student', false, null],
            ['table_of_contents', 'Daftar Isi', 'static_content', 'all', false, 'Daftar isi dibentuk otomatis mengikuti bagian portofolio PKPA Apotek.'],
            ['site_profile', 'Profil Tempat PKPA', 'structured_form', 'field_internal', true, null],
            ['daily_logbook', 'Logbook Harian', 'auto_logbook', 'field', true, null],
            ['supply_management', 'Laporan Kegiatan: Pengelolaan Sediaan Farmasi', 'structured_form', 'field', true, null],
            ['prescription_services', 'Laporan Kegiatan: Pelayanan Resep', 'structured_form', 'field', true, null],
            ['self_medication', 'Laporan Kegiatan: Pelayanan Swamedikasi', 'structured_form', 'field', true, null],
            ['counselling', 'Laporan Kegiatan: Konseling Pasien', 'structured_form', 'field', true, null],
            ['pio', 'Laporan Kegiatan: Pelayanan Informasi Obat', 'structured_form', 'field', true, null],
            ['narcotics_psychotropics', 'Laporan Kegiatan: Pengelolaan Narkotika dan Psikotropika', 'structured_form', 'field', true, null],
            ['administration', 'Laporan Kegiatan: Administrasi Kefarmasian', 'structured_form', 'field', true, null],
            ['case_report', 'Studi Kasus', 'repeatable_case', 'field', true, null],
            ['weekly_reflection', 'Refleksi Mingguan', 'weekly_reflection', 'internal', true, null],
            ['self_assessment', 'Self Assessment', 'self_assessment', 'internal', true, null],
            ['documentation', 'Dokumentasi Kegiatan', 'evidence_gallery', 'field', true, null],
            ['bibliography', 'Daftar Pustaka', 'structured_form', 'internal', false, null],
            ['attachments', 'Lampiran', 'attachment_list', 'all', false, null],
        ];
    }

    public static function completed(array $payload, string $code): bool
    {
        if (in_array($code, self::reportSectionCodes(), true)) {
            if (array_key_exists('activity_entries', $payload)) {
                return collect($payload['activity_entries'])
                    ->filter(fn ($entry) => is_array($entry))
                    ->isNotEmpty()
                    && collect($payload['activity_entries'])->every(fn ($entry) => filled($entry['activity'] ?? null)
                        && filled($entry['purpose'] ?? null)
                        && filled($entry['description'] ?? null)
                        && filled($entry['result'] ?? null));
            }

            return filled($payload['purpose'] ?? null)
                && filled($payload['selected_activities'] ?? null)
                && filled($payload['activities'] ?? null)
                && filled($payload['result'] ?? null);
        }

        return collect($payload)
            ->filter(fn ($value) => filled(is_array($value) ? implode(' ', $value) : $value))
            ->isNotEmpty();
    }

    public static function summaryLines(string $code, array $payload): array
    {
        $definition = self::sectionDefinition($code);
        if (! $definition) {
            return [];
        }

        if (in_array($code, self::reportSectionCodes(), true) && is_array($payload['activity_entries'] ?? null)) {
            return collect($payload['activity_entries'])
                ->filter(fn ($entry) => is_array($entry))
                ->flatMap(function (array $entry, int $index) {
                    $number = $index + 1;

                    return [
                        'Kegiatan '.$number.': '.($entry['activity'] ?? '-'),
                        'Tujuan: '.($entry['purpose'] ?? '-'),
                        'Uraian Kegiatan: '.($entry['description'] ?? '-'),
                        'Hasil: '.($entry['result'] ?? '-'),
                    ];
                })
                ->values()
                ->all();
        }

        $lines = [];
        foreach ($definition['fields'] ?? [] as $field) {
            $value = $payload[$field['name']] ?? '';
            $value = is_array($value) ? implode(', ', array_filter($value)) : trim((string) $value);
            if ($value !== '') {
                $lines[] = $field['label'].': '.$value;
            }
        }

        return $lines;
    }
}
