<?php

namespace App\Support;

class PkpaApotekAssessment
{
    public const VERSION = 'PANDUAN-PKPA-2026-APT-v1';

    public static function sections(string $assessorType): array
    {
        return $assessorType === 'field_supervisor'
            ? self::fieldSupervisorSections()
            : self::internalSupervisorSections();
    }

    public static function criteria(string $assessorType): array
    {
        return collect(self::sections($assessorType))
            ->flatMap(fn (array $section) => $section['criteria'])
            ->values()
            ->all();
    }

    public static function criterion(string $assessorType, string $code): ?array
    {
        return collect(self::criteria($assessorType))->firstWhere('code', $code);
    }

    public static function levelLabels(): array
    {
        return [
            1 => 'Sangat Kurang',
            2 => 'Kurang',
            3 => 'Cukup',
            4 => 'Baik',
            5 => 'Sangat Baik',
        ];
    }

    public static function generalRubric(): array
    {
        return [
            5 => 'Mandiri, tepat, konsisten, tanpa kesalahan berarti.',
            4 => 'Menguasai dengan baik dan hanya memerlukan sedikit arahan.',
            3 => 'Menguasai kompetensi dasar tetapi masih memerlukan bimbingan.',
            2 => 'Belum konsisten, sering melakukan kesalahan, dan memerlukan banyak arahan.',
            1 => 'Belum menguasai kompetensi dan belum mampu melaksanakan tugas sesuai standar.',
        ];
    }

