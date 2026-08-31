@extends('layouts.app')
@section('title', 'Monitoring PKPA')
@section('page_title', 'Monitoring PKPA')
@section('content')
@php
    $totalRuns = $runs->count();
    $readyToValidate = $runs->sum(fn ($run) => $run->logbookEntries->whereIn('status', ['field_approved', 'approved'])->count());
@endphp
<div class="space-y-5">
<section class="grid gap-4 md:grid-cols-2">
    <article class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <p class="text-xs font-black uppercase tracking-widest text-slate-500">Mahasiswa Bimbingan</p>
        <p class="mt-3 text-3xl font-black text-slate-950">{{ $totalRuns }}</p>
        <p class="mt-1 text-sm text-slate-500">Rotasi yang dapat Anda monitor dari sisi akademik.</p>
    </article>
    <article class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <p class="text-xs font-black uppercase tracking-widest text-cyan-700">Logbook Menunggu Validasi</p>
        <p class="mt-3 text-3xl font-black text-cyan-700">{{ $readyToValidate }}</p>
        <p class="mt-1 text-sm text-slate-500">Logbook yang sudah tervalidasi preseptor dan menunggu keputusan Anda.</p>
    </article>
</section>
<div class="grid gap-4 lg:grid-cols-2">
@forelse($runs as $run)
    <a href="{{ route('internal-supervisor.pkpa-operations.show', $run) }}" class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-sky-100">
        <p class="text-xs font-black uppercase tracking-widest text-cyan-700">{{ $run->practiceDomain?->name }}</p>
        <h2 class="mt-1 text-xl font-black text-slate-950">{{ $run->studentDisplayName() }}</h2>
        <p class="mt-1 text-sm text-slate-500">{{ $run->studentDisplaySecondary() }}</p>
        <p class="mt-3 text-sm font-semibold text-slate-700">{{ $run->practiceSite?->name }}</p>
        <p class="mt-2 text-sm text-slate-500">{{ $run->logbookEntries->whereIn('status', ['field_approved', 'approved'])->count() }} logbook menunggu validasi Anda.</p>
    </a>
@empty
    <div class="rounded-2xl bg-white p-6 text-sm text-slate-500 shadow-sm ring-1 ring-sky-100">Belum ada rotasi operasional untuk monitoring.</div>
@endforelse
</div>
</div>
@endsection
