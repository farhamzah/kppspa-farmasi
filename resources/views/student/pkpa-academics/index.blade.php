@extends('layouts.app')
@section('title', 'Akademik Rotasi')
@section('page_title', 'Akademik Rotasi')
@section('content')
@php
    $totalRuns = $runs->count();
    $verifiedCompetencies = $runs->sum(fn ($run) => $run->competencyRecords->where('status', 'verified')->count());
    $approvedTasks = $runs->sum(fn ($run) => $run->specialTasks->where('status', 'approved')->count());
@endphp
<div class="space-y-5">
<section class="grid gap-4 md:grid-cols-3">
    <article class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <p class="text-xs font-black uppercase tracking-widest text-slate-500">Rotasi Akademik</p>
        <p class="mt-3 text-3xl font-black text-slate-950">{{ $totalRuns }}</p>
        <p class="mt-1 text-sm text-slate-500">Jumlah wahana yang sedang Anda jalankan.</p>
    </article>
    <article class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <p class="text-xs font-black uppercase tracking-widest text-emerald-700">Kompetensi Terverifikasi</p>
        <p class="mt-3 text-3xl font-black text-emerald-700">{{ $verifiedCompetencies }}</p>
        <p class="mt-1 text-sm text-slate-500">Kompetensi yang sudah lolos verifikasi pembimbing lapangan.</p>
    </article>
    <article class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <p class="text-xs font-black uppercase tracking-widest text-cyan-700">Tugas Disetujui</p>
        <p class="mt-3 text-3xl font-black text-cyan-700">{{ $approvedTasks }}</p>
        <p class="mt-1 text-sm text-slate-500">Tugas khusus yang sudah selesai diperiksa.</p>
    </article>
</section>
<div class="grid gap-4 lg:grid-cols-2">
@forelse($runs as $run)
    <a href="{{ route('student.pkpa-academics.show', $run) }}" class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-sky-100">
        <p class="text-xs font-black uppercase tracking-widest text-cyan-700">{{ $run->practiceDomain?->name }}</p>
        <h2 class="mt-1 text-xl font-black">{{ $run->practiceSite?->name }}</h2>
        <p class="mt-2 text-sm text-slate-500">Kompetensi {{ $run->competencyRecords->where('status','verified')->count() }}/{{ $run->competencyRecords->where('is_required_snapshot', true)->count() }} / Tugas {{ $run->specialTasks->where('status','approved')->count() }}/{{ $run->specialTasks->where('is_required_snapshot', true)->count() }}</p>
        <p class="mt-1 text-sm text-slate-500">Kesiapan: {{ match($run->academicReadinessReviews->first()?->status) {
            'ready_for_assessment' => 'Siap untuk penilaian',
            'assessment_blocked' => 'Masih ada yang perlu dilengkapi',
            null => 'Belum dicek',
            default => str($run->academicReadinessReviews->first()?->status)->replace('_', ' ')->headline(),
        } }}</p>
        <p class="mt-1 text-sm text-slate-500">Bimbingan tercatat: {{ $run->guidance_sessions_count }}</p>
    </a>
@empty
    <div class="rounded-2xl bg-white p-6 text-sm text-slate-500 shadow-sm ring-1 ring-sky-100">Belum ada akademik rotasi.</div>
@endforelse
</div>
</div>
@endsection
