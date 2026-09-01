@extends('layouts.app')
@section('title', 'Antrean Validasi Logbook')
@section('page_title', 'Antrean Validasi Logbook')
@section('content')
@php
    $studentCount = $assignments->count();
    $finalCount = $assignments->sum(fn ($assignment) => $assignment->rotationRuns->sum(fn ($run) => $run->logbookEntries->where('status', 'internal_approved')->count()));
@endphp
<div class="space-y-5">
    <section class="grid gap-3 md:grid-cols-3">
        <article class="rounded-2xl border border-cyan-100 bg-white p-5 shadow-sm"><p class="text-xs font-black uppercase tracking-widest text-slate-500">Mahasiswa Bimbingan</p><p class="mt-2 text-3xl font-black text-slate-950">{{ $studentCount }}</p><p class="mt-1 text-sm text-slate-500">Lihat seluruh aktivitas dari menu Pemantauan PKPA.</p></article>
        <article class="rounded-2xl border border-amber-100 bg-white p-5 shadow-sm"><p class="text-xs font-black uppercase tracking-widest text-amber-700">Siap Validasi Akhir</p><p class="mt-2 text-3xl font-black text-amber-700">{{ $readyLogbooks->total() }}</p><p class="mt-1 text-sm text-slate-500">Sudah disetujui Preseptor dan menunggu keputusan Anda.</p></article>
        <article class="rounded-2xl border border-emerald-100 bg-white p-5 shadow-sm"><p class="text-xs font-black uppercase tracking-widest text-emerald-700">Tervalidasi Final</p><p class="mt-2 text-3xl font-black text-emerald-700">{{ $finalCount }}</p><p class="mt-1 text-sm text-slate-500">Logbook yang telah disetujui Pembimbing Dalam.</p></article>
    </section>

    <section class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        <div class="flex flex-col gap-4 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div><h2 class="text-lg font-black text-slate-950">Logbook Siap Divalidasi</h2><p class="mt-1 text-sm text-slate-500">Hanya logbook yang telah disetujui Preseptor tampil di antrean ini.</p></div>
            <a href="{{ route('internal-supervisor.pkpa-operations.index') }}" class="inline-flex min-h-10 items-center justify-center rounded-xl border border-cyan-200 px-4 py-2 text-sm font-bold text-cyan-800">Buka Pemantauan PKPA</a>
        </div>
        <div class="divide-y divide-slate-100">
            @forelse($readyLogbooks as $entry)
                @php($run = $entry->rotationRun)
                <article class="flex flex-col gap-4 px-5 py-5 md:flex-row md:items-center md:justify-between">
                    <div>
                        <div class="flex flex-wrap items-center gap-2"><p class="text-lg font-black text-slate-950">{{ $entry->title }}</p><span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-bold text-amber-700">Siap validasi akhir</span></div>
                        <p class="mt-1 text-sm font-semibold text-slate-700">{{ $run?->studentDisplayName() }}</p>
                        <p class="mt-1 text-sm text-slate-500">{{ optional($entry->entry_date)->translatedFormat('d M Y') }} · {{ $run?->practiceDomain?->name }} · {{ $run?->practiceSite?->name }}</p>
                    </div>
                    <a href="{{ route('internal-supervisor.pkpa-operations.show', ['run' => $run, 'logbook' => $entry->id]) }}" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-cyan-700 px-5 py-2 text-sm font-bold text-white">Lihat & Validasi</a>
                </article>
            @empty
                <div class="px-5 py-12 text-center"><p class="text-base font-bold text-slate-700">Belum ada logbook siap validasi akhir.</p><p class="mt-1 text-sm text-slate-500">Kiriman yang masih menunggu Preseptor tetap dapat Anda baca dari Pemantauan PKPA.</p><a href="{{ route('internal-supervisor.pkpa-operations.index') }}" class="mt-4 inline-flex min-h-10 items-center justify-center rounded-xl border border-cyan-200 px-4 py-2 text-sm font-bold text-cyan-800">Buka Pemantauan PKPA</a></div>
            @endforelse
        </div>
        @if($readyLogbooks->hasPages())<div class="border-t border-slate-100 px-5 py-4">{{ $readyLogbooks->links() }}</div>@endif
    </section>
</div>
@endsection