    private static function fieldSupervisorSections(): array
    {
        return [
            self::section('professional', 'A. Sikap Profesional', [
                self::criterionDefinition('attendance', 'Kehadiran', 5, true, [5 => 'Seluruh hari presensi terpenuhi dan tervalidasi.', 4 => 'Terdapat 1 hari tidak terpenuhi.', 3 => 'Terdapat 2-3 hari tidak terpenuhi.', 2 => 'Lebih dari 3 hari tidak terpenuhi.', 1 => 'Kehadiran tidak memenuhi ketentuan.']),
                self::criterionDefinition('punctuality', 'Ketepatan waktu', 5),
                self::criterionDefinition('sop_discipline', 'Disiplin terhadap SOP', 5, false, [5 => 'Selalu mematuhi SOP.', 4 => 'Hampir selalu mematuhi SOP.', 3 => 'Kadang memerlukan pengingat.', 2 => 'Sering melanggar SOP.', 1 => 'Tidak mematuhi SOP.']),
                self::criterionDefinition('ethics', 'Etika dan sopan santun', 5, false, [5 => 'Sangat sopan, profesional, dan menjaga kerahasiaan pasien.', 4 => 'Sopan dan profesional.', 3 => 'Cukup sopan.', 2 => 'Kurang profesional.', 1 => 'Tidak menunjukkan etika profesi.']),
                self::criterionDefinition('responsibility', 'Tanggung jawab', 5, false, [5 => 'Menyelesaikan semua tugas tepat waktu tanpa diingatkan.', 4 => 'Menyelesaikan tugas dengan sedikit arahan.', 3 => 'Menyelesaikan sebagian besar tugas.', 2 => 'Sering terlambat menyelesaikan tugas.', 1 => 'Tidak menyelesaikan tugas.']),
                self::criterionDefinition('integrity', 'Kejujuran dan integritas', 5),
            ]),
            self::section('technical', 'B. Kompetensi Teknis', [
                self::criterionDefinition('administrative_screening', 'Skrining administrasi resep', 4, false, [5 => 'Selalu lengkap dan benar.', 4 => 'Hampir selalu benar.', 3 => 'Masih ada kekurangan kecil.', 2 => 'Banyak kesalahan.', 1 => 'Tidak mampu melakukan skrining.']),
                self::criterionDefinition('pharmaceutical_screening', 'Skrining farmasetik resep', 4, false, [5 => 'Tepat mengidentifikasi semua aspek farmasetik.', 4 => 'Hampir seluruh aspek benar.', 3 => 'Masih memerlukan arahan.', 2 => 'Banyak aspek terlewat.', 1 => 'Belum memahami.']),
                self::criterionDefinition('clinical_screening', 'Skrining klinis resep', 4, false, [5 => 'Mampu mengidentifikasi masalah klinis secara tepat.', 4 => 'Sebagian besar benar.', 3 => 'Perlu bimbingan.', 2 => 'Sering salah.', 1 => 'Belum mampu.']),
                self::criterionDefinition('dispensing', 'Dispensing obat', 5, false, [5 => 'Tepat, teliti, tanpa kesalahan.', 4 => 'Sangat sedikit kesalahan.', 3 => 'Masih perlu supervisi.', 2 => 'Sering melakukan kesalahan.', 1 => 'Tidak mampu melakukan dispensing.']),
                self::criterionDefinition('labeling', 'Pembuatan etiket', 3),
                self::criterionDefinition('medicine_handover', 'Penyerahan obat', 4, false, [5 => 'Edukasi lengkap, jelas, dan sistematis.', 4 => 'Edukasi baik dengan sedikit kekurangan.', 3 => 'Edukasi dasar terpenuhi.', 2 => 'Edukasi kurang lengkap.', 1 => 'Tidak mampu memberikan edukasi.']),
                self::criterionDefinition('self_medication', 'Pelayanan swamedikasi', 4, false, [5 => 'Anamnesis sangat lengkap dan sistematis, pemilihan obat tepat, serta edukasi sangat jelas.', 4 => 'Anamnesis hampir lengkap, pemilihan obat hampir selalu tepat, dan edukasi baik.', 3 => 'Anamnesis, pemilihan obat, dan edukasi cukup tetapi masih memerlukan arahan.', 2 => 'Anamnesis kurang lengkap, pemilihan obat kurang tepat, dan edukasi kurang.', 1 => 'Tidak melakukan anamnesis, pemilihan obat tidak sesuai, dan tidak memberikan edukasi.']),
                self::criterionDefinition('patient_counselling', 'Konseling pasien', 4, false, [5 => 'Sangat komunikatif dan empatik, informasi lengkap dan akurat, serta selalu memastikan pemahaman pasien.', 4 => 'Komunikasi baik, informasi hampir lengkap, dan sebagian besar memastikan pemahaman pasien.', 3 => 'Komunikasi dan informasi cukup, tetapi evaluasi pemahaman baru dilakukan sesekali.', 2 => 'Komunikasi dan informasi kurang serta jarang mengevaluasi pemahaman pasien.', 1 => 'Tidak mampu berkomunikasi, memberi informasi, atau mengevaluasi pemahaman pasien.']),
                self::criterionDefinition('drug_information', 'Pelayanan Informasi Obat (PIO)', 4, false, [5 => 'Informasi selalu akurat, berbasis referensi ilmiah terkini.', 4 => 'Informasi akurat dengan sedikit koreksi dan menggunakan referensi yang sesuai.', 3 => 'Informasi cukup akurat dengan referensi terbatas.', 2 => 'Informasi kurang akurat dan jarang menggunakan referensi.', 1 => 'Informasi tidak akurat dan tidak menggunakan referensi.']),
                self::criterionDefinition('medicine_management', 'Pengelolaan obat: pengadaan, penyimpanan, FIFO/FEFO', 4, false, [5 => 'Penyimpanan selalu sesuai SOP dan FIFO/FEFO; stock opname teliti dan akurat.', 4 => 'Penyimpanan hampir selalu sesuai; stock opname hampir akurat.', 3 => 'Cukup memahami penyimpanan dan masih memerlukan bimbingan saat stock opname.', 2 => 'Kurang memahami penyimpanan dan banyak kesalahan saat stock opname.', 1 => 'Tidak memahami penyimpanan dan tidak mampu melakukan stock opname.']),
            ]),
            self::section('clinical', 'C. Kemampuan Klinis', [
                self::criterionDefinition('drp_identification', 'Identifikasi Drug Related Problems (DRP)', 4, false, [5 => 'Tepat mengidentifikasi seluruh DRP.', 4 => 'Sebagian besar tepat.', 3 => 'Masih memerlukan arahan.', 2 => 'Banyak DRP tidak teridentifikasi.', 1 => 'Tidak mampu mengidentifikasi.']),
                self::criterionDefinition('therapy_analysis', 'Analisis terapi obat', 4),
                self::criterionDefinition('soap', 'Penyusunan SOAP', 4, false, [5 => 'Lengkap, logis, dan sistematis.', 4 => 'Hampir lengkap.', 3 => 'Cukup.', 2 => 'Kurang sistematis.', 1 => 'Tidak mampu menyusun SOAP.']),
                self::criterionDefinition('clinical_reasoning', 'Clinical reasoning', 4),
                self::criterionDefinition('therapy_monitoring', 'Monitoring terapi obat', 4, false, [5 => 'Menentukan parameter monitoring secara tepat.', 4 => 'Sebagian besar tepat.', 3 => 'Cukup.', 2 => 'Kurang.', 1 => 'Tidak mampu.']),
            ]),
            self::section('communication', 'D. Komunikasi dan Administrasi', [
                self::criterionDefinition('patient_communication', 'Komunikasi dengan pasien', 2),
                self::criterionDefinition('health_worker_communication', 'Komunikasi dengan tenaga kesehatan', 2),
                self::criterionDefinition('teamwork', 'Kerja sama dalam tim', 2),
                self::criterionDefinition('logbook_documentation', 'Kelengkapan logbook dan dokumentasi', 2, false, [5 => 'Lengkap, rapi, dan sesuai kegiatan.', 4 => 'Hampir lengkap.', 3 => 'Cukup lengkap.', 2 => 'Banyak bagian belum terisi.', 1 => 'Tidak lengkap.']),
                self::criterionDefinition('report_neatness', 'Kerapihan laporan PKPA', 2, false, [5 => 'Sangat sistematis, ilmiah, dan sesuai pedoman.', 4 => 'Baik.', 3 => 'Cukup.', 2 => 'Kurang sistematis.', 1 => 'Tidak memenuhi pedoman.']),
            ]),
        ];
    }

