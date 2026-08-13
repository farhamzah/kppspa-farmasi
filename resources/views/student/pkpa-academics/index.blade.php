@extends('layouts.app')
@section('title', 'Akademik Rotasi')
@section('page_title', 'Akademik Rotasi')
@section('content')
<div class="grid gap-4 lg:grid-cols-2">
@forelse($runs as $run)
    <a href="{{ route('student.pkpa-academics.show', $run) }}" class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-sky-100">
        <p class="text-xs font-black uppercase tracking-widest text-cyan-700">{{ $run->practiceDomain?->name }}</p>
        <h2 class="mt-1 text-xl font-black">{{ $run->practiceSite?->name }}</h2>
        <p class="mt-2 text-sm text-slate-500">Kompetensi {{ $run->competencyRecords->where('status','verified')->count() }}/{{ $run->competencyRecords->where('is_required_snapshot', true)->count() }} / Tugas {{ $run->specialTasks->where('status','approved')->count() }}/{{ $run->specialTasks->where('is_required_snapshot', true)->count() }}</p>
        <p class="mt-1 text-sm text-slate-500">Kesiapan: {{ $run->academicReadinessReviews->first()?->status ?? 'Belum dicek' }}</p>
    </a>
@empty
    <div class="rounded-2xl bg-white p-6 text-sm text-slate-500 shadow-sm ring-1 ring-sky-100">Belum ada akademik rotasi.</div>
@endforelse
</div>
@endsection
