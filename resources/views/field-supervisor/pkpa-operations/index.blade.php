@extends('layouts.app')
@section('title', 'Operasional PKPA')
@section('page_title', 'Operasional PKPA')
@section('content')
@php
    $totalRuns = $runs->count();
    $pendingAttendance = $runs->sum(fn ($run) => $run->attendanceRecords->where('submission_status', 'submitted')->count());
    $pendingLogbooks = $runs->sum(fn ($run) => $run->logbookEntries->where('status', 'submitted')->count());
@endphp
<div class="space-y-5">
<section class="grid gap-4 md:grid-cols-3">
    <article class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <p class="text-xs font-black uppercase tracking-widest text-slate-500">Mahasiswa Binaan</p>
        <p class="mt-3 text-3xl font-black text-slate-950">{{ $totalRuns }}</p>
        <p class="mt-1 text-sm text-slate-500">Rotasi yang perlu Anda validasi dari sisi preseptor.</p>
    </article>
    <article class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <p class="text-xs font-black uppercase tracking-widest text-amber-700">Presensi Menunggu</p>
        <p class="mt-3 text-3xl font-black text-amber-700">{{ $pendingAttendance }}</p>
        <p class="mt-1 text-sm text-slate-500">Perlu pemeriksaan preseptor.</p>
    </article>
    <article class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <p class="text-xs font-black uppercase tracking-widest text-cyan-700">Logbook Menunggu</p>
        <p class="mt-3 text-3xl font-black text-cyan-700">{{ $pendingLogbooks }}</p>
        <p class="mt-1 text-sm text-slate-500">Siap diperiksa dari aktivitas harian mahasiswa.</p>
    </article>
</section>
<div class="grid gap-4 lg:grid-cols-2">
@forelse($runs as $run)
    <a href="{{ route('field-supervisor.pkpa-operations.show', $run) }}" class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-sky-100">
        <p class="text-xs font-black uppercase tracking-widest text-cyan-700">{{ $run->practiceDomain?->name }}</p>
        <h2 class="mt-1 text-xl font-black text-slate-950">{{ $run->studentDisplayName() }}</h2>
        <p class="mt-1 text-sm text-slate-500">{{ $run->studentDisplaySecondary() }}</p>
        <p class="mt-3 text-sm font-semibold text-slate-700">{{ $run->practiceSite?->name }}</p>
        <p class="mt-2 text-sm text-slate-500">{{ $run->attendanceRecords->where('submission_status', 'submitted')->count() }} presensi menunggu / {{ $run->logbookEntries->where('status', 'submitted')->count() }} logbook menunggu</p>
    </a>
@empty
    <div class="rounded-2xl bg-white p-6 text-sm text-slate-500 shadow-sm ring-1 ring-sky-100">Belum ada rotasi operasional untuk divalidasi.</div>
@endforelse
</div>
</div>
@endsection
