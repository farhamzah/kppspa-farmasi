@extends('layouts.app')
@section('title', 'Rotasi PKPA')
@section('page_title', 'Rotasi PKPA')
@section('content')
<div class="grid gap-4 lg:grid-cols-2">
    @forelse($runs as $run)
        <a href="{{ route('student.pkpa-operations.show', $run) }}" class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-sky-100 transition hover:ring-cyan-200">
            <p class="text-xs font-black uppercase tracking-widest text-cyan-700">{{ $run->practiceDomain?->name }}</p>
            <h2 class="mt-1 text-xl font-black text-slate-950">{{ $run->practiceSite?->name }}</h2>
            <p class="mt-2 text-sm text-slate-500">{{ $run->scheduled_start_date?->format('d M Y') }} - {{ $run->scheduled_end_date?->format('d M Y') }}</p>
            <div class="mt-4 h-2 rounded-full bg-slate-100"><div class="h-2 rounded-full bg-cyan-600" style="width: {{ optional($run->progressSnapshots->first())->progress_percentage ?? 0 }}%"></div></div>
            <p class="mt-2 text-xs font-bold text-slate-500">Progress {{ optional($run->progressSnapshots->first())->progress_percentage ?? 0 }}%</p>
        </a>
    @empty
        <div class="rounded-2xl bg-white p-6 text-sm text-slate-500 shadow-sm ring-1 ring-sky-100">Belum ada rotasi operasional aktif dari publikasi resmi.</div>
    @endforelse
</div>
@endsection
