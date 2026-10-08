<?php

namespace App\Support;

use App\Models\PkpaRotationComponentScore;
use App\Models\PkpaRotationRun;

class PkpaPreceptorAssessment
{
    public const VERSION = 'PANDUAN-PKPA-2026-PRESEPTOR-v1';

    public static function supports(PkpaRotationComponentScore $score): bool
    {
        $score->loadMissing(['assessment.rotationRun', 'assessor']);
        $key = self::keyFor($score->assessment->rotationRun);

        return $key !== null && ($score->assessor?->assessor_type === 'field_supervisor'
            || ($key === 'APT' && $score->assessor?->assessor_type === 'internal_supervisor'));
    }

    public static function sectionsFor(PkpaRotationComponentScore $score): array
    {
        if ($score->assessor?->assessor_type === 'internal_supervisor') {
            return PkpaApotekAssessment::sections('internal_supervisor');
        }

        return self::sections(self::keyFor($score->assessment->rotationRun));
    }

    public static function keyFor(PkpaRotationRun $run): ?string
    {
        $run->loadMissing(['practiceDomain', 'practiceDomainOption', 'practiceSite.practiceDomainOption']);
        $code = strtoupper((string) $run->practiceDomain?->code);
        if ($code === 'PEM') {
            $code = strtoupper((string) ($run->practiceDomainOption?->code ?? $run->practiceSite?->practiceDomainOption?->code));
        }

        return match ($code) {
            'APT', 'APOTEK' => 'APT',
            'PBF', 'RS', 'IND', 'DINKES', 'LOKAPOM' => $code,
            'PKM', 'PUSKESMAS' => 'PKM',
            default => null,
        };
    }

    public static function name(string $key): string
    {
        return ['APT' => 'Apotek', 'PBF' => 'PBF', 'RS' => 'Rumah Sakit', 'IND' => 'Industri Farmasi',
            'PKM' => 'Puskesmas', 'DINKES' => 'Dinas Kesehatan', 'LOKAPOM' => 'Loka POM'][$key];
    }

    public static function reference(string $key): string
    {
        return 'Panduan PKPA 2026, '.(['APT' => 'Lampiran 8, halaman 51-53', 'PBF' => 'Lampiran 22, halaman 94-96',
            'RS' => 'Lampiran 11, halaman 60-62', 'IND' => 'Lampiran 24, halaman 101-103',
            'PKM' => 'Lampiran 14, halaman 68-70', 'DINKES' => 'Lampiran 17, halaman 76-78',
            'LOKAPOM' => 'Lampiran 20, halaman 85-88'][$key]);
    }

