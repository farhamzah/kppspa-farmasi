@extends('layouts.app')

@section('title', 'Penyelesaian PKPA')

@section('content')
<div class="space-y-6">
    <div class="rounded-3xl border border-sky-100 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-sm font-bold uppercase tracking-wide text-cyan-700">Penyelesaian Program PKPA</p>
                <h1 class="mt-2 text-3xl font-black text-slate-950">Nilai akhir, remedial, kelengkapan persyaratan, dan kelulusan</h1>
                <p class="mt-2 max-w-3xl text-sm text-slate-600">Hasil ini adalah hasil akademik Program PKPA dalam MY PSPA. Dokumen resmi universitas mengikuti proses administrasi terpisah.</p>
            </div>
            <a href="{{ route('management.pkpa-final-program.export') }}" class="rounded-2xl bg-cyan-700 px-4 py-3 text-sm font-bold text-white">Ekspor Rekap</a>
        </div>
    </div>

    @if (session('status'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-800">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-bold text-rose-800">{{ $errors->first() }}</div>
    @endif

    <div class="grid gap-4 md:grid-cols-6">
        @foreach ($summary as $label => $value)
            @php
                $summaryLabels = [
                    'schemes' => 'Skema',
                    'calculations' => 'Perhitungan',
                    'finalized' => 'Difinalisasi',
                    'decisions' => 'Keputusan',
                    'released' => 'Dirilis',
                    'remedial' => 'Remedial',
                ];
            @endphp
            <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
                <p class="text-xs font-bold uppercase text-slate-500">{{ $summaryLabels[$label] ?? str($label)->headline() }}</p>
                <p class="mt-2 text-2xl font-black text-slate-950">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
        <section class="rounded-3xl border border-slate-100 bg-white p-5 shadow-sm">
            <h2 class="text-xl font-black text-slate-950">Penyusun Skema Akhir</h2>
            <div class="mt-4 space-y-4">
                @foreach ($programs as $program)
                    <div class="rounded-2xl border border-slate-100 p-4">
                        <p class="font-black text-slate-900">{{ $program->name }}</p>
                        <p class="text-xs text-slate-500">{{ $program->academic_year }} - {{ $program->domains->count() }} wahana</p>
                        <form method="POST" action="{{ route('management.pkpa-final-schemes.store', $program) }}" class="mt-3 grid gap-3 sm:grid-cols-2">
                            @csrf
                            <input name="code" placeholder="Kode skema" class="rounded-2xl border-slate-200 text-sm" required>
                            <input name="name" placeholder="Nama skema akhir" class="rounded-2xl border-slate-200 text-sm" required>
                            <input name="maximum_score" type="number" step="0.01" value="100" class="rounded-2xl border-slate-200 text-sm">
                            <button class="rounded-2xl bg-slate-900 px-4 py-2 text-sm font-bold text-white">Buat Skema</button>
                        </form>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="rounded-3xl border border-slate-100 bg-white p-5 shadow-sm">
            <h2 class="text-xl font-black text-slate-950">Skema dan Bobot</h2>
            <div class="mt-4 space-y-4">
                @foreach ($schemes as $scheme)
                    <div class="rounded-2xl border border-slate-100 p-4">
                        @php $weight = $scheme->components->where('status','active')->sum(fn($c)=>(float)$c->weight_percentage); @endphp
                        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                            <div>
                                <p class="font-black text-slate-900">{{ $scheme->code }} v{{ $scheme->version_number }}</p>
                                <p class="text-xs text-slate-500">Bobot aktif {{ number_format($weight,2) }}% - {{ $scheme->status }}</p>
                            </div>
                            <form method="POST" action="{{ route('management.pkpa-final-schemes.activate', $scheme) }}">
                                @csrf
                                <button class="rounded-xl bg-cyan-700 px-3 py-2 text-xs font-bold text-white">Aktifkan</button>
                            </form>
                        </div>

                        @if($scheme->status === 'draft')
                            <form method="POST" action="{{ route('management.pkpa-final-components.store', $scheme) }}" class="mt-3 grid gap-2 md:grid-cols-5">
                                @csrf
                                <input name="code" placeholder="Kode" class="rounded-xl border-slate-200 text-xs" required>
                                <input name="name" placeholder="Komponen" class="rounded-xl border-slate-200 text-xs" required>
                                <select name="component_type" class="rounded-xl border-slate-200 text-xs">
                                    <option value="wahana_grade">Nilai Wahana</option>
                                    <option value="custom">Manual</option>
                                </select>
                                <select name="source_practice_domain_id" class="rounded-xl border-slate-200 text-xs">
                                    <option value="">Wahana</option>
                                    @foreach($programs->flatMap->domains->unique('practice_domain_id') as $domain)
                                        <option value="{{ $domain->practice_domain_id }}">{{ $domain->practiceDomain?->name }}</option>
                                    @endforeach
                                </select>
                                <input name="weight_percentage" type="number" step="0.0001" placeholder="Bobot" class="rounded-xl border-slate-200 text-xs" required>
                                <button class="rounded-xl bg-slate-900 px-3 py-2 text-xs font-bold text-white md:col-span-5">Tambah Komponen</button>
                            </form>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>
    </div>

    <section class="rounded-3xl border border-slate-100 bg-white p-5 shadow-sm">
        <h2 class="text-xl font-black text-slate-950">Monitoring Peserta</h2>
        <div class="mt-4 grid gap-4">
            @foreach ($enrollments as $enrollment)
                <article class="rounded-2xl border border-slate-100 p-4">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <p class="font-black text-slate-900">{{ $enrollment->student_name_snapshot }} - {{ $enrollment->core_user_id }}</p>
                            <p class="text-sm text-slate-500">{{ $enrollment->program?->name }} - status {{ $enrollment->status }}</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <form method="POST" action="{{ route('management.pkpa-final-calculations.store', $enrollment) }}">
                                @csrf
                                <button class="rounded-xl bg-cyan-700 px-3 py-2 text-xs font-bold text-white">Hitung Nilai Akhir</button>
                            </form>
                            <form method="POST" action="{{ route('management.pkpa-remedials.store', $enrollment) }}">
                                @csrf
                                <input type="hidden" name="reason" value="Dibuka dari monitoring koordinator">
                                <button class="rounded-xl bg-amber-600 px-3 py-2 text-xs font-bold text-white">Buka Remedial</button>
                            </form>
                        </div>
                    </div>

                    <div class="mt-3 grid gap-2 md:grid-cols-3 xl:grid-cols-6">
                        @foreach($enrollment->requirements as $requirement)
                            <div class="rounded-xl bg-slate-50 p-3 text-xs">
                                <p class="font-bold text-slate-800">{{ $requirement->practiceDomain?->name }}</p>
                                <p class="text-slate-500">{{ $requirement->status }}</p>
                                <form method="POST" action="{{ route('management.pkpa-requirements.completion.evaluate', $requirement) }}" class="mt-2">
                                    @csrf
                                    <button class="font-bold text-cyan-700">Cek</button>
                                </form>
                            </div>
                        @endforeach
                    </div>
                </article>
            @endforeach
        </div>
        <div class="mt-4">{{ $enrollments->links() }}</div>
    </section>
</div>
@endsection
