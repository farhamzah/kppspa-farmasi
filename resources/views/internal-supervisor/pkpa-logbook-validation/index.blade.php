@extends('layouts.app')
@section('title', 'Validasi Logbook Pembimbing Dalam')
@section('page_title', 'Validasi Logbook')
@section('content')
@php
    $readyCount = $assignments->sum(fn ($assignment) => $assignment->rotationRuns->sum(fn ($run) => $run->logbookEntries->whereIn('status', ['field_approved', 'approved'])->count()));
    $finalCount = $assignments->sum(fn ($assignment) => $assignment->rotationRuns->sum(fn ($run) => $run->logbookEntries->where('status', 'internal_approved')->count()));
@endphp
<div class="space-y-5">
    <section class="grid gap-3 md:grid-cols-3">
        <article class="rounded-2xl border border-cyan-100 bg-white p-5 shadow-sm"><p class="text-xs font-black uppercase tracking-widest text-slate-500">Mahasiswa Bimbingan</p><p class="mt-2 text-3xl font-black text-slate-950">{{ $assignments->count() }}</p><p class="mt-1 text-sm text-slate-500">Seluruh mahasiswa pada penugasan Anda.</p></article>
        <article class="rounded-2xl border border-amber-100 bg-white p-5 shadow-sm"><p class="text-xs font-black uppercase tracking-widest text-amber-700">Menunggu Validasi Akhir</p><p class="mt-2 text-3xl font-black text-amber-700">{{ $readyCount }}</p><p class="mt-1 text-sm text-slate-500">Sudah tervalidasi preseptor.</p></article>
        <article class="rounded-2xl border border-emerald-100 bg-white p-5 shadow-sm"><p class="text-xs font-black uppercase tracking-widest text-emerald-700">Tervalidasi Final</p><p class="mt-2 text-3xl font-black text-emerald-700">{{ $finalCount }}</p><p class="mt-1 text-sm text-slate-500">Sudah disetujui pembimbing dalam.</p></article>
    </section>
    <section class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        <div class="border-b border-slate-100 px-5 py-4"><h2 class="text-lg font-black text-slate-950">Logbook per Mahasiswa</h2><p class="mt-1 text-sm text-slate-500">Pembimbing dalam melakukan validasi akhir setelah preseptor menyetujui logbook.</p></div>
        <div class="divide-y divide-slate-100">
            @forelse($assignments as $assignment)
                @php($run = $assignment->rotationRuns->first())
                @php($ready = $run?->logbookEntries->whereIn('status', ['field_approved', 'approved'])->count() ?? 0)
                @php($final = $run?->logbookEntries->where('status', 'internal_approved')->count() ?? 0)
                @php($total = $run?->logbookEntries->count() ?? 0)
                <article class="flex flex-col gap-4 px-5 py-5 md:flex-row md:items-center md:justify-between"><div><p class="text-lg font-black text-slate-950">{{ $assignment->student_name_snapshot }}</p><p class="mt-1 text-sm text-slate-500">{{ $assignment->student_number_snapshot ?: '-' }} · {{ $assignment->practice_site_name_snapshot }}</p><div class="mt-3 flex flex-wrap gap-2"><span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-700">{{ $total }} logbook</span><span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-bold text-amber-700">{{ $ready }} siap divalidasi</span><span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700">{{ $final }} final</span>@if(! $run)<span class="rounded-full bg-rose-50 px-3 py-1 text-xs font-bold text-rose-700">Runtime belum dibentuk</span>@elseif($total === 0)<span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">Belum ada logbook</span>@endif</div></div>@if($run)<a href="{{ route('internal-supervisor.pkpa-operations.show', $run) }}" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-cyan-700 px-5 py-2 text-sm font-bold text-white">Buka Validasi</a>@else<span class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-200 px-5 py-2 text-sm font-bold text-slate-500">Menunggu Koordinator</span>@endif</article>
            @empty
                <div class="px-5 py-10 text-center text-sm text-slate-500">Belum ada mahasiswa PKPA yang menjadi bimbingan Anda.</div>
            @endforelse
        </div>
    </section>
</div>
@endsection
