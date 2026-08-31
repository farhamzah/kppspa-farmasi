@extends('layouts.app')
@section('title', 'Pemeriksaan Portofolio Pembimbing Dalam')
@section('page_title', 'Pemeriksaan Portofolio')
@section('content')
<div class="space-y-6">
    <section class="rounded-3xl border border-slate-100 bg-white p-6 shadow-sm"><p class="text-sm font-bold uppercase tracking-wide text-cyan-700">Pemeriksaan Portofolio</p><h1 class="mt-2 text-3xl font-black text-slate-950">Antrian Pembimbing Dalam</h1><p class="mt-2 text-sm text-slate-600">Semua mahasiswa bimbingan tampil di sini. Portofolio baru dapat diperiksa setelah mahasiswa mulai membuatnya.</p></section>
    <div class="grid gap-4">
        @forelse($assignments as $assignment)
            @php($run = $assignment->rotationRuns->first())
            @php($portfolio = $run?->currentPortfolio)
            <article class="rounded-3xl border border-slate-100 bg-white p-5 shadow-sm"><div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between"><div><p class="font-black text-slate-950">{{ $assignment->student_name_snapshot }}</p><p class="mt-1 text-sm text-slate-500">{{ $assignment->practice_domain_name_snapshot }} - {{ $assignment->practice_site_name_snapshot }}</p><p class="mt-2 text-sm font-semibold {{ $portfolio ? 'text-cyan-700' : 'text-slate-500' }}">{{ $portfolio ? $portfolio->statusLabel() : ($run ? 'Belum ada portofolio yang diisi mahasiswa' : 'Runtime rotasi belum dibentuk') }}</p></div>@if($portfolio)<a href="{{ route('internal-supervisor.pkpa-portfolios.show', $portfolio) }}" class="rounded-2xl bg-cyan-700 px-4 py-3 text-center text-sm font-bold text-white">Periksa</a>@else<span class="rounded-2xl border border-slate-200 px-4 py-3 text-center text-sm font-bold text-slate-500">Belum dapat diperiksa</span>@endif</div></article>
        @empty
            <div class="rounded-3xl border border-slate-100 bg-white p-6 text-sm text-slate-500">Belum ada mahasiswa PKPA yang menjadi bimbingan Anda.</div>
        @endforelse
    </div>
</div>
@endsection
