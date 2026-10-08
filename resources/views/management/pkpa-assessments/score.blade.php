@extends('layouts.app')
@section('title', 'Catat Penilaian PKPA')
@section('page_title', 'Catat Penilaian PKPA')
@section('content')
@php
    $run = $score->assessment->rotationRun;
    $locked = in_array($score->status, ['submitted', 'approved', 'locked'], true);
    $ready = !$score->assessment->scheme?->require_academic_readiness || $run->academicReadinessReviews->sortByDesc('reviewed_at')->first()?->status === 'ready_for_assessment';
    $label = $score->assessor->assessor_type === 'field_supervisor' ? 'Preseptor' : 'Pembimbing Dalam';
@endphp
<div class="mx-auto max-w-6xl space-y-4">
    <a href="{{ route('management.pkpa-assessments.index') }}" class="inline-flex items-center gap-2 text-sm font-bold text-cyan-800">Kembali ke Penilaian</a>
    <header class="border-b border-slate-200 pb-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-xl font-black text-slate-950">Penilaian {{ $label }}</h1>
            <span class="rounded-full px-3 py-1 text-sm font-bold {{ $locked ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">{{ $locked ? 'Terkirim dan terkunci' : 'Draf' }}</span>
        </div>
        <h2 class="mt-3 text-lg font-bold text-slate-900">{{ $run->studentDisplayName() }}</h2>
        <p class="mt-1 break-words text-sm text-slate-600">{{ $run->studentDisplaySecondary() }} · {{ $run->practiceDomain?->name }} · {{ $run->practiceSite?->name }}</p>
        <dl class="mt-3 grid gap-2 text-sm sm:grid-cols-2">
            <div><dt class="text-slate-500">Penilai</dt><dd class="font-bold">{{ $score->assessor->name_snapshot }}</dd></div>
            <div><dt class="text-slate-500">Penginput</dt><dd class="font-bold">{{ data_get($score->source_summary, 'recorded_by_core_user_id') ? 'Core '.data_get($score->source_summary, 'recorded_by_core_user_id') : auth()->user()->name.' (Koordinator/Admin)' }}</dd></div>
        </dl>
        <p class="mt-3 text-sm text-slate-600">Nilai preseptor dan pembimbing disimpan terpisah. Penggabungan menunggu ketentuan resmi.</p>
        @if(data_get($score->source_summary, 'recorded_at'))
            <p class="mt-2 text-xs text-slate-500">Pencatatan terakhir: {{ data_get($score->source_summary, 'recorded_at') }} · Core penginput {{ data_get($score->source_summary, 'recorded_by_core_user_id') }}</p>
        @endif
    </header>
    @if($usesGuide)
        @include('shared.pkpa-apotek-assessment-form', ['assignment' => $score->assessor, 'routePrefix' => 'management', 'attendanceSummary' => $attendanceSummary])
    @else
        <form method="POST" action="{{ route('management.pkpa-assessments.scores.save', $score) }}" class="grid gap-4 rounded-lg border border-slate-200 bg-white p-5">
            @csrf
            @if(!$ready)<p class="text-sm text-amber-800">Menunggu status siap dinilai.</p>@endif
            <label class="grid gap-2 text-sm font-bold">Nilai {{ $label }} (maks. {{ $score->component->maximum_raw_score }})<input name="raw_score" type="number" min="0" max="{{ $score->component->maximum_raw_score }}" step="0.01" required value="{{ old('raw_score', $score->raw_score) }}" class="w-full rounded-lg border-slate-200" @disabled($locked || !$ready)></label>
            <label class="grid gap-2 text-sm font-bold">Catatan penilai<textarea name="overall_comments" rows="3" class="w-full rounded-lg border-slate-200" @disabled($locked || !$ready)>{{ old('overall_comments', $score->comments) }}</textarea></label>
            <label class="grid gap-2 text-sm font-bold">Dasar pencatatan oleh koordinator<textarea name="recording_basis" rows="2" maxlength="1500" required class="w-full rounded-lg border-slate-200" @disabled($locked || !$ready)>{{ old('recording_basis', data_get($score->source_summary, 'basis')) }}</textarea></label>
            @unless($locked)
                <div class="flex flex-wrap gap-2"><button class="rounded-lg border border-slate-300 px-4 py-3 text-sm font-bold" @disabled(!$ready)>Simpan Draf</button><button formaction="{{ route('management.pkpa-assessments.scores.submit', $score) }}" class="rounded-lg bg-cyan-700 px-4 py-3 text-sm font-bold text-white" @disabled(!$ready)>Kirim &amp; Kunci</button></div>
            @endunless
        </form>
    @endif
</div>
@endsection
