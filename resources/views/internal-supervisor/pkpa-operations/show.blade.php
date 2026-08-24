@extends('layouts.app')
@section('title', 'Monitoring Logbook PKPA')
@section('page_title', 'Monitoring Logbook PKPA')
@section('content')
@php
    $progress = optional($run->progressSnapshots->first())->progress_percentage ?? 0;
    $monitorableEntries = $run->logbookEntries->whereIn('status', ['approved', 'reviewed_by_internal']);
@endphp
<div class="space-y-5">
    @if(session('status'))<div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-bold text-rose-700">{{ $errors->first() }}</div>@endif
    <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-sky-100">
        <p class="text-xs font-black uppercase tracking-widest text-cyan-700">{{ $run->practiceDomain?->name }}</p>
        <h2 class="mt-1 text-2xl font-black text-slate-950">{{ $run->studentDisplayName() }}</h2>
        <p class="mt-1 text-sm text-slate-500">{{ $run->studentDisplaySecondary() }} / {{ $run->practiceSite?->name }}</p>
        <div class="mt-4 grid gap-3 md:grid-cols-3">
            <div class="rounded-xl bg-slate-50 px-4 py-3"><p class="text-xs font-black uppercase tracking-widest text-slate-500">Kemajuan</p><p class="mt-1 font-black text-slate-950">{{ $progress }}%</p></div>
            <div class="rounded-xl bg-slate-50 px-4 py-3"><p class="text-xs font-black uppercase tracking-widest text-slate-500">Siap Dimonitor</p><p class="mt-1 font-black text-slate-950">{{ $monitorableEntries->count() }}</p></div>
            <div class="rounded-xl bg-slate-50 px-4 py-3"><p class="text-xs font-black uppercase tracking-widest text-slate-500">Total Presensi</p><p class="mt-1 font-black text-slate-950">{{ $run->attendanceRecords->count() }}</p></div>
        </div>
    </section>
    <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-sky-100">
        <h3 class="text-lg font-black">Logbook Tervalidasi Lapangan</h3>
        <p class="mt-1 text-sm text-slate-500">Tambahkan catatan monitoring akademik setelah logbook lolos pemeriksaan pembimbing lapangan.</p>
        <div class="mt-4 space-y-3">
            @forelse($monitorableEntries as $entry)
                <form method="POST" action="{{ route('internal-supervisor.pkpa-logbooks.monitoring', $entry) }}" class="rounded-xl bg-slate-50 p-3">
                    @csrf
                    <p class="font-bold text-slate-950">{{ $entry->title }} / {{ $entry->entry_date?->format('d M Y') }}</p>
                    <p class="mt-1 text-sm text-slate-500">{{ $entry->learning_outcomes }}</p>
                    @if($entry->reviews->isNotEmpty())
                        <p class="mt-2 text-xs text-slate-500">Sudah ada {{ $entry->reviews->count() }} catatan review.</p>
                    @endif
                    <textarea name="comments" class="mt-2 w-full rounded-xl border-slate-200 text-sm" placeholder="Catatan monitoring pembimbing dalam" required></textarea>
                    <button class="mt-2 rounded-lg bg-cyan-700 px-3 py-1.5 text-xs font-black text-white">Simpan Catatan</button>
                </form>
            @empty
                <div class="rounded-xl border border-dashed border-slate-200 px-4 py-8 text-center text-sm text-slate-500">Belum ada logbook yang siap dimonitor dari sisi pembimbing dalam.</div>
            @endforelse
        </div>
    </section>
</div>
@endsection