    private static function internalSupervisorSections(): array
    {
        return [
            self::section('portfolio', 'A. Penilaian Portofolio', [
                self::criterionDefinition('portfolio_completeness', 'Kelengkapan isi portofolio', 10),
                self::criterionDefinition('portfolio_guideline', 'Kesesuaian dengan pedoman PKPA', 5),
                self::criterionDefinition('portfolio_structure', 'Kerapihan dan sistematika penulisan', 5),
                self::criterionDefinition('logbook_completeness', 'Kelengkapan logbook', 5, false, [5 => 'Lengkap, rapi, dan sesuai kegiatan.', 4 => 'Hampir lengkap.', 3 => 'Cukup lengkap.', 2 => 'Banyak bagian belum terisi.', 1 => 'Tidak lengkap.']),
                self::criterionDefinition('documentation_completeness', 'Kelengkapan dokumentasi', 5),
                self::criterionDefinition('scientific_references', 'Ketepatan penggunaan referensi ilmiah', 5),
            ]),
            self::section('case_report', 'B. Penilaian Studi Kasus', [
                self::criterionDefinition('patient_data', 'Kelengkapan data pasien', 5),
                self::criterionDefinition('soap_analysis', 'Analisis SOAP', 10, false, [5 => 'Lengkap, logis, dan sistematis.', 4 => 'Hampir lengkap.', 3 => 'Cukup.', 2 => 'Kurang sistematis.', 1 => 'Tidak mampu menyusun SOAP.']),
                self::criterionDefinition('case_drp', 'Identifikasi Drug Related Problems (DRP)', 5),
                self::criterionDefinition('pharmacy_intervention', 'Ketepatan intervensi farmasi', 5),
                self::criterionDefinition('case_monitoring', 'Monitoring dan evaluasi terapi', 5),
            ]),
            self::section('presentation', 'C. Penilaian Presentasi dan Seminar', [
                self::criterionDefinition('material_mastery', 'Penguasaan materi', 5),
                self::criterionDefinition('answering_questions', 'Kemampuan menjawab pertanyaan', 5),
                self::criterionDefinition('delivery', 'Penyampaian materi', 5),
                self::criterionDefinition('presentation_media', 'Media presentasi', 5),
            ]),
            self::section('academic_attitude', 'D. Penilaian Sikap Akademik', [
                self::criterionDefinition('guidance_discipline', 'Disiplin dalam bimbingan', 3),
                self::criterionDefinition('guidance_participation', 'Keaktifan selama bimbingan', 3),
                self::criterionDefinition('accepting_feedback', 'Kemampuan menerima masukan', 3),
                self::criterionDefinition('academic_ethics', 'Etika akademik dan profesionalisme', 3),
                self::criterionDefinition('task_independence', 'Kemandirian dalam menyelesaikan tugas', 3),
            ]),
        ];
    }

    private static function section(string $code, string $title, array $criteria): array
    {
        return ['code' => $code, 'title' => $title, 'weight' => collect($criteria)->sum('weight'), 'criteria' => $criteria];
    }

    private static function criterionDefinition(string $code, string $name, int $weight, bool $automatic = false, ?array $rubric = null): array
    {
        return [
            'code' => $code,
            'name' => $name,
            'weight' => $weight,
            'automatic' => $automatic,
            'rubric' => $rubric ?? self::generalRubric(),
        ];
    }
}
