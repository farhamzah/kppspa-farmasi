@extends('layouts.app')
@section('title', 'Pemeriksaan Portofolio Preseptor')
@section('page_title', 'Pemeriksaan Portofolio')
@section('content')
@php($runsByDomain = $runs->groupBy(fn ($run) => $run->practiceDomain?->name ?: 'Wahana belum ditentukan'))
<div class="space-y-6">
    <section class="rounded-3xl border border-slate-100 bg-white p-6 shadow-sm"><p class="text-sm font-bold uppercase tracking-wide text-cyan-700">Pemeriksaan Portofolio</p><h1 class="mt-2 text-3xl font-black text-slate-950">Antrean Preseptor</h1><p class="mt-2 text-sm text-slate-600">Portofolio dikelompokkan berdasarkan wahana agar antrean tetap mudah dipindai saat menangani lebih dari satu wahana.</p></section>
    @forelse($runsByDomain as $domainName => $domainRuns)
        <section class="overflow-hidden rounded-2xl border border-sky-100 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4"><div><p class="text-xs font-black uppercase tracking-widest text-cyan-700">Wahana PKPA</p><h2 class="mt-1 text-xl font-black text-slate-950">{{ $domainName }}</h2></div><span class="rounded-full bg-sky-50 px-3 py-1 text-xs font-bold text-sky-700">{{ $domainRuns->count() }} mahasiswa</span></div>
            <div class="divide-y divide-slate-100">
                @foreach($domainRuns as $run)
                    @php($portfolio = $run->currentPortfolio)
                    <article class="flex flex-col gap-4 px-5 py-5 md:flex-row md:items-center md:justify-between"><div><p class="font-black text-slate-950">{{ $run->studentDisplayName() }}</p><p class="mt-1 text-sm text-slate-500">{{ $run->practiceSite?->name }}</p><p class="mt-2 text-sm font-semibold {{ $portfolio ? 'text-cyan-700' : 'text-slate-500' }}">{{ $portfolio ? $portfolio->statusLabel() : 'Belum ada portofolio yang diisi mahasiswa' }}</p></div>@if($portfolio)<a href="{{ route('field-supervisor.pkpa-portfolios.show', $portfolio) }}" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-cyan-700 px-5 py-2 text-sm font-bold text-white">Periksa</a>@else<span class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-200 px-5 py-2 text-sm font-bold text-slate-500">Belum dapat diperiksa</span>@endif</article>
                @endforeach
            </div>
        </section>
    @empty
        <div class="rounded-2xl border border-slate-100 bg-white p-6 text-sm text-slate-500">Belum ada mahasiswa PKPA pada penempatan Anda.</div>
    @endforelse
</div>
@endsection
