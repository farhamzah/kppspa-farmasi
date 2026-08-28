@extends('layouts.app')
@section('title','Capaian Kompetensi Mahasiswa - '.config('app.name'))
@section('page_title','Capaian Kompetensi Mahasiswa')
@section('content')
@php($achievements = $assignment->competencyAchievements->keyBy('kp_competency_id'))
<div class="space-y-5">
    <a href="{{ route('internal-supervisor.competencies.index') }}" class="inline-flex rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700">Kembali ke Daftar</a>
    <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <span class="rounded-full bg-sky-50 px-3 py-1 text-xs font-semibold text-sky-700">Read-only</span>
        <h2 class="mt-4 text-2xl font-black text-slate-950">{{ $assignment->student->user->name }}</h2>
        <p class="mt-1 text-sm text-slate-500">{{ $assignment->student->nim ?: '-' }} · {{ $assignment->place->name }} · {{ $assignment->period->name }}</p>
    </section>

    <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <h3 class="text-lg font-black text-slate-950">Capaian Kompetensi</h3>
        <p class="mt-1 text-sm text-slate-500">Checklist diisi oleh preseptor.</p>
        <div class="mt-5 space-y-3">
            @forelse($competencies as $competency)
                @php($achievement = $achievements->get($competency->id))
                <div class="rounded-xl border {{ $achievement ? 'border-emerald-200 bg-emerald-50/40' : 'border-slate-200' }} p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h4 class="font-bold text-slate-950">{{ $competency->title }}</h4>
                            @if($competency->description)<p class="mt-1 text-sm text-slate-500">{{ $competency->description }}</p>@endif
                            @if($achievement?->note)<p class="mt-2 text-sm text-slate-700">Catatan: {{ $achievement->note }}</p>@endif
                        </div>
                        <span class="rounded-full {{ $achievement ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' }} px-3 py-1 text-xs font-bold">{{ $achievement ? 'Tercapai' : 'Belum' }}</span>
                    </div>
                    @if($achievement)<p class="mt-2 text-xs text-emerald-700">Dicek {{ $achievement->achieved_at?->format('d M Y H:i') }} oleh {{ $achievement->checkedBy?->name ?? 'Preseptor' }}</p>@endif
                </div>
            @empty
                <p class="rounded-lg bg-slate-50 px-4 py-6 text-center text-sm text-slate-500">Belum ada panduan kompetensi aktif.</p>
            @endforelse
        </div>
    </section>
</div>
@endsection
