@extends('layouts.app')

@section('title', 'Detail Portofolio PKPA')
@section('page_title', 'Detail Portofolio PKPA')

@php
    $isApotek = \App\Support\PkpaApotekPortfolio::isApotekCode($portfolio->practiceDomain?->code);
    $isPbf = $portfolio->practiceDomain?->code === 'PBF';
    $isHospital = \App\Support\PkpaHospitalPortfolio::isHospitalCode($portfolio->practiceDomain?->code);
    $isIndustry = \App\Support\PkpaIndustryPortfolio::isIndustryCode($portfolio->practiceDomain?->code);
    $isPuskesmas = $portfolio->template?->code === \App\Support\PkpaPuskesmasPortfolio::TEMPLATE_CODE;
    $isHealthOffice = $portfolio->template?->code === \App\Support\PkpaHealthOfficePortfolio::TEMPLATE_CODE;
    $editableSections = $isApotek ? \App\Support\PkpaApotekPortfolio::editableSections() : [];
    $hospitalSections = $isHospital ? \App\Support\PkpaHospitalPortfolio::editableSections() : [];
    $industrySections = $isIndustry ? \App\Support\PkpaIndustryPortfolio::editableSections() : [];
    $puskesmasSections = $isPuskesmas ? \App\Support\PkpaPuskesmasPortfolio::editableSections() : [];
    $healthOfficeSections = $isHealthOffice ? \App\Support\PkpaHealthOfficePortfolio::editableSections() : [];
    $sectionRecords = $portfolio->sectionRecords->keyBy('section_code');
    $reportCodes = \App\Support\PkpaApotekPortfolio::reportSectionCodes();
    $manualSections = $isApotek
        ? collect($editableSections)->keys()
        : $portfolio->template->sections->where('source_type', 'structured_form')->pluck('code');
    $completedSections = $manualSections
        ->filter(fn ($code) => ($sectionRecords->get($code)?->status === 'completed'))
        ->count();
    $previewSections = collect(['site_profile', 'bibliography', 'attachments'])
        ->map(function ($code) use ($editableSections, $sectionRecords) {
            $definition = $editableSections[$code] ?? null;
            $record = $sectionRecords->get($code);

            if (! $definition || ! $record) {
                return null;
            }

            $payload = collect($record->manual_payload ?? [])
                ->filter(fn ($value) => filled($value))
                ->mapWithKeys(fn ($value, $key) => [
                    collect($definition['fields'] ?? [])->firstWhere('name', $key)['label'] ?? $key => $value,
                ]);

            return [
                'title' => $definition['title'],
                'status' => $record->status,
                'payload' => $payload,
            ];
        })
        ->filter();
    $latestCase = $portfolio->caseReports->sortByDesc('case_date')->first();
    $latestReflection = $portfolio->weeklyReflections->sortByDesc('week_number')->first();
    $latestAssessment = $portfolio->selfAssessments->sortByDesc('id')->first();
    $latestDocumentation = $portfolio->documentationItems->sortByDesc('activity_date')->first();
    $documentationCategories = $isPbf ? [
        'Orientasi dan Pengenalan PBF', 'Pengadaan', 'Penerimaan Barang', 'Gudang dan Penyimpanan',
        'Cold Chain Product', 'Inventory Control', 'Picking dan Packing', 'Distribusi',
        'Quality Assurance', 'Retur dan Recall', 'Produk Rusak dan Kedaluwarsa',
    ] : ($isHospital ? [
        'Orientasi Instalasi Farmasi Rumah Sakit', 'Gudang Farmasi', 'Pelayanan Farmasi Rawat Jalan',
        'Pelayanan Farmasi Rawat Inap', 'Farmasi Klinik', 'Pelayanan Informasi Obat', 'Konseling Pasien',
        'Rekonsiliasi Obat', 'Monitoring Efek Samping Obat', 'Visite Apoteker', 'Pelayanan Sediaan Steril',
    ] : ($isIndustry ? [
        'Orientasi Industri Farmasi', 'Quality Assurance', 'Quality Control', 'Produksi', 'Gudang',
        'Research and Development', 'Regulatory Affairs', 'Validasi', 'PPIC', 'Pharmacovigilance', 'Engineering',
    ] : ($isPuskesmas ? [
        'Orientasi Puskesmas', 'Pengelolaan Gudang Farmasi', 'Manajerial Instalasi Farmasi',
        'Farmasi Klinik', 'Pelayanan Informasi Obat', 'Konseling Pasien', 'Drug Related Problems',
        'Monitoring Efek Samping Obat', 'Visite Apoteker', 'Pelayanan Obat Steril',
    ] : [
        'Orientasi PKPA',
        'Pelayanan Resep',
        'Konseling Pasien',
        'Pelayanan Informasi Obat',
        'Swamedikasi',
        'Pengelolaan Sediaan Farmasi',
        'Penyimpanan Obat',
        'Stock Opname',
        'Administrasi Kefarmasian',
        'Penutupan PKPA',
    ])));
    $selfAssessmentAspects = $isPbf ? [
        'Pemahaman CDOB', 'Etika dan Disiplin', 'Komunikasi Profesional', 'Pengadaan',
        'Penerimaan Barang', 'Penyimpanan', 'Cold Chain Product', 'Pengendalian Persediaan',
        'Picking dan Packing', 'Distribusi', 'Quality Assurance', 'Penanganan Retur',
        'Recall Produk', 'Produk Rusak dan Kedaluwarsa', 'Dokumentasi', 'Manajemen Risiko',
        'Audit dan CAPA', 'Kerja Sama Tim', 'Problem Solving', 'Manajemen Waktu',
    ] : ($isHospital ? [
        'Kehadiran', 'Disiplin', 'Etika Profesi', 'Tanggung Jawab', 'Komunikasi', 'Kerja Sama Tim',
        'Dispensing', 'Pengelolaan Obat', 'Pelayanan Informasi Obat', 'Konseling Pasien',
        'Rekonsiliasi Obat', 'Monitoring Efek Samping Obat', 'Drug Related Problem',
        'Monitoring Terapi Obat', 'Pelayanan Steril', 'Dokumentasi', 'Kemampuan Analisis Kasus',
        'Problem Solving', 'Inisiatif', 'Pengembangan Diri',
    ] : ($isIndustry ? [
        'Profesionalisme dan Etika Profesi', 'Disiplin dan Tanggung Jawab', 'Komunikasi dengan Preseptor dan Tim',
        'Kerja Sama dalam Tim', 'Struktur Organisasi Industri Farmasi', 'Penerapan CPOB',
        'Sistem Manajemen Mutu', 'Good Documentation Practice', 'Proses Produksi Obat',
        'In Process Control', 'Quality Assurance', 'Quality Control', 'Validasi dan Kualifikasi',
        'Sistem Gudang FIFO/FEFO', 'Research and Development', 'Regulatory Affairs', 'Farmakovigilans',
        'Investigasi Deviasi dan CAPA', 'Manajemen Risiko Mutu', 'Keselamatan dan Kesehatan Kerja',
        'Berpikir Kritis', 'Analisis Masalah', 'Laporan Ilmiah', 'Presentasi', 'Belajar Mandiri',
    ] : ($isPuskesmas ? [
        'Struktur Organisasi dan Sistem Pelayanan Puskesmas', 'Tugas dan Tanggung Jawab Apoteker',
        'Perencanaan Kebutuhan Obat', 'Permintaan dan Penerimaan Obat', 'Penyimpanan FIFO dan FEFO',
        'Pengendalian Stok Obat', 'Pencatatan dan Pelaporan Logistik', 'Skrining Administratif Resep',
        'Skrining Farmasetik Resep', 'Skrining Klinis Resep', 'Penyiapan dan Penyerahan Obat',
        'Pelayanan Informasi Obat', 'Konseling Pasien', 'Identifikasi Drug Related Problems',
        'Monitoring Efek Samping Obat', 'Penggunaan Obat Rasional', 'Program Kesehatan Puskesmas',
        'Promosi Kesehatan dan Edukasi Masyarakat', 'Komunikasi Efektif', 'Etika dan Kerahasiaan Pasien',
        'Kerja Sama Tim', 'Keselamatan Pasien', 'Dokumentasi dan Laporan', 'Manajemen Waktu',
        'Disiplin Tanggung Jawab dan Profesionalisme',
    ] : [
        'Disiplin',
        'Kehadiran',
        'Etika',
        'Komunikasi',
        'Pelayanan Resep',
        'Skrining Resep',
        'Swamedikasi',
        'Konseling',
        'Pelayanan Informasi Obat',
        'Pengelolaan Obat',
        'Dokumentasi',
        'Kerja Sama Tim',
        'Problem Solving',
        'Clinical Reasoning',
        'Manajemen Waktu',
    ])));
    if ($isHealthOffice) {
        $documentationCategories = [
            'Orientasi Dinas Kesehatan', 'Perencanaan Obat dan BMHP', 'Pengadaan Obat Pemerintah',
            'Penerimaan dan Pemeriksaan Obat', 'Penyimpanan', 'Distribusi', 'Monitoring dan Evaluasi',
            'Obat Program', 'Pembinaan dan Supervisi', 'Pelaporan dan Analisis Data',
        ];
        $selfAssessmentAspects = [
            'Struktur Organisasi Dinas Kesehatan', 'Tugas dan Fungsi Dinas Kesehatan', 'Sistem Kesehatan Daerah',
            'Peran Apoteker di Pemerintahan', 'Hubungan dengan Fasilitas Pelayanan Kesehatan', 'Program Pembangunan Kesehatan',
            'Perencanaan Kebutuhan Obat', 'Metode Konsumsi', 'Metode Morbiditas', 'Analisis Data Pemakaian Obat',
            'Perencanaan Berdasarkan Anggaran', 'Prioritas Obat Program', 'Pengadaan Obat', 'Penerimaan dan Pemeriksaan',
            'Penyimpanan FIFO dan FEFO', 'Distribusi Obat', 'Monitoring Stok', 'Obat Kedaluwarsa dan Penghapusan',
            'Indikator Pengelolaan Obat', 'Analisis Stockout dan Overstock', 'Program Kesehatan Daerah',
            'Penggunaan Obat Rasional', 'Regulasi Kefarmasian', 'Pencatatan dan Pelaporan', 'Sikap dan Profesionalisme',
        ];
    }
