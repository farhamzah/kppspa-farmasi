@extends('layouts.app')

@section('title', 'Pemeriksaan Portofolio Pembimbing Dalam')
@section('page_title', 'Pemeriksaan Portofolio')

@section('content')
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
        <h2 class="text-lg font-black text-slate-950">Refleksi dan Penilaian Diri</h2>
        <p class="mt-2 text-sm text-slate-600">Refleksi: {{ $portfolio->weeklyReflections->count() }}. Penilaian diri: {{ $portfolio->selfAssessments->count() }}. Gunakan catatan revisi bila mahasiswa masih perlu melengkapi isi portofolio.</p>
        <div class="mt-4 flex flex-wrap gap-3">
            <form method="POST" action="{{ route('internal-supervisor.pkpa-portfolios.approve', $portfolio) }}">
                @csrf
                <button class="rounded-2xl bg-cyan-700 px-4 py-3 text-sm font-bold text-white">Setujui</button>
            </form>
            <form method="POST" action="{{ route('internal-supervisor.pkpa-portfolios.revision', $portfolio) }}" class="flex flex-wrap gap-3">
                @csrf
                <input name="comments" placeholder="Catatan revisi" class="rounded-2xl border-slate-200 text-sm" required>
                <button class="rounded-2xl bg-amber-600 px-4 py-3 text-sm font-bold text-white">Minta Revisi</button>
            </form>
        </div>
    </section>
</div>
@endsection
