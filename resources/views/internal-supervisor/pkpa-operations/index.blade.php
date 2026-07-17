@extends('layouts.app')
@section('title', 'Monitoring PKPA')
@section('page_title', 'Monitoring PKPA')
@section('content')
<div class="grid gap-4 lg:grid-cols-2">
@forelse($runs as $run)
    <a href="{{ route('internal-supervisor.pkpa-operations.show', $run) }}" class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-sky-100">
        <p class="text-xs font-black uppercase tracking-widest text-cyan-700">{{ $run->student_core_user_id }}</p>
        <h2 class="mt-1 text-xl font-black text-slate-950">{{ $run->practiceSite?->name }}</h2>
        <p class="mt-2 text-sm text-slate-500">{{ $run->logbookEntries->whereIn('status', ['approved', 'reviewed_by_internal'])->count() }} logbook siap dimonitor.</p>
    </a>
@empty
    <div class="rounded-2xl bg-white p-6 text-sm text-slate-500 shadow-sm ring-1 ring-sky-100">Belum ada rotasi operasional untuk monitoring.</div>
@endforelse
</div>
@endsection
