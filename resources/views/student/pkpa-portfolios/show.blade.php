@extends('layouts.app')

@section('title', 'Detail Portofolio PKPA')
@section('page_title', 'Detail Portofolio PKPA')

@php
    $isApotek = \App\Support\PkpaApotekPortfolio::isApotekCode($portfolio->practiceDomain?->code);
    $editableSections = $isApotek ? \App\Support\PkpaApotekPortfolio::editableSections() : [];
    $sectionRecords = $portfolio->sectionRecords->keyBy('section_code');
    $reportCodes = \App\Support\PkpaApotekPortfolio::reportSectionCodes();
    $completedSections = collect($editableSections)
        ->keys()
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
    $documentationCategories = [
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
    ];
    $selfAssessmentAspects = [
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
    ];
@endphp

@section('content')
<div class="space-y-6">
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
            <p class="text-xs font-bold uppercase text-slate-500">Bagian Apotek</p>
            <p class="mt-2 text-2xl font-black text-slate-950">{{ $completedSections }} / {{ count($editableSections) }}</p>
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
                                <dd class="mt-1 text-sm leading-6 text-slate-800">{{ $value }}</dd>
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
            <p class="mt-2 text-sm text-slate-600">{{ $portfolio->integrity_pact_text }}</p>
            <form method="POST" action="{{ route('student.pkpa-portfolios.integrity.acknowledge', $portfolio) }}" class="mt-4">
                @csrf
                <button class="rounded-2xl bg-slate-900 px-4 py-3 text-sm font-bold text-white">Setujui Pakta</button>
            </form>
        </section>
        <section class="rounded-3xl border border-slate-100 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-black text-slate-950">Unduhan</h2>
            <p class="mt-2 text-sm text-slate-600">Unduhan sementara tetap berbentuk draf internal, tetapi isinya mulai mengikuti struktur portofolio PKPA Apotek.</p>
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
                    <section class="rounded-3xl border border-slate-100 bg-white p-5 shadow-sm">
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
                                        <textarea name="{{ $field['name'] }}" rows="{{ $field['rows'] ?? 3 }}" class="rounded-2xl border-slate-200 text-sm">{{ old($field['name'], $payload[$field['name']] ?? '') }}</textarea>
                                    @else
                                        <input name="{{ $field['name'] }}" value="{{ old($field['name'], $payload[$field['name']] ?? '') }}" class="rounded-2xl border-slate-200 text-sm">
                                    @endif
                                </label>
                            @endforeach
                            <button class="rounded-2xl bg-slate-900 px-4 py-3 text-sm font-bold text-white">Simpan {{ $definition['title'] }}</button>
                        </form>
                    </section>
                @endforeach
            </div>

            <section class="rounded-3xl border border-slate-100 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-black text-slate-950">Laporan Kegiatan PKPA Apotek</h2>
                <p class="mt-2 text-sm text-slate-600">Setiap topik kegiatan mengikuti format tujuan, kegiatan, dan hasil sesuai portofolio PKPA Apotek.</p>
                <div class="mt-4 grid gap-5 xl:grid-cols-2">
                    @foreach($reportCodes as $sectionCode)
                        @php
                            $definition = $editableSections[$sectionCode];
                            $record = $sectionRecords->get($sectionCode);
                            $payload = $record?->manual_payload ?? [];
                        @endphp
                        <section class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                            <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                                <div>
                                    <h3 class="text-base font-black text-slate-950">{{ $definition['title'] }}</h3>
                                    <p class="mt-1 text-sm text-slate-600">{{ $definition['description'] }}</p>
                                    <p class="mt-2 text-xs font-semibold uppercase tracking-wide text-cyan-700">{{ $definition['activity_hint'] }}</p>
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
                                        <textarea name="{{ $field['name'] }}" rows="{{ $field['rows'] ?? 3 }}" class="rounded-2xl border-slate-200 text-sm">{{ old($field['name'], $payload[$field['name']] ?? '') }}</textarea>
                                    </label>
                                @endforeach
                                <button class="rounded-2xl bg-white px-4 py-3 text-sm font-bold text-slate-900 ring-1 ring-inset ring-slate-200">Simpan Topik</button>
                            </form>
                        </section>
                    @endforeach
                </div>
            </section>
        </section>
    @endif

    <section class="rounded-3xl border border-slate-100 bg-white p-5 shadow-sm">
        <h2 class="text-lg font-black text-slate-950">Studi Kasus</h2>
        <p class="mt-2 text-sm text-slate-600">Format studi kasus mengikuti case report PKPA Apotek: identitas singkat pasien anonim, riwayat, DRP, intervensi, monitoring, edukasi, dan referensi.</p>
        <form method="POST" action="{{ route('student.pkpa-portfolios.cases.store', $portfolio) }}" class="mt-4 grid gap-3 md:grid-cols-2">
            @csrf
            <input name="case_code" placeholder="Nomor kasus" class="rounded-2xl border-slate-200 text-sm" required>
            <input type="date" name="case_date" class="rounded-2xl border-slate-200 text-sm">
            <input name="patient_initials" placeholder="Inisial pasien" class="rounded-2xl border-slate-200 text-sm">
            <input name="gender" placeholder="Jenis kelamin" class="rounded-2xl border-slate-200 text-sm">
            <input name="age" type="number" placeholder="Umur" class="rounded-2xl border-slate-200 text-sm">
            <input name="weight_kg" type="number" step="0.01" placeholder="Berat badan (kg)" class="rounded-2xl border-slate-200 text-sm">
            <input name="height_cm" type="number" step="0.01" placeholder="Tinggi badan (cm)" class="rounded-2xl border-slate-200 text-sm">
            <input name="diagnosis" placeholder="Diagnosis (bila diketahui)" class="rounded-2xl border-slate-200 text-sm">
            <textarea name="complaint" placeholder="Keluhan utama" class="rounded-2xl border-slate-200 text-sm md:col-span-2"></textarea>
            <textarea name="history" placeholder="Riwayat pasien" class="rounded-2xl border-slate-200 text-sm md:col-span-2"></textarea>
            <textarea name="allergy" placeholder="Riwayat alergi" class="rounded-2xl border-slate-200 text-sm md:col-span-2"></textarea>
            <textarea name="medication_use" placeholder="Riwayat penggunaan obat" class="rounded-2xl border-slate-200 text-sm md:col-span-2"></textarea>
            <textarea name="drp" placeholder="Drug Related Problems (DRP)" class="rounded-2xl border-slate-200 text-sm md:col-span-2"></textarea>
            <textarea name="intervention" placeholder="Intervensi apoteker" class="rounded-2xl border-slate-200 text-sm md:col-span-2"></textarea>
            <textarea name="monitoring" placeholder="Monitoring dan evaluasi" class="rounded-2xl border-slate-200 text-sm md:col-span-2"></textarea>
            <textarea name="education" placeholder="Edukasi pasien" class="rounded-2xl border-slate-200 text-sm md:col-span-2"></textarea>
            <textarea name="conclusion" placeholder="Kesimpulan kasus" class="rounded-2xl border-slate-200 text-sm md:col-span-2"></textarea>
            <textarea name="references" placeholder="Referensi" class="rounded-2xl border-slate-200 text-sm md:col-span-2"></textarea>
            <label class="flex items-center gap-2 text-sm font-semibold md:col-span-2"><input type="checkbox" name="anonymization_confirmed" value="1" required> Saya memastikan tidak ada nama, nomor rekam medis, alamat, atau kontak pasien.</label>
            <button class="rounded-2xl bg-slate-900 px-4 py-3 text-sm font-bold text-white md:col-span-2">Simpan Studi Kasus</button>
        </form>
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
