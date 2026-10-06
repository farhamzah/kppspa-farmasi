@extends('layouts.app')

@section('title', 'Pemeriksaan Portofolio Preseptor')
@section('page_title', 'Pemeriksaan Portofolio')

@section('content')
@php
    $isApotek = \App\Support\PkpaApotekPortfolio::isApotekCode($portfolio->practiceDomain?->code);
    $isPbf = \App\Support\PkpaPbfPortfolio::isPbfCode($portfolio->practiceDomain?->code);
    $isHospital = \App\Support\PkpaHospitalPortfolio::isHospitalCode($portfolio->practiceDomain?->code);
    $isIndustry = \App\Support\PkpaIndustryPortfolio::isIndustryCode($portfolio->practiceDomain?->code);
    $isPuskesmas = $portfolio->template?->code === \App\Support\PkpaPuskesmasPortfolio::TEMPLATE_CODE;
    $isHealthOffice = $portfolio->template?->code === \App\Support\PkpaHealthOfficePortfolio::TEMPLATE_CODE;
    $isLokaPom = $portfolio->template?->code === \App\Support\PkpaLokaPomPortfolio::TEMPLATE_CODE;
    $editableSections = $isApotek
        ? \App\Support\PkpaApotekPortfolio::editableSections()
        : ($isPbf
            ? \App\Support\PkpaPbfPortfolio::editableSections()
            : ($isHospital
            ? \App\Support\PkpaHospitalPortfolio::editableSections()
            : ($isIndustry ? \App\Support\PkpaIndustryPortfolio::editableSections()
                : ($isPuskesmas ? \App\Support\PkpaPuskesmasPortfolio::editableSections()
                    : ($isHealthOffice ? \App\Support\PkpaHealthOfficePortfolio::editableSections()
                        : ($isLokaPom ? \App\Support\PkpaLokaPomPortfolio::editableSections() : []))))));
    $sectionRecords = $portfolio->sectionRecords->keyBy('section_code');
    $canReview = config('my_pkpa.preceptor_document_validation_enabled') && $portfolio->status === 'submitted_to_field_supervisor';
    $isComplete = (bool) data_get($portfolio->progress_snapshot, 'ready_to_submit', false);
    $blockingNotes = data_get($portfolio->progress_snapshot, 'blocking', []);
@endphp
<div class="space-y-6">
    <section class="rounded-3xl border border-slate-100 bg-white p-6 shadow-sm">
        <p class="text-sm font-bold uppercase tracking-wide text-cyan-700">Pemeriksaan Portofolio</p>
        <h1 class="mt-2 text-3xl font-black text-slate-950">{{ data_get($portfolio->identity_snapshot, 'student_name') }}</h1>
        <p class="mt-2 text-sm text-slate-600">{{ $portfolio->statusLabel() }} · {{ $portfolio->practiceDomain?->name }} · {{ data_get($portfolio->placement_snapshot, 'practice_site') }}</p>
    </section>

    @if(session('status'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-bold text-emerald-800">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm font-bold text-rose-800">{{ $errors->first() }}</div>
    @endif

    <section class="rounded-3xl border border-slate-100 bg-white p-5 shadow-sm">
        <h2 class="text-lg font-black text-slate-950">Studi Kasus dan Bukti Kegiatan</h2>
        <p class="mt-2 text-sm text-slate-600">Studi kasus: {{ $portfolio->caseReports->count() }}. Dokumentasi: {{ $portfolio->documentationItems->count() }}. Gunakan catatan revisi bila isi portofolio masih perlu diperjelas atau dilengkapi.</p>
        @if($canReview)
            @unless($isComplete)
                <div class="mt-4 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                    <p class="font-black">Belum dapat diverifikasi</p>
                    <p class="mt-1">Masih ada {{ count($blockingNotes) }} bagian yang perlu diselesaikan. Gunakan <strong>Minta Revisi</strong> agar mahasiswa dapat memperbaiki isian.</p>
                </div>
            @endunless
            <div class="mt-4 flex flex-wrap gap-3">
            @if($isComplete)
                <form method="POST" action="{{ route('field-supervisor.pkpa-portfolios.verify', $portfolio) }}">
                    @csrf
                    <button class="rounded-2xl bg-cyan-700 px-4 py-3 text-sm font-bold text-white">Verifikasi Portofolio</button>
                </form>
            @endif
            <form method="POST" action="{{ route('field-supervisor.pkpa-portfolios.revision', $portfolio) }}" class="flex flex-wrap gap-3">
                @csrf
                <input name="comments" placeholder="Catatan revisi" class="rounded-2xl border-slate-200 text-sm" required>
                <button class="rounded-2xl bg-amber-600 px-4 py-3 text-sm font-bold text-white">Minta Revisi</button>
            </form>
            </div>
        @else
            <div class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
                Portofolio ini tidak sedang menunggu pemeriksaan Preseptor. Isinya tetap dapat dibaca, tetapi tindakan pemeriksaan tidak tersedia pada status <strong>{{ $portfolio->statusLabel() }}</strong>.
            </div>
        @endif
    </section>

    @if($isApotek || $isPbf || $isHospital || $isIndustry || $isPuskesmas || $isHealthOffice || $isLokaPom)
        <section class="rounded-3xl border border-slate-100 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-black text-slate-950">Bagian Portofolio {{ $isPbf ? 'PBF' : ($isLokaPom ? 'Loka POM' : ($isHealthOffice ? 'Dinas Kesehatan' : ($isHospital ? 'Rumah Sakit' : ($isIndustry ? 'Industri Farmasi' : ($isPuskesmas ? 'Puskesmas' : 'Apotek'))))) }}</h2>
            <div class="mt-4 grid gap-4 xl:grid-cols-2">
                @foreach($editableSections as $code => $definition)
                    @php
                        $record = $sectionRecords->get($code);
                        $lines = $isApotek
                            ? \App\Support\PkpaApotekPortfolio::summaryLines($code, $record?->manual_payload ?? [])
                            : collect($definition['fields'] ?? [])->map(function ($field) use ($record) {
                                $value = data_get($record?->manual_payload, $field['name']);
                                return filled($value) ? $field['label'].': '.$value : null;
                            })->filter()->values()->all();
                    @endphp
                    <article class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="text-sm font-black text-slate-950">{{ $definition['title'] }}</h3>
                                <p class="mt-1 text-xs text-slate-500">{{ $definition['description'] }}</p>
                            </div>
                            <span class="rounded-full px-3 py-1 text-xs font-bold {{ $record?->status === 'completed' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">
                                {{ $record?->status === 'completed' ? 'Lengkap' : 'Belum lengkap' }}
                            </span>
                        </div>
                        <div class="mt-3 space-y-2 text-sm text-slate-700">
                            @forelse($lines as $line)
                                <p class="whitespace-pre-line leading-6">{{ \Illuminate\Support\Str::limit($line, 600) }}</p>
                            @empty
                                <p class="text-slate-500">Belum ada isi yang disimpan di bagian ini.</p>
                            @endforelse
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    @endif
</div>
@endsection
