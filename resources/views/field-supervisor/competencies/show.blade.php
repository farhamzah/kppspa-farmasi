@extends('layouts.app')
@section('title','Checklist Kompetensi Mahasiswa - '.config('app.name'))
@section('page_title','Checklist Kompetensi Mahasiswa')
@section('content')
@php($achievements = $assignment->competencyAchievements->keyBy('kp_competency_id'))
<div class="space-y-5">
    <a href="{{ route('field-supervisor.competencies.index') }}" class="inline-flex rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700">Kembali ke Daftar</a>
    <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <span class="rounded-full {{ $assignment->statusBadgeClass() }} px-3 py-1 text-xs font-semibold">{{ $assignment->statusLabel() }}</span>
        <h2 class="mt-4 text-2xl font-black text-slate-950">{{ $assignment->student->user->name }}</h2>
        <p class="mt-1 text-sm text-slate-500">{{ $assignment->student->nim ?: '-' }} · {{ $assignment->place->name }} · {{ $assignment->period->name }}</p>
    </section>

    <form method="POST" action="{{ route('field-supervisor.competencies.update', $assignment) }}" class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        @csrf
        @method('PUT')
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h3 class="text-lg font-black text-slate-950">Capaian Kompetensi</h3>
                <p class="mt-1 text-sm text-slate-500">Hanya preseptor mahasiswa ini yang dapat mengubah checklist.</p>
            </div>
            <button class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-bold text-white">Simpan Checklist</button>
        </div>
        <div class="mt-5 space-y-3">
            @forelse($competencies as $competency)
                @php($achievement = $achievements->get($competency->id))
                <div class="rounded-xl border border-slate-200 p-4">
                    <label class="flex items-start gap-3">
                        <input type="checkbox" name="competencies[]" value="{{ $competency->id }}" @checked($achievement) class="mt-1 rounded border-slate-300 text-teal-600">
                        <span class="min-w-0 flex-1">
                            <span class="block font-bold text-slate-950">{{ $competency->title }}</span>
                            @if($competency->description)<span class="mt-1 block text-sm text-slate-500">{{ $competency->description }}</span>@endif
                            @if($achievement)<span class="mt-2 block text-xs text-emerald-700">Dicek {{ $achievement->achieved_at?->format('d M Y H:i') }} oleh {{ $achievement->checkedBy?->name ?? 'Preseptor' }}</span>@endif
                        </span>
                    </label>
                    <input name="notes[{{ $competency->id }}]" value="{{ old('notes.'.$competency->id, $achievement?->note) }}" class="mt-3 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Catatan opsional">
                </div>
            @empty
                <p class="rounded-lg bg-slate-50 px-4 py-6 text-center text-sm text-slate-500">Belum ada panduan kompetensi aktif.</p>
            @endforelse
        </div>
    </form>
</div>
@endsection