@endphp

@section('content')
<div class="space-y-6" data-portfolio-writing-assistant>
    <section class="rounded-3xl border border-slate-100 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="text-sm font-bold uppercase tracking-wide text-cyan-700">Portofolio PKPA</p>
                <h1 class="mt-2 text-3xl font-black text-slate-950">{{ data_get($portfolio->placement_snapshot, 'practice_domain') }}</h1>
                <p class="mt-2 max-w-3xl text-sm text-slate-600">{{ data_get($portfolio->placement_snapshot, 'practice_site') }} - {{ data_get($portfolio->placement_snapshot, 'start_date') }} s.d. {{ data_get($portfolio->placement_snapshot, 'end_date') }}</p>
            </div>
            <span class="rounded-full bg-cyan-50 px-4 py-2 text-sm font-bold text-cyan-700">{{ $portfolio->statusLabel() }}</span>
        </div>
    </section>

    @if(session('status'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-bold text-emerald-800">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm font-bold text-rose-800">{{ $errors->first() }}</div>
    @endif

    <section class="grid gap-4 md:grid-cols-4">
        <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
            <p class="text-xs font-bold uppercase text-slate-500">Bagian {{ $isHealthOffice ? 'Dinas Kesehatan' : ($isPbf ? 'PBF' : ($isHospital ? 'Rumah Sakit' : ($isIndustry ? 'Industri Farmasi' : ($isPuskesmas ? 'Puskesmas' : 'Apotek')))) }}</p>
            <p class="mt-2 text-2xl font-black text-slate-950">{{ $completedSections }} / {{ $manualSections->count() }}</p>
        </div>
        <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
            <p class="text-xs font-bold uppercase text-slate-500">Studi Kasus</p>
            <p class="mt-2 text-2xl font-black text-slate-950">{{ data_get($portfolio->progress_snapshot, 'counts.cases', 0) }}</p>
        </div>
        <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
            <p class="text-xs font-bold uppercase text-slate-500">Refleksi</p>
            <p class="mt-2 text-2xl font-black text-slate-950">{{ data_get($portfolio->progress_snapshot, 'counts.reflections', 0) }}</p>
        </div>
        <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
            <p class="text-xs font-bold uppercase text-slate-500">Dokumentasi</p>
            <p class="mt-2 text-2xl font-black text-slate-950">{{ data_get($portfolio->progress_snapshot, 'counts.documentation', 0) }}</p>
        </div>
    </section>

    <section class="rounded-3xl border border-slate-100 bg-white p-5 shadow-sm">
        <h2 class="text-lg font-black text-slate-950">Kemajuan</h2>
        <ul class="mt-3 space-y-2 text-sm text-slate-700">
            @forelse(data_get($portfolio->progress_snapshot, 'blocking', []) as $item)
                <li class="rounded-2xl bg-amber-50 px-4 py-3 font-semibold text-amber-800">{{ $item }}</li>
            @empty
                <li class="rounded-2xl bg-emerald-50 px-4 py-3 font-semibold text-emerald-800">Siap dikirim.</li>
            @endforelse
        </ul>
    </section>

    <section class="rounded-3xl border border-slate-100 bg-white p-5 shadow-sm">
        <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-lg font-black text-slate-950">Preview Hasil Isian</h2>
                <p class="mt-1 text-sm text-slate-600">Ringkasan isi yang sudah tersimpan, agar Anda bisa cek cepat sebelum melanjutkan atau mengirim portofolio.</p>
            </div>
            <span class="rounded-full bg-slate-100 px-4 py-2 text-xs font-bold uppercase tracking-wide text-slate-600">Preview kerja</span>
        </div>
        <div class="mt-4 grid gap-4 xl:grid-cols-2">
            @forelse($previewSections as $section)
                <section class="rounded-2xl bg-slate-50 p-4">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="text-base font-black text-slate-950">{{ $section['title'] }}</h3>
                        <span class="rounded-full px-3 py-1 text-xs font-bold {{ $section['status'] === 'completed' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">
                            {{ $section['status'] === 'completed' ? 'Tersimpan' : 'Draft' }}
                        </span>
                    </div>
                    <dl class="mt-3 space-y-3">
                        @forelse($section['payload'] as $label => $value)
                            <div>
                                <dt class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ $label }}</dt>
                                <dd class="mt-1 whitespace-pre-line text-sm leading-6 text-slate-800">{{ app(\App\Support\PkpaPortfolioTextFormatter::class)->normalize($value) }}</dd>
                            </div>
                        @empty
                            <p class="text-sm text-slate-500">Belum ada isian tersimpan.</p>
                        @endforelse
                    </dl>
                </section>
            @empty
                <div class="rounded-2xl border border-dashed border-slate-200 px-4 py-6 text-sm text-slate-500 xl:col-span-2">Belum ada bagian portofolio manual yang bisa dipreview.</div>
            @endforelse

            <section class="rounded-2xl bg-slate-50 p-4">
                <h3 class="text-base font-black text-slate-950">Preview Studi Kasus</h3>
                @if($latestCase)
                    <dl class="mt-3 space-y-3 text-sm">
                        <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Nomor Kasus</dt><dd class="mt-1 text-slate-800">{{ $latestCase->case_code }}</dd></div>
                        <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Keluhan / Diagnosis</dt><dd class="mt-1 text-slate-800">{{ $latestCase->complaint ?: '-' }}{{ $latestCase->diagnosis ? ' / '.$latestCase->diagnosis : '' }}</dd></div>
                        <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Intervensi</dt><dd class="mt-1 text-slate-800">{{ $latestCase->intervention ?: '-' }}</dd></div>
                    </dl>
                @else
                    <p class="mt-3 text-sm text-slate-500">Belum ada studi kasus tersimpan.</p>
                @endif
            </section>

            <section class="rounded-2xl bg-slate-50 p-4">
                <h3 class="text-base font-black text-slate-950">Preview Refleksi Mingguan</h3>
                @if($latestReflection)
                    <dl class="mt-3 space-y-3 text-sm">
                        <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Minggu</dt><dd class="mt-1 text-slate-800">Minggu ke-{{ $latestReflection->week_number }}</dd></div>
                        <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Pencapaian</dt><dd class="mt-1 text-slate-800">{{ $latestReflection->achievement ?: '-' }}</dd></div>
                        <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Rencana Berikutnya</dt><dd class="mt-1 text-slate-800">{{ $latestReflection->next_plan ?: '-' }}</dd></div>
                    </dl>
                @else
                    <p class="mt-3 text-sm text-slate-500">Belum ada refleksi mingguan tersimpan.</p>
                @endif
            </section>

            <section class="rounded-2xl bg-slate-50 p-4">
                <h3 class="text-base font-black text-slate-950">Preview Self Assessment</h3>
                @if($latestAssessment)
                    <dl class="mt-3 space-y-3 text-sm">
                        <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Aspek</dt><dd class="mt-1 text-slate-800">{{ $latestAssessment->aspect }}</dd></div>
                        <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Skor</dt><dd class="mt-1 text-slate-800">{{ $latestAssessment->score }}/5</dd></div>
                        <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Refleksi Akhir</dt><dd class="mt-1 text-slate-800">{{ $latestAssessment->final_reflection ?: ($latestAssessment->evidence_experience ?: '-') }}</dd></div>
                    </dl>
                @else
                    <p class="mt-3 text-sm text-slate-500">Belum ada self assessment tersimpan.</p>
                @endif
            </section>

            <section class="rounded-2xl bg-slate-50 p-4">
                <h3 class="text-base font-black text-slate-950">Preview Dokumentasi</h3>
                @if($latestDocumentation)
                    <dl class="mt-3 space-y-3 text-sm">
                        <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Kegiatan</dt><dd class="mt-1 text-slate-800">{{ $latestDocumentation->activity }}</dd></div>
                        <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Kategori</dt><dd class="mt-1 text-slate-800">{{ $latestDocumentation->category ?: '-' }}</dd></div>
                        <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Keterangan</dt><dd class="mt-1 text-slate-800">{{ $latestDocumentation->description ?: '-' }}</dd></div>
                    </dl>
                @else
                    <p class="mt-3 text-sm text-slate-500">Belum ada dokumentasi tersimpan.</p>
                @endif
            </section>
        </div>
    </section>

    <div class="grid gap-6 xl:grid-cols-2">
        <section class="rounded-3xl border border-slate-100 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-black text-slate-950">Pakta Integritas</h2>
            <p class="mt-2 text-sm text-slate-600">Buka lembar pakta integritas bergaya siap-print, cek QR validasi, lalu pilih setuju atau tidak setuju dari halaman dokumen.</p>
            <div class="mt-4 flex flex-wrap items-center gap-3">
                <a href="{{ route('student.pkpa-portfolios.integrity.show', $portfolio) }}" class="rounded-2xl bg-slate-900 px-4 py-3 text-sm font-bold text-white">
                    {{ $portfolio->integrity_acknowledged_at ? 'Lihat Pakta Integritas' : 'Buka Pakta Integritas' }}
                </a>
                <span class="rounded-full px-3 py-1 text-xs font-bold {{ $portfolio->integrity_acknowledged_at ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">
                    {{ $portfolio->integrity_acknowledged_at ? 'Sudah disetujui '.optional($portfolio->integrity_acknowledged_at)->format('d M Y H:i') : 'Belum disetujui' }}
                </span>
            </div>
        </section>
        <section class="rounded-3xl border border-slate-100 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-black text-slate-950">Unduhan</h2>
            <p class="mt-2 text-sm text-slate-600">Unduhan sementara tetap berbentuk draf internal, tetapi isinya mengikuti struktur portofolio PKPA {{ data_get($portfolio->placement_snapshot, 'practice_domain') }}.</p>
            <div class="mt-4 flex flex-wrap gap-3">
                <form method="POST" action="{{ route('student.pkpa-portfolios.exports.store', [$portfolio, 'docx']) }}">@csrf<button class="rounded-2xl bg-cyan-700 px-4 py-3 text-sm font-bold text-white">Unduh DOCX</button></form>
                <form method="POST" action="{{ route('student.pkpa-portfolios.exports.store', [$portfolio, 'pdf']) }}">@csrf<button class="rounded-2xl bg-cyan-700 px-4 py-3 text-sm font-bold text-white">Unduh PDF</button></form>
            </div>
        </section>
    </div>

    @if($isApotek)
        <section class="rounded-3xl border border-slate-100 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-lg font-black text-slate-950">Struktur Portofolio Apotek</h2>
                    <p class="mt-1 text-sm text-slate-600">Urutan ini mengikuti dokumen Portofolio PKPA Apotek dan Panduan PKPA 2026. Bagian isi praktik bisa dicicil sesuai progres rotasi.</p>
                </div>
                <span class="rounded-full bg-slate-100 px-4 py-2 text-xs font-bold uppercase tracking-wide text-slate-600">Apotek First</span>
            </div>
            <ol class="mt-4 grid gap-3 text-sm text-slate-700 md:grid-cols-2 xl:grid-cols-3">
                @foreach([
                    'Sampul',
                    'Lembar Pengesahan',
                    'Visi, Misi, Tujuan, dan Sasaran',
                    'Tata Tertib PKPA',
                    'Identitas Mahasiswa',
                    'Pakta Integritas',
                    'Daftar Isi',
                    'Profil Tempat PKPA',
                    'Logbook Harian',
                    'Laporan Kegiatan',
                    'Studi Kasus',
                    'Refleksi Mingguan',
                    'Self Assessment',
                    'Dokumentasi Kegiatan',
                    'Daftar Pustaka / Lampiran',
                ] as $index => $item)
                    <li class="rounded-2xl bg-slate-50 px-4 py-3 font-semibold">{{ $index + 1 }}. {{ $item }}</li>
                @endforeach
            </ol>
        </section>

        <section class="rounded-3xl border border-slate-100 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-black text-slate-950">Logbook Harian PKPA</h2>
            <p class="mt-2 text-sm text-slate-600">Logbook harian dibaca otomatis dari logbook rotasi PKPA. Pastikan aktivitas harian, kompetensi, dan refleksi harian sudah terisi di modul logbook.</p>
            <div class="mt-4 rounded-2xl bg-slate-50 p-4 text-sm text-slate-700">
                <p class="font-bold text-slate-900">Status logbook: {{ ($sectionRecords->get('daily_logbook')?->status === 'completed') ? 'Sudah terbaca dari rotasi' : 'Belum terbaca dari rotasi' }}</p>
                <p class="mt-2">Jumlah referensi logbook: {{ count(data_get($sectionRecords->get('daily_logbook')?->auto_source_refs, 'logbook_entry_ids', [])) }}</p>
            </div>
        </section>

        <section class="space-y-6">
            <div class="grid gap-6 xl:grid-cols-2">
                @foreach(['site_profile', 'bibliography', 'attachments'] as $sectionCode)
                    @php
                        $definition = $editableSections[$sectionCode];
                        $record = $sectionRecords->get($sectionCode);
                        $payload = $record?->manual_payload ?? [];
                    @endphp
                    <section @class([
                        'rounded-3xl border border-slate-100 bg-white p-5 shadow-sm',
                        'xl:col-span-2' => $sectionCode === 'site_profile',
                    ])>
                        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                            <div>
                                <h2 class="text-lg font-black text-slate-950">{{ $definition['title'] }}</h2>
                                <p class="mt-1 text-sm text-slate-600">{{ $definition['description'] }}</p>
                            </div>
                            <span class="rounded-full px-3 py-1 text-xs font-bold {{ $record?->status === 'completed' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">
                                {{ $record?->status === 'completed' ? 'Lengkap' : 'Perlu diisi' }}
                            </span>
                        </div>
                        <form method="POST" action="{{ route('student.pkpa-portfolios.sections.store', [$portfolio, $sectionCode]) }}" class="mt-4 grid gap-3">
                            @csrf
                            @foreach($definition['fields'] as $field)
                                <label class="grid gap-2">
                                    <span class="text-sm font-bold text-slate-700">{{ $field['label'] }}</span>
                                    @if($field['type'] === 'textarea')
                                        @php
                                            $rows = $field['rows'] ?? 3;
                                            $minimumHeight = match (true) {
                                                $rows >= 6 => 'min-h-52',
                                                $rows >= 5 => 'min-h-44',
                                                $rows >= 4 => 'min-h-36',
                                                $rows >= 3 => 'min-h-28',
                                                default => 'min-h-20',
                                            };
                                        @endphp
                                        <textarea name="{{ $field['name'] }}" rows="{{ $rows }}" @class(['resize-y rounded-2xl border-slate-200 text-sm', $minimumHeight])>{{ old($field['name'], $payload[$field['name']] ?? '') }}</textarea>
                                    @else
                                        <input name="{{ $field['name'] }}" value="{{ old($field['name'], $payload[$field['name']] ?? '') }}" class="max-w-xl rounded-2xl border-slate-200 text-sm">
                                    @endif
                                </label>
                            @endforeach
                            <button class="rounded-2xl bg-slate-900 px-4 py-3 text-sm font-bold text-white">Simpan {{ $definition['title'] }}</button>
                        </form>
                    </section>
                @endforeach
            </div>

            @php
                $selectedReportCode = request()->string('report')->toString();
                $selectedReportCode = in_array($selectedReportCode, $reportCodes, true) ? $selectedReportCode : $reportCodes[0];
                $activeDefinition = $editableSections[$selectedReportCode];
                $activeRecord = $sectionRecords->get($selectedReportCode);
                $activePayload = $activeRecord?->manual_payload ?? [];
                $activityEntries = collect(\App\Support\PkpaApotekPortfolio::orderedActivityEntries($selectedReportCode, $activePayload['activity_entries'] ?? $activePayload['legacy_activity_entries'] ?? []));
                $activityItems = $activeDefinition['activity_items'] ?? [];
                $activityRequirement = $activeDefinition['activity_requirement'] ?? null;
                $editingEntry = $activityEntries->firstWhere('id', request()->string('edit_activity')->toString());
                $totalReportActivities = collect($reportCodes)->sum(fn ($code) => count($sectionRecords->get($code)?->manual_payload['activity_entries'] ?? []));
                $completedReports = collect($reportCodes)->filter(fn ($code) => $sectionRecords->get($code)?->status === 'completed')->count();
            @endphp
            <section class="overflow-hidden rounded-3xl border border-slate-100 bg-white shadow-sm">
                <header class="border-b border-slate-100 px-5 py-5 sm:px-6">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            <p class="text-xs font-black uppercase tracking-wide text-cyan-700">Portofolio Apotek</p>
                            <h2 class="mt-1 text-xl font-black text-slate-950">Laporan Kegiatan PKPA</h2>
                            <p class="mt-1 text-sm text-slate-600">Tambahkan kegiatan yang benar-benar dikerjakan. Setiap isian tersimpan sebagai entri terpisah sesuai urutan dibuat.</p>
                        </div>
                        <div class="flex items-center gap-3 text-sm">
                            <div class="border-l-2 border-cyan-600 pl-3"><p class="font-black text-slate-950">{{ $totalReportActivities }}</p><p class="text-xs text-slate-500">kegiatan tersimpan</p></div>
                            <div class="border-l-2 border-emerald-500 pl-3"><p class="font-black text-slate-950">{{ $completedReports }}</p><p class="text-xs text-slate-500">topik lengkap</p></div>
                        </div>
                    </div>
                </header>

                <div>
                    <nav class="border-b border-slate-100 bg-slate-50/70 p-3 sm:p-4" aria-label="Topik laporan kegiatan">
                        <p class="px-2 pb-2 text-xs font-black uppercase tracking-wide text-slate-500">Topik Laporan</p>
                        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
                            @foreach($reportCodes as $sectionCode)
                                @php
                                    $definition = $editableSections[$sectionCode];
                                    $record = $sectionRecords->get($sectionCode);
                                    $entryCount = count($record?->manual_payload['activity_entries'] ?? []);
                                @endphp
                                <a href="{{ route('student.pkpa-portfolios.show', ['portfolio' => $portfolio, 'report' => $sectionCode]) }}#laporan-{{ $sectionCode }}" @class([
                                    'flex min-w-0 items-start justify-between gap-3 rounded-xl px-3 py-3 text-left transition',
                                    'bg-cyan-700 text-white shadow-sm' => $sectionCode === $selectedReportCode,
                                    'text-slate-700 hover:bg-white hover:shadow-sm' => $sectionCode !== $selectedReportCode,
                                ])>
                                    <span class="min-w-0"><span class="block break-words text-sm font-bold leading-5">{{ str($definition['title'])->after(': ') }}</span><span class="mt-1 block text-xs {{ $sectionCode === $selectedReportCode ? 'text-cyan-100' : 'text-slate-500' }}">{{ $entryCount }} kegiatan tersimpan</span></span>
                                    <span @class(['shrink-0 rounded-full px-2 py-1 text-xs font-black', 'bg-white/20 text-white' => $sectionCode === $selectedReportCode, 'bg-emerald-50 text-emerald-700' => $sectionCode !== $selectedReportCode && $record?->status === 'completed', 'bg-amber-50 text-amber-700' => $sectionCode !== $selectedReportCode && $record?->status !== 'completed'])>{{ $record?->status === 'completed' ? 'Lengkap' : 'Draf' }}</span>
                                </a>
                            @endforeach
                        </div>
                    </nav>

                    <div id="laporan-{{ $selectedReportCode }}" class="min-w-0 p-5 sm:p-6">
                        <div class="flex flex-col gap-4 border-b border-slate-100 pb-5 md:flex-row md:items-start md:justify-between">
                            <div>
                                <p class="text-xs font-black uppercase tracking-wide text-cyan-700">{{ $activeDefinition['title'] }}</p>
                                <h3 class="mt-1 text-xl font-black text-slate-950">{{ str($activeDefinition['title'])->after(': ') }}</h3>
                                <p class="mt-2 max-w-2xl text-sm text-slate-600">{{ $activeDefinition['description'] }}</p>
                            </div>
                            <span class="w-fit rounded-full px-3 py-1 text-xs font-bold {{ $activeRecord?->status === 'completed' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">{{ $activeRecord?->status === 'completed' ? 'Lengkap' : 'Belum lengkap' }}</span>
                        </div>

                        <div class="mt-5 grid gap-5 lg:grid-cols-[minmax(0,0.85fr)_minmax(0,1.15fr)]">
                            <section class="border-y border-slate-200 py-5">
                                <p class="text-xs font-black uppercase tracking-wide text-cyan-700">Referensi Tugas</p>
                                @if($activityRequirement)
                                    <p class="mt-3 text-sm font-bold text-slate-800">{{ $activityRequirement }}</p>
                                @endif
                                @if($activityItems)
                                    <p class="mt-3 text-sm text-slate-600">Pilih dan isi hanya kegiatan yang benar-benar Anda kerjakan. Daftar ini tidak wajib diselesaikan seluruhnya.</p>
                                    <ol class="mt-3 list-decimal space-y-1 pl-5 text-sm text-slate-700">
                                        @foreach($activityItems as $index => $item)
                                            <li>{{ $item }}</li>
                                        @endforeach
                                    </ol>
                                @endif
                                @if(! $activityRequirement && ! $activityItems)
                                    <p class="mt-3 text-sm text-slate-600">Tambahkan kegiatan atau kasus satu per satu. Entri tersimpan tetap dapat dibuka dan diperbarui.</p>
                                @endif
                                @if($activityEntries->isNotEmpty())
                                    <div class="mt-5 space-y-2 border-t border-slate-200 pt-4">
                                        <p class="text-xs font-black uppercase tracking-wide text-slate-500">Kegiatan Tersimpan</p>
                                        @foreach($activityEntries as $index => $entry)
                                            <a href="{{ route('student.pkpa-portfolios.show', ['portfolio' => $portfolio, 'report' => $selectedReportCode, 'edit_activity' => $entry['id']]) }}#laporan-{{ $selectedReportCode }}" class="flex items-center gap-3 rounded-xl border border-slate-200 px-3 py-3 text-sm font-bold text-slate-700 hover:border-cyan-200 hover:bg-cyan-50"><span class="text-xs text-slate-400">{{ $index + 1 }}.</span><span>{{ $entry['activity'] }}</span></a>
                                        @endforeach
                                    </div>
                                @endif
                            </section>

                            <form method="POST" action="{{ $editingEntry ? route('student.pkpa-portfolios.report-activities.update', [$portfolio, $selectedReportCode, $editingEntry['id']]) : route('student.pkpa-portfolios.report-activities.store', [$portfolio, $selectedReportCode]) }}" class="grid gap-4 rounded-2xl border border-slate-200 bg-slate-50 p-4 sm:p-5">
                                @csrf
                                @if($editingEntry) @method('PATCH') @endif
                                <div>
                                    <h4 class="text-base font-black text-slate-950">{{ $editingEntry ? 'Perbarui' : 'Tambah' }} Kegiatan</h4>
                                    <p class="mt-1 text-sm text-slate-600">Setiap penyimpanan menambahkan satu kegiatan baru. Gunakan Edit pada kegiatan tersimpan untuk memperbaiki isian.</p>
                                </div>
                                <label class="grid gap-2"><span class="text-sm font-bold text-slate-700">Nama Kegiatan atau Kasus</span><input name="activity" value="{{ old('activity', $editingEntry['activity'] ?? '') }}" class="rounded-xl border-slate-200 text-sm" required></label>
                                <label class="grid gap-2"><span class="text-sm font-bold text-slate-700">Tujuan</span><textarea name="purpose" rows="4" class="min-h-36 resize-y rounded-xl border-slate-200 text-sm" required>{{ old('purpose', $editingEntry['purpose'] ?? '') }}</textarea></label>
                                <label class="grid gap-2"><span class="text-sm font-bold text-slate-700">Uraian Kegiatan</span><textarea name="description" rows="5" class="min-h-44 resize-y rounded-xl border-slate-200 text-sm" required>{{ old('description', $editingEntry['description'] ?? '') }}</textarea></label>
                                <label class="grid gap-2"><span class="text-sm font-bold text-slate-700">Hasil</span><textarea name="result" rows="4" class="min-h-36 resize-y rounded-xl border-slate-200 text-sm" required>{{ old('result', $editingEntry['result'] ?? '') }}</textarea></label>
                                <div class="flex flex-wrap gap-3"><button class="inline-flex min-h-12 items-center justify-center rounded-xl bg-cyan-700 px-5 py-3 text-sm font-black text-white">{{ $editingEntry ? 'Simpan Perubahan' : 'Simpan Kegiatan' }}</button>@if($editingEntry)<a href="{{ route('student.pkpa-portfolios.show', ['portfolio' => $portfolio, 'report' => $selectedReportCode]) }}#laporan-{{ $selectedReportCode }}" class="inline-flex min-h-12 items-center justify-center rounded-xl border border-slate-200 px-5 py-3 text-sm font-bold text-slate-700">Batal</a>@endif</div>
                            </form>
                            @if($editingEntry)
                                <form method="POST" action="{{ route('student.pkpa-portfolios.report-activities.destroy', [$portfolio, $selectedReportCode, $editingEntry['id']]) }}" class="-mt-3">
                                    @csrf
                                    @method('DELETE')
                                    <button class="text-sm font-bold text-rose-700 hover:text-rose-800">Hapus kegiatan ini</button>
                                </form>
                            @endif
                        </div>

                        @if(filled($activePayload['purpose'] ?? null) || filled($activePayload['result'] ?? null) || filled(data_get($activePayload, 'legacy_unified_report.purpose')) || filled(data_get($activePayload, 'legacy_unified_report.result')))
                            <details class="mt-5 border-t border-slate-100 pt-5">
                                <summary class="cursor-pointer text-sm font-bold text-slate-700">Lihat laporan gabungan sebelumnya</summary>
                                <div class="mt-3 space-y-3 text-sm text-slate-600">
                                    <div class="rounded-xl bg-slate-50 px-4 py-3"><p class="font-bold text-slate-900">Tujuan</p><p class="mt-1 whitespace-pre-line">{{ data_get($activePayload, 'legacy_unified_report.purpose', $activePayload['purpose'] ?? '') }}</p><p class="mt-3 font-bold text-slate-900">Hasil</p><p class="mt-1 whitespace-pre-line">{{ data_get($activePayload, 'legacy_unified_report.result', $activePayload['result'] ?? '') }}</p></div>
                                </div>
                            </details>
                        @endif
                    </div>
                </div>
            </section>
        </section>
    @endif

    @if($isPbf)
        <section class="rounded-3xl border border-slate-100 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="text-xs font-black uppercase tracking-wide text-cyan-700">Portofolio PBF</p>
                    <h2 class="mt-1 text-xl font-black text-slate-950">Laporan Kegiatan PKPA PBF</h2>
                    <p class="mt-1 text-sm text-slate-600">Isi setiap unit yang benar-benar dipelajari. Laporan dapat disimpan sebagai draf dan diperbarui selama portofolio belum dikirim.</p>
                </div>
                <span class="w-fit rounded-full bg-cyan-50 px-3 py-1 text-xs font-bold text-cyan-700">11 Unit Kegiatan</span>
            </div>
            <div class="mt-5 space-y-3">
                @foreach($portfolio->template->sections->where('source_type', 'structured_form') as $section)
                    @php
                        $record = $sectionRecords->get($section->code);
                        $payload = $record?->manual_payload ?? [];
                        $fields = data_get($section->content_schema, 'fields', []);
                    @endphp
                    <details class="group rounded-2xl border border-slate-200 bg-slate-50" @if($section->code === 'site_profile') open @endif>
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-4 py-4">
                            <span class="min-w-0"><span class="block text-base font-black text-slate-950">{{ $section->title }}</span><span class="mt-1 block text-sm text-slate-600">{{ $section->code === 'site_profile' ? 'Lengkapi gambaran tempat PKPA sebelum membuat laporan unit.' : 'Tujuan, kegiatan, dan hasil pembelajaran.' }}</span></span>
                            <span class="shrink-0 rounded-full px-3 py-1 text-xs font-bold {{ $record?->status === 'completed' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">{{ $record?->status === 'completed' ? 'Tersimpan' : 'Belum diisi' }}</span>
                        </summary>
                        <form method="POST" action="{{ route('student.pkpa-portfolios.sections.store', [$portfolio, $section->code]) }}" class="grid gap-4 border-t border-slate-200 bg-white p-4 sm:p-5">
                            @csrf
                            @foreach($fields as $field)
                                <label class="grid gap-2">
                                    <span class="text-sm font-bold text-slate-700">{{ $field['label'] }}</span>
                                    <textarea name="{{ $field['name'] }}" rows="{{ $field['rows'] ?? 4 }}" class="min-h-28 resize-y rounded-xl border-slate-200 text-sm" @if($field['required'] ?? true) required @endif>{{ old($field['name'], $payload[$field['name']] ?? '') }}</textarea>
                                </label>
                            @endforeach
                            <div class="flex justify-end"><button class="rounded-xl bg-cyan-700 px-4 py-3 text-sm font-bold text-white">Simpan {{ $section->title }}</button></div>
                        </form>
                    </details>
                @endforeach
            </div>
        </section>
    @endif

    @if($isHospital || $isIndustry || $isPuskesmas || $isHealthOffice)
        @php
            $specialSections = $isHospital ? $hospitalSections : ($isIndustry ? $industrySections : ($isPuskesmas ? $puskesmasSections : $healthOfficeSections));
            $specialReportCodes = $isHospital ? \App\Support\PkpaHospitalPortfolio::reportSectionCodes()
                : ($isIndustry ? \App\Support\PkpaIndustryPortfolio::reportSectionCodes()
                    : ($isPuskesmas ? \App\Support\PkpaPuskesmasPortfolio::reportSectionCodes() : \App\Support\PkpaHealthOfficePortfolio::reportSectionCodes()));
            $specialCompletedReports = collect($specialReportCodes)
                ->filter(fn ($code) => $sectionRecords->get($code)?->status === 'completed')
                ->count();
            $specialDomainLabel = $isHospital ? 'Rumah Sakit' : ($isIndustry ? 'Industri Farmasi' : ($isPuskesmas ? 'Puskesmas' : 'Dinas Kesehatan'));
        @endphp
        <section class="overflow-hidden rounded-3xl border border-slate-100 bg-white shadow-sm">
            <header class="border-b border-slate-100 p-5 sm:p-6">
                <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                    <div>
                        <p class="text-xs font-black uppercase tracking-wide text-cyan-700">Portofolio {{ $specialDomainLabel }}</p>
                        <h2 class="mt-1 text-xl font-black text-slate-950">Profil dan Laporan Kegiatan</h2>
                        <p class="mt-2 max-w-3xl text-sm text-slate-600">Isi profil tempat PKPA, lalu lengkapi unit yang benar-benar Anda ikuti. Tidak semua unit wajib tersedia; minimal satu laporan kegiatan harus lengkap.</p>
                    </div>
                    <div class="flex gap-3 text-sm">
                        <div class="border-l-2 border-cyan-600 pl-3"><p class="font-black text-slate-950">{{ $specialCompletedReports }}</p><p class="text-xs text-slate-500">unit terisi</p></div>
                        <div class="border-l-2 border-slate-300 pl-3"><p class="font-black text-slate-950">{{ count($specialReportCodes) }}</p><p class="text-xs text-slate-500">unit tersedia</p></div>
                    </div>
                </div>
            </header>

            <section class="border-b border-slate-100 bg-slate-50 px-5 py-4 sm:px-6">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div><h3 class="font-black text-slate-950">Logbook Harian</h3><p class="mt-1 text-sm text-slate-600">Terhubung otomatis dengan logbook operasional {{ $specialDomainLabel }}.</p></div>
                    <span class="w-fit rounded-full px-3 py-1 text-xs font-bold {{ $sectionRecords->get('daily_logbook')?->status === 'completed' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">{{ $sectionRecords->get('daily_logbook')?->status === 'completed' ? 'Sudah terbaca' : 'Belum tersedia' }}</span>
                </div>
            </section>

            <div class="space-y-3 p-4 sm:p-5">
                @foreach($specialSections as $sectionCode => $definition)
                    @php
                        $record = $sectionRecords->get($sectionCode);
                        $payload = $record?->manual_payload ?? [];
                        $isProfile = $sectionCode === 'site_profile';
                    @endphp
                    <details class="group rounded-2xl border border-slate-200 bg-slate-50" @if($isProfile) open @endif>
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-4 py-4">
                            <span class="min-w-0"><span class="block text-base font-black text-slate-950">{{ $definition['title'] }}</span><span class="mt-1 block text-sm text-slate-600">{{ $definition['description'] }}</span></span>
                            <span class="shrink-0 rounded-full px-3 py-1 text-xs font-bold {{ $record?->status === 'completed' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">{{ $record?->status === 'completed' ? 'Tersimpan' : ($definition['is_required'] ? 'Wajib' : 'Opsional') }}</span>
                        </summary>
                        <form method="POST" action="{{ route('student.pkpa-portfolios.sections.store', [$portfolio, $sectionCode]) }}" class="grid gap-4 border-t border-slate-200 bg-white p-4 sm:p-5">
                            @csrf
                            @foreach($definition['fields'] as $field)
                                <label class="grid gap-2">
                                    <span class="text-sm font-bold text-slate-700">{{ $field['label'] }}</span>
                                    <textarea name="{{ $field['name'] }}" rows="{{ $field['rows'] ?? 4 }}" class="min-h-28 resize-y rounded-xl border-slate-200 text-sm" @if($field['required'] ?? true) required @endif>{{ old($field['name'], $payload[$field['name']] ?? '') }}</textarea>
                                </label>
                            @endforeach
                            <div class="flex justify-end"><button class="rounded-xl bg-cyan-700 px-4 py-3 text-sm font-bold text-white">Simpan {{ $definition['title'] }}</button></div>
                        </form>
                    </details>
                @endforeach
            </div>
        </section>
    @endif

    <section class="rounded-3xl border border-slate-100 bg-white p-5 shadow-sm">
        <h2 class="text-lg font-black text-slate-950">{{ $isHealthOffice ? 'Studi Kasus Dinas Kesehatan' : ($isIndustry ? 'Studi Kasus Industri Farmasi' : ($isPbf ? 'Studi Kasus PBF' : 'Studi Kasus')) }}</h2>
        <p class="mt-2 text-sm text-slate-600">{{ $isHealthOffice ? 'Analisis satu permasalahan nyata dalam pengelolaan obat, BMHP, distribusi, pelaporan, atau program kesehatan.' : ($isIndustry ? 'Dokumentasikan satu masalah mutu atau proses, misalnya deviasi, OOS, OOT, CAPA, change control, validasi, keluhan, atau penarikan produk.' : ($isPbf ? 'Dokumentasikan satu kasus operasional PBF, misalnya suhu CCP, retur, recall, selisih stok, atau penyimpangan dokumen. Jangan menulis identitas personal yang tidak diperlukan.' : 'Isi satu case report tanpa identitas langsung pasien. Bagian yang tersimpan akan digunakan dalam keluaran portofolio.')) }}</p>
        @if($isIndustry)
            <form method="POST" action="{{ route('student.pkpa-portfolios.cases.store', $portfolio) }}" class="mt-5 grid gap-4 md:grid-cols-2">
                @csrf
                <label class="grid gap-2"><span class="text-sm font-bold text-slate-700">Nomor Kasus</span><input name="case_code" value="{{ old('case_code') }}" class="rounded-2xl border-slate-200 text-sm" required></label>
                <label class="grid gap-2"><span class="text-sm font-bold text-slate-700">Tanggal</span><input type="date" name="case_date" value="{{ old('case_date') }}" class="rounded-2xl border-slate-200 text-sm"></label>
                @foreach([
                    'complaint' => 'Latar Belakang',
                    'diagnosis' => 'Identifikasi Masalah',
                    'history' => 'Analisis',
                    'drp' => 'Regulasi yang Digunakan',
                    'intervention' => 'Penyelesaian',
                    'monitoring' => 'Peran Apoteker',
                    'conclusion' => 'Kesimpulan',
                    'references' => 'Daftar Pustaka',
                ] as $name => $label)
                    <label class="grid gap-2 {{ in_array($name, ['complaint', 'history'], true) ? 'md:col-span-2' : '' }}"><span class="text-sm font-bold text-slate-700">{{ $label }}</span><textarea name="{{ $name }}" rows="4" class="min-h-36 resize-y rounded-2xl border-slate-200 text-sm">{{ old($name) }}</textarea></label>
                @endforeach
                <label class="flex items-start gap-2 text-sm font-semibold text-slate-700 md:col-span-2"><input type="checkbox" name="anonymization_confirmed" value="1" class="mt-1" required> Saya memastikan isi tidak memuat informasi rahasia perusahaan, formula, data bets, atau identitas personal yang tidak diizinkan.</label>
                <button class="rounded-2xl bg-slate-900 px-4 py-3 text-sm font-bold text-white md:col-span-2">Simpan Studi Kasus Industri</button>
            </form>
        @elseif($isHealthOffice)
            <form method="POST" action="{{ route('student.pkpa-portfolios.cases.store', $portfolio) }}" class="mt-5 grid gap-4 md:grid-cols-2">
                @csrf
                <label class="grid gap-2"><span class="text-sm font-bold text-slate-700">Judul Kasus</span><input name="case_code" value="{{ old('case_code') }}" class="rounded-2xl border-slate-200 text-sm" required></label>
                <label class="grid gap-2"><span class="text-sm font-bold text-slate-700">Tanggal</span><input type="date" name="case_date" value="{{ old('case_date') }}" class="rounded-2xl border-slate-200 text-sm"></label>
                @foreach([
                    'medication_use' => 'Identitas Kasus (lokasi, unit, waktu, dan pihak terkait)', 'complaint' => 'Latar Belakang',
                    'diagnosis' => 'Identifikasi Masalah', 'history' => 'Tujuan Analisis', 'past_medical_history' => 'Data Kasus',
                    'drp' => 'Analisis Masalah', 'intervention' => 'Alternatif Solusi', 'monitoring' => 'Rekomendasi',
                    'conclusion' => 'Kesimpulan', 'references' => 'Daftar Pustaka',
                ] as $name => $label)
                    <label class="grid gap-2 {{ in_array($name, ['complaint', 'past_medical_history', 'drp'], true) ? 'md:col-span-2' : '' }}"><span class="text-sm font-bold text-slate-700">{{ $label }}</span><textarea name="{{ $name }}" rows="4" class="min-h-36 resize-y rounded-2xl border-slate-200 text-sm">{{ old($name) }}</textarea></label>
                @endforeach
                <label class="flex items-start gap-2 text-sm font-semibold text-slate-700 md:col-span-2"><input type="checkbox" name="anonymization_confirmed" value="1" class="mt-1" required> Saya memastikan data yang ditulis telah disamarkan dan tidak memuat informasi terbatas yang tidak diizinkan.</label>
                <button class="rounded-2xl bg-slate-900 px-4 py-3 text-sm font-bold text-white md:col-span-2">Simpan Studi Kasus Dinas Kesehatan</button>
            </form>
        @else
        @php
            $drugRows = range(0, 2);
            $drpTypes = [
                'Indikasi tanpa obat', 'Obat tanpa indikasi', 'Dosis terlalu rendah', 'Dosis terlalu tinggi',
                'Interaksi obat', 'Efek samping obat', 'Ketidakpatuhan pasien', 'Duplikasi terapi', 'Lainnya',
            ];
        @endphp
        <form method="POST" action="{{ route('student.pkpa-portfolios.cases.store', $portfolio) }}" class="mt-5 space-y-7">
            @csrf
            <fieldset class="grid gap-4 border-t border-slate-200 pt-5 md:grid-cols-2">
                <legend class="pr-3 text-base font-black text-slate-950">A. Identitas Pasien</legend>
                <label class="grid gap-2"><span class="text-sm font-bold text-slate-700">Nomor Kasus</span><input name="case_code" value="{{ old('case_code') }}" class="rounded-2xl border-slate-200 text-sm" required></label>
                <label class="grid gap-2"><span class="text-sm font-bold text-slate-700">Tanggal</span><input type="date" name="case_date" value="{{ old('case_date') }}" class="rounded-2xl border-slate-200 text-sm"></label>
                <label class="grid gap-2"><span class="text-sm font-bold text-slate-700">Inisial Pasien</span><input name="patient_initials" value="{{ old('patient_initials') }}" maxlength="16" class="rounded-2xl border-slate-200 text-sm"></label>
                <label class="grid gap-2"><span class="text-sm font-bold text-slate-700">Jenis Kelamin</span><input name="gender" value="{{ old('gender') }}" class="rounded-2xl border-slate-200 text-sm"></label>
                <label class="grid gap-2"><span class="text-sm font-bold text-slate-700">Umur</span><input name="age" type="number" min="0" max="130" value="{{ old('age') }}" class="rounded-2xl border-slate-200 text-sm"></label>
                <label class="grid gap-2"><span class="text-sm font-bold text-slate-700">Berat Badan (kg)</span><input name="weight_kg" type="number" step="0.01" min="0" value="{{ old('weight_kg') }}" class="rounded-2xl border-slate-200 text-sm"></label>
                <label class="grid gap-2"><span class="text-sm font-bold text-slate-700">Tinggi Badan (cm)</span><input name="height_cm" type="number" step="0.01" min="0" value="{{ old('height_cm') }}" class="rounded-2xl border-slate-200 text-sm"></label>
                <label class="grid gap-2"><span class="text-sm font-bold text-slate-700">Diagnosis (bila diketahui)</span><input name="diagnosis" value="{{ old('diagnosis') }}" class="rounded-2xl border-slate-200 text-sm"></label>
                <label class="grid gap-2 md:col-span-2"><span class="text-sm font-bold text-slate-700">Keluhan Utama</span><textarea name="complaint" rows="3" class="min-h-28 resize-y rounded-2xl border-slate-200 text-sm">{{ old('complaint') }}</textarea></label>
            </fieldset>

            <fieldset class="grid gap-4 border-t border-slate-200 pt-5 md:grid-cols-2">
                <legend class="pr-3 text-base font-black text-slate-950">B. Riwayat Pasien</legend>
                <label class="grid gap-2"><span class="text-sm font-bold text-slate-700">Riwayat Penyakit Sekarang</span><textarea name="history" rows="4" class="min-h-36 resize-y rounded-2xl border-slate-200 text-sm">{{ old('history') }}</textarea></label>
                <label class="grid gap-2"><span class="text-sm font-bold text-slate-700">Riwayat Penyakit Dahulu</span><textarea name="past_medical_history" rows="4" class="min-h-36 resize-y rounded-2xl border-slate-200 text-sm">{{ old('past_medical_history') }}</textarea></label>
                <label class="grid gap-2"><span class="text-sm font-bold text-slate-700">Riwayat Penyakit Keluarga</span><textarea name="family_history" rows="4" class="min-h-36 resize-y rounded-2xl border-slate-200 text-sm">{{ old('family_history') }}</textarea></label>
                <label class="grid gap-2"><span class="text-sm font-bold text-slate-700">Riwayat Alergi</span><textarea name="allergy" rows="4" class="min-h-36 resize-y rounded-2xl border-slate-200 text-sm">{{ old('allergy') }}</textarea></label>
                <label class="grid gap-2 md:col-span-2"><span class="text-sm font-bold text-slate-700">Riwayat Penggunaan Obat</span><textarea name="medication_use" rows="4" class="min-h-36 resize-y rounded-2xl border-slate-200 text-sm">{{ old('medication_use') }}</textarea></label>
            </fieldset>

            <fieldset class="border-t border-slate-200 pt-5">
                <legend class="pr-3 text-base font-black text-slate-950">C. Data Obat</legend>
                <div class="mt-3 overflow-x-auto rounded-xl border border-slate-200">
                    <table class="min-w-[760px] w-full text-left text-sm">
                        <thead class="bg-slate-50 text-xs font-black uppercase text-slate-600"><tr><th class="p-3">Nama Obat</th><th class="p-3">Dosis</th><th class="p-3">Frekuensi</th><th class="p-3">Rute</th><th class="p-3">Indikasi</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($drugRows as $row)
                                <tr>
                                    <td class="p-2"><input name="drug_data[{{ $row }}][name]" value="{{ old("drug_data.$row.name") }}" class="w-full rounded-xl border-slate-200 text-sm"></td>
                                    <td class="p-2"><input name="drug_data[{{ $row }}][dose]" value="{{ old("drug_data.$row.dose") }}" class="w-full rounded-xl border-slate-200 text-sm"></td>
                                    <td class="p-2"><input name="drug_data[{{ $row }}][frequency]" value="{{ old("drug_data.$row.frequency") }}" class="w-full rounded-xl border-slate-200 text-sm"></td>
                                    <td class="p-2"><input name="drug_data[{{ $row }}][route]" value="{{ old("drug_data.$row.route") }}" class="w-full rounded-xl border-slate-200 text-sm"></td>
                                    <td class="p-2"><input name="drug_data[{{ $row }}][indication]" value="{{ old("drug_data.$row.indication") }}" class="w-full rounded-xl border-slate-200 text-sm"></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </fieldset>

            <fieldset class="grid gap-4 border-t border-slate-200 pt-5 md:grid-cols-2">
                <legend class="pr-3 text-base font-black text-slate-950">D. Analisis SOAP</legend>
                @foreach(['subjective' => 'S (Subjective)', 'objective' => 'O (Objective)', 'assessment' => 'A (Assessment)', 'plan' => 'P (Plan)'] as $key => $label)
                    <label class="grid gap-2"><span class="text-sm font-bold text-slate-700">{{ $label }}</span><textarea name="soap[{{ $key }}]" rows="5" class="min-h-44 resize-y rounded-2xl border-slate-200 text-sm">{{ old("soap.$key") }}</textarea></label>
                @endforeach
            </fieldset>

            <fieldset class="border-t border-slate-200 pt-5">
                <legend class="pr-3 text-base font-black text-slate-950">E. Drug Related Problems (DRP)</legend>
                <div class="mt-3 overflow-x-auto rounded-xl border border-slate-200">
                    <table class="min-w-[720px] w-full text-left text-sm">
                        <thead class="bg-slate-50 text-xs font-black uppercase text-slate-600"><tr><th class="p-3">Jenis DRP</th><th class="p-3">Ada/Tidak</th><th class="p-3">Keterangan</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($drpTypes as $index => $type)
                                <tr>
                                    <td class="p-2"><input name="drp_items[{{ $index }}][type]" value="{{ $type }}" readonly class="w-full border-0 bg-transparent p-1 text-sm font-semibold text-slate-700"></td>
                                    <td class="p-2"><select name="drp_items[{{ $index }}][status]" class="w-full rounded-xl border-slate-200 text-sm"><option value="">Pilih</option><option value="ada" @selected(old("drp_items.$index.status") === 'ada')>Ada</option><option value="tidak" @selected(old("drp_items.$index.status") === 'tidak')>Tidak</option></select></td>
                                    <td class="p-2"><input name="drp_items[{{ $index }}][note]" value="{{ old("drp_items.$index.note") }}" class="w-full rounded-xl border-slate-200 text-sm"></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <label class="mt-4 grid gap-2"><span class="text-sm font-bold text-slate-700">Ringkasan DRP</span><textarea name="drp" rows="3" class="min-h-28 resize-y rounded-2xl border-slate-200 text-sm">{{ old('drp') }}</textarea></label>
            </fieldset>

            <fieldset class="grid gap-4 border-t border-slate-200 pt-5 md:grid-cols-2">
                <legend class="pr-3 text-base font-black text-slate-950">F-J. Tindak Lanjut Kasus</legend>
                <label class="grid gap-2"><span class="text-sm font-bold text-slate-700">Intervensi Apoteker</span><textarea name="intervention" rows="5" class="min-h-44 resize-y rounded-2xl border-slate-200 text-sm">{{ old('intervention') }}</textarea></label>
                <label class="grid gap-2"><span class="text-sm font-bold text-slate-700">Parameter Klinis dan Follow-up</span><textarea name="monitoring" rows="5" class="min-h-44 resize-y rounded-2xl border-slate-200 text-sm">{{ old('monitoring') }}</textarea></label>
                <label class="grid gap-2"><span class="text-sm font-bold text-slate-700">Evaluasi</span><textarea name="evaluation" rows="4" class="min-h-36 resize-y rounded-2xl border-slate-200 text-sm">{{ old('evaluation') }}</textarea></label>
                <label class="grid gap-2"><span class="text-sm font-bold text-slate-700">Edukasi Pasien</span><textarea name="education" rows="4" class="min-h-36 resize-y rounded-2xl border-slate-200 text-sm">{{ old('education') }}</textarea></label>
                <label class="grid gap-2"><span class="text-sm font-bold text-slate-700">Kesimpulan Kasus</span><textarea name="conclusion" rows="4" class="min-h-36 resize-y rounded-2xl border-slate-200 text-sm">{{ old('conclusion') }}</textarea></label>
                <label class="grid gap-2"><span class="text-sm font-bold text-slate-700">Referensi</span><textarea name="references" rows="4" class="min-h-36 resize-y rounded-2xl border-slate-200 text-sm">{{ old('references') }}</textarea></label>
            </fieldset>

            <label class="flex items-start gap-2 text-sm font-semibold text-slate-700"><input type="checkbox" name="anonymization_confirmed" value="1" class="mt-1" required> Saya memastikan tidak ada nama, nomor rekam medis, alamat, atau kontak pasien.</label>
            <button class="w-full rounded-2xl bg-slate-900 px-4 py-3 text-sm font-bold text-white">Simpan Studi Kasus</button>
        </form>
        @endif
    </section>

    <section class="grid gap-6 xl:grid-cols-3">
        <form method="POST" action="{{ route('student.pkpa-portfolios.reflections.store', $portfolio) }}" class="rounded-3xl border border-slate-100 bg-white p-5 shadow-sm">
            @csrf
            <h2 class="text-lg font-black text-slate-950">Refleksi Mingguan</h2>
            <div class="mt-4 grid gap-3">
                <input type="number" name="week_number" min="1" placeholder="Minggu ke-" class="rounded-2xl border-slate-200 text-sm" required>
                <input type="date" name="period_start_date" class="rounded-2xl border-slate-200 text-sm">
                <input type="date" name="period_end_date" class="rounded-2xl border-slate-200 text-sm">
                <input name="unit" placeholder="Unit/Kegiatan utama" class="rounded-2xl border-slate-200 text-sm">
                <textarea name="target" placeholder="Target" class="rounded-2xl border-slate-200 text-sm"></textarea>
                <textarea name="achievement" placeholder="Pencapaian" class="rounded-2xl border-slate-200 text-sm"></textarea>
                <textarea name="obstacle" placeholder="Hambatan" class="rounded-2xl border-slate-200 text-sm"></textarea>
                <textarea name="solution" placeholder="Solusi" class="rounded-2xl border-slate-200 text-sm"></textarea>
                <textarea name="next_plan" placeholder="Rencana minggu berikutnya" class="rounded-2xl border-slate-200 text-sm"></textarea>
                <button class="rounded-2xl bg-slate-900 px-4 py-3 text-sm font-bold text-white">Simpan Refleksi</button>
            </div>
        </form>

        <form method="POST" action="{{ route('student.pkpa-portfolios.self-assessments.store', $portfolio) }}" class="rounded-3xl border border-slate-100 bg-white p-5 shadow-sm">
            @csrf
            <h2 class="text-lg font-black text-slate-950">Self Assessment</h2>
            <div class="mt-4 grid gap-3">
                <select name="aspect" class="rounded-2xl border-slate-200 text-sm" required>
                    <option value="">Pilih kompetensi</option>
                    @foreach($selfAssessmentAspects as $aspect)
                        <option value="{{ $aspect }}">{{ $aspect }}</option>
                    @endforeach
                </select>
                <input type="number" name="score" min="1" max="5" placeholder="Skor 1-5" class="rounded-2xl border-slate-200 text-sm" required>
                <textarea name="evidence_experience" placeholder="Bukti/pengalaman selama PKPA" class="rounded-2xl border-slate-200 text-sm"></textarea>
                <textarea name="strength" placeholder="Kelebihan" class="rounded-2xl border-slate-200 text-sm"></textarea>
                <textarea name="weakness" placeholder="Kekurangan" class="rounded-2xl border-slate-200 text-sm"></textarea>
                <textarea name="improvement_plan" placeholder="Upaya perbaikan" class="rounded-2xl border-slate-200 text-sm"></textarea>
                <textarea name="final_reflection" placeholder="Refleksi akhir" class="rounded-2xl border-slate-200 text-sm"></textarea>
                <button class="rounded-2xl bg-slate-900 px-4 py-3 text-sm font-bold text-white">Simpan Self Assessment</button>
            </div>
        </form>

        <form method="POST" enctype="multipart/form-data" action="{{ route('student.pkpa-portfolios.documentation.store', $portfolio) }}" class="rounded-3xl border border-slate-100 bg-white p-5 shadow-sm">
            @csrf
            <h2 class="text-lg font-black text-slate-950">Dokumentasi Kegiatan</h2>
            <div class="mt-4 grid gap-3">
                <select name="category" class="rounded-2xl border-slate-200 text-sm">
                    <option value="">Pilih kategori dokumentasi</option>
                    @foreach($documentationCategories as $category)
                        <option value="{{ $category }}">{{ $category }}</option>
                    @endforeach
                </select>
                <input name="activity" placeholder="Kegiatan" class="rounded-2xl border-slate-200 text-sm" required>
                <input type="date" name="activity_date" class="rounded-2xl border-slate-200 text-sm">
                <input name="competency_label" placeholder="Kompetensi terkait" class="rounded-2xl border-slate-200 text-sm">
                <textarea name="description" placeholder="Keterangan dokumentasi" class="rounded-2xl border-slate-200 text-sm"></textarea>
                <input type="file" name="file" class="rounded-2xl border border-slate-200 p-2 text-sm">
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="anonymization_confirmed" value="1" required> Identitas pasien disamarkan</label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="consent_confirmed" value="1" required> Sudah ada izin dokumentasi</label>
                <button class="rounded-2xl bg-slate-900 px-4 py-3 text-sm font-bold text-white">Simpan Dokumentasi</button>
            </div>
        </form>
    </section>

    <section class="rounded-3xl border border-slate-100 bg-white p-5 shadow-sm">
        <h2 class="text-lg font-black text-slate-950">Pemeriksaan</h2>
        <div class="mt-4 flex flex-wrap gap-3">
            <form method="POST" action="{{ route('student.pkpa-portfolios.submit', $portfolio) }}">@csrf<button class="rounded-2xl bg-cyan-700 px-4 py-3 text-sm font-bold text-white">Kirim ke Preseptor</button></form>
            <form method="POST" action="{{ route('student.pkpa-portfolios.submit-internal', $portfolio) }}">@csrf<button class="rounded-2xl bg-cyan-700 px-4 py-3 text-sm font-bold text-white">Kirim ke Pembimbing Dalam</button></form>
        </div>
    </section>
</div>
@endsection