    public static function sections(string $key): array
    {
        if ($key === 'APT') {
            return PkpaApotekAssessment::sections('field_supervisor');
        }

        $definitions = match ($key) {
            'PBF' => [
                ['professional', 'A. Sikap Profesional', [
                    ['discipline', 'Disiplin (ketepatan waktu dan kepatuhan terhadap tata tertib)', 5],
                    ['attendance', 'Kehadiran', 5], ['responsibility', 'Tanggung jawab', 5],
                    ['ethics', 'Etika profesi dan integritas', 5], ['appearance', 'Penampilan dan kerapian', 5],
                ]],
                ['technical', 'B. Kompetensi Teknis', [
                    ['workflow', 'Memahami struktur organisasi dan alur kerja PBF', 5],
                    ['cdob', 'Memahami dan menerapkan CDOB', 10],
                    ['receiving_storage', 'Penerimaan dan penyimpanan obat sesuai SOP', 5],
                    ['inventory', 'Pengelolaan persediaan (FIFO, FEFO, stock opname)', 5],
                    ['cold_chain', 'Pengelolaan Cold Chain Product', 5],
                    ['distribution', 'Picking, packing, dan distribusi obat', 5],
                    ['distribution_documentation', 'Dokumentasi dan administrasi distribusi', 5],
                ]],
                ['analysis', 'C. Kemampuan Analisis', [
                    ['identify_problem', 'Identifikasi masalah dalam distribusi obat', 5],
                    ['cause_analysis', 'Analisis penyebab masalah', 5],
                    ['solutions', 'Kemampuan memberikan solusi sesuai CDOB dan regulasi', 5],
                ]],
                ['communication', 'D. Komunikasi dan Kerja Sama', [
                    ['staff_communication', 'Komunikasi dengan preseptor dan staf', 5], ['teamwork', 'Kerja sama dalam tim', 5],
                ]],
                ['reports', 'E. Laporan dan Portofolio', [
                    ['logbook', 'Kelengkapan logbook', 3], ['activity_report', 'Kualitas laporan kegiatan', 4],
                    ['case_portfolio', 'Penyusunan studi kasus dan portofolio', 3],
                ]],
            ],
            'RS' => [
                ['professional', 'A. Penilaian Sikap (Afektif)', [
                    ['attendance', 'Kehadiran', 5], ['discipline', 'Kedisiplinan', 5], ['ethics', 'Etika profesi', 5],
                    ['responsibility', 'Tanggung jawab', 5], ['honesty', 'Kejujuran', 5], ['initiative', 'Inisiatif', 5],
                    ['teamwork', 'Kerja sama tim', 5], ['communication', 'Komunikasi', 5],
                ]],
                ['knowledge', 'B. Penilaian Pengetahuan (Kognitif)', [
                    ['service_system', 'Pemahaman sistem pelayanan rumah sakit', 3],
                    ['medicine_management', 'Pengelolaan sediaan farmasi dan BMHP', 4],
                    ['prescription_dispensing', 'Dispensing resep', 4], ['pio', 'Pelayanan Informasi Obat (PIO)', 4],
                    ['counselling', 'Konseling pasien', 4], ['reconciliation', 'Rekonsiliasi obat', 4],
                    ['meso', 'Monitoring Efek Samping Obat (MESO)', 4], ['drp', 'Drug Related Problem (DRP)', 4],
                    ['therapy_monitoring', 'Monitoring terapi obat', 4], ['clinical_pharmacy', 'Pelayanan farmasi klinik', 5],
                ]],
                ['skills', 'C. Penilaian Keterampilan (Psikomotor)', [
                    ['screening', 'Skrining resep', 2], ['preparation', 'Penyiapan obat', 2], ['label', 'Pelabelan obat', 2],
                    ['dispensing', 'Dispensing', 2], ['sterile', 'Pelayanan steril', 2],
                    ['documentation', 'Dokumentasi pelayanan', 2], ['soap', 'Penyusunan SOAP', 2],
                    ['case_presentation', 'Presentasi kasus', 2], ['portfolio', 'Penyusunan portofolio', 2],
                    ['problem_solving', 'Kemampuan problem solving', 2],
                ]],
            ],
            'PKM' => [
                ['technical', 'I. Kompetensi Profesional', [
                    ['service_system', 'Memahami sistem pelayanan kefarmasian di Puskesmas', 5],
                    ['medicine_management', 'Pengelolaan perbekalan farmasi (perencanaan, pengadaan, penyimpanan)', 5],
                    ['dispensing', 'Pelayanan resep dan dispensing', 5], ['pio', 'Pelayanan Informasi Obat (PIO)', 5],
                    ['counselling', 'Konseling pasien', 5], ['medicine_monitoring', 'Monitoring penggunaan obat', 5],
                    ['drp', 'Kemampuan mengidentifikasi Drug Related Problems (DRP)', 5],
                    ['documentation', 'Dokumentasi pelayanan kefarmasian', 5],
                ]],
                ['communication', 'II. Kompetensi Komunikasi', [
                    ['patient_communication', 'Komunikasi dengan pasien', 5], ['staff_communication', 'Komunikasi dengan tenaga kesehatan', 5],
                    ['education', 'Kemampuan edukasi kesehatan', 5], ['empathy', 'Sikap sopan dan empati', 5],
                ]],
                ['professional', 'III. Sikap Profesional', [
                    ['discipline', 'Disiplin waktu', 5], ['responsibility', 'Tanggung jawab', 5],
                    ['ethics', 'Etika profesi', 5], ['initiative', 'Inisiatif dan kemandirian', 5],
                ]],
                ['reports', 'IV. Administrasi dan Pelaporan', [
                    ['logbook', 'Kelengkapan logbook', 5], ['activity_report', 'Kelengkapan laporan kegiatan', 5],
                    ['case', 'Penyusunan studi kasus', 5], ['presentation', 'Presentasi hasil PKPA', 5],
                ]],
            ],
            'DINKES' => [
                ['knowledge', 'A. Pengetahuan', [
                    ['structure', 'Memahami struktur organisasi dan tugas Dinas Kesehatan', 5],
                    ['regulations', 'Memahami regulasi dan kebijakan kefarmasian', 5],
                    ['planning', 'Memahami perencanaan kebutuhan obat dan BMHP', 5],
                    ['logistics', 'Memahami pengelolaan logistik farmasi (pengadaan, penyimpanan, distribusi)', 10],
                    ['monitoring', 'Memahami monitoring dan evaluasi pengelolaan obat', 5],
                ]],
                ['skills', 'B. Keterampilan', [
                    ['data_analysis', 'Kemampuan mengumpulkan dan menganalisis data', 10],
                    ['procedures', 'Kemampuan menyelesaikan tugas sesuai prosedur', 10],
                    ['reports', 'Kemampuan menyusun laporan kegiatan dan portofolio', 10],
                    ['solutions', 'Kemampuan mengidentifikasi masalah dan memberikan solusi', 10],
                ]],
                ['professional', 'C. Sikap Profesional', [
                    ['discipline', 'Disiplin dan kehadiran', 5], ['responsibility', 'Tanggung jawab', 5],
                    ['ethics', 'Etika dan integritas', 5], ['communication', 'Komunikasi dan kerja sama', 10],
                    ['initiative', 'Inisiatif dan motivasi belajar', 5],
                ]],
            ],
            'LOKAPOM' => [
                ['professional', 'I. Sikap Profesional', [
                    ['integrity', 'Integritas dan kejujuran', 5], ['discipline', 'Disiplin dan ketepatan waktu', 5],
                    ['responsibility', 'Tanggung jawab terhadap tugas', 5], ['ethics', 'Etika profesi', 5],
                    ['appearance', 'Penampilan dan sikap profesional', 5],
                ]],
                ['knowledge', 'II. Pengetahuan', [
                    ['functions', 'Memahami tugas dan fungsi Loka POM', 5], ['regulations', 'Memahami regulasi BPOM', 5],
                    ['market_supervision', 'Memahami pengawasan pre-market dan post-market', 5],
                    ['sampling', 'Memahami proses pemeriksaan dan sampling', 5],
                    ['laboratory', 'Memahami proses pengujian laboratorium', 5],
                ]],
                ['skills', 'III. Keterampilan', [
                    ['observation', 'Kemampuan observasi kegiatan pengawasan', 5],
                    ['identify_problem', 'Kemampuan mengidentifikasi permasalahan', 5], ['case_analysis', 'Kemampuan menganalisis kasus', 5],
                    ['reports', 'Kemampuan menyusun laporan', 5], ['presentation', 'Kemampuan menyampaikan hasil kegiatan', 5],
                    ['documentation', 'Ketelitian dalam dokumentasi', 5],
                ]],
                ['communication', 'IV. Komunikasi dan Kerja Sama', [
                    ['preceptor_communication', 'Komunikasi dengan preseptor', 5], ['staff_communication', 'Komunikasi dengan pegawai', 5],
                    ['teamwork', 'Kerja sama dalam tim', 5], ['discussion', 'Keaktifan dalam diskusi', 5],
                ]],
            ],
            'IND' => [
                ['professional', 'B. Sikap dan Profesionalisme', [
                    ['attendance', 'Kehadiran', 5], ['punctuality', 'Ketepatan waktu', 5], ['discipline', 'Disiplin', 5],
                    ['responsibility', 'Tanggung jawab', 5], ['integrity', 'Kejujuran dan integritas', 5], ['ethics', 'Etika profesi', 5],
                    ['appearance', 'Penampilan dan kepatuhan terhadap tata tertib', 5], ['adaptation', 'Kemampuan beradaptasi', 5],
                ]],
                ['technical', 'C. Kompetensi Teknis', [
                    ['cpob', 'Memahami penerapan CPOB', 5], ['qms', 'Memahami sistem manajemen mutu (QMS)', 5],
                    ['production', 'Memahami proses produksi', 5], ['qa', 'Memahami Quality Assurance (QA)', 5],
                    ['qc', 'Memahami Quality Control (QC)', 5], ['gdp', 'Memahami dokumentasi (GDP)', 5],
                    ['validation', 'Memahami validasi dan kualifikasi', 5], ['materials', 'Memahami pengelolaan material/gudang', 5],
                ]],
                ['skills', 'D. Keterampilan', [
                    ['communication', 'Kemampuan komunikasi', 3], ['teamwork', 'Kerja sama dalam tim', 3],
                    ['analysis', 'Kemampuan analisis dan pemecahan masalah', 4], ['initiative', 'Inisiatif', 3],
                    ['reports', 'Kemampuan menyusun laporan', 3], ['presentation', 'Kemampuan presentasi', 4],
                ]],
            ],
            default => [],
        };

        return array_map(function (array $section) {
            $criteria = array_map(fn (array $row) => [
                'code' => $row[0], 'name' => $row[1], 'weight' => $row[2],
                'automatic' => false, 'rubric' => PkpaApotekAssessment::generalRubric(),
            ], $section[2]);

            return ['code' => $section[0], 'title' => $section[1], 'weight' => array_sum(array_column($criteria, 'weight')), 'criteria' => $criteria];
        }, $definitions);
    }
}
