@extends('layouts.app')
@section('title','Detail Logbook - '.config('app.name'))
@section('page_title','Detail Logbook')
@section('content')
<div class="space-y-5">
    <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
            <div>
                <p class="text-sm text-slate-500">{{ $logbook->activity_date->format('d M Y') }} | {{ $logbook->activityDurationLabel() }}</p>
                <h2 class="mt-1 text-2xl font-bold text-slate-950">{{ $logbook->activity_title }}</h2>
                <p class="mt-2 text-sm text-slate-500">{{ $logbook->assignment->place->name }}</p>
            </div>
            <span class="w-max rounded-full px-3 py-1 text-xs font-semibold ring-1 {{ $logbook->statusBadgeClass() }}">{{ $logbook->statusLabel() }}</span>
        </div>
        <div class="mt-6 grid gap-4 md:grid-cols-2">
            <div class="rounded-xl bg-slate-50 p-4"><p class="text-xs font-semibold uppercase text-slate-500">Uraian Kegiatan</p><p class="mt-2 whitespace-pre-line text-sm text-slate-700">{{ $logbook->activity_description }}</p></div>
            <div class="rounded-xl bg-slate-50 p-4"><p class="text-xs font-semibold uppercase text-slate-500">Hasil Pembelajaran</p><p class="mt-2 whitespace-pre-line text-sm text-slate-700">{{ $logbook->learning_outcome ?: '-' }}</p></div>
            <div class="rounded-xl bg-slate-50 p-4"><p class="text-xs font-semibold uppercase text-slate-500">Kendala</p><p class="mt-2 whitespace-pre-line text-sm text-slate-700">{{ $logbook->obstacle ?: '-' }}</p></div>
            <div class="rounded-xl bg-slate-50 p-4"><p class="text-xs font-semibold uppercase text-slate-500">Solusi</p><p class="mt-2 whitespace-pre-line text-sm text-slate-700">{{ $logbook->solution ?: '-' }}</p></div>
        </div>
        @if($logbook->validation_note)
            <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">{{ $logbook->validation_note }}</div>
        @endif
        <div class="mt-6 flex flex-wrap gap-3">
            @if($logbook->canBeEditedByStudent())<a href="{{ route('student.logbooks.edit',$logbook) }}" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Edit</a>@endif
            @if($logbook->canBeSubmitted())<form method="POST" action="{{ route('student.logbooks.submit',$logbook) }}">@csrf<button onclick="return confirm('Submit logbook untuk validasi?')" class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white">Submit</button></form>@endif
            @include('partials.logbook-evidence-actions', ['downloadRoute' => 'student.logbooks.evidence.download'])
        </div>
    </section>
    <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <h3 class="text-lg font-bold text-slate-950">Komentar dan Riwayat</h3>
        <div class="mt-4 space-y-3">
            @forelse($logbook->comments->where('visibility','visible_to_student') as $comment)
                <div class="rounded-xl border border-slate-200 p-4 text-sm"><p class="font-semibold text-slate-900">{{ $comment->user->name }}</p><p class="mt-1 text-slate-600">{{ $comment->comment }}</p></div>
            @empty
                <p class="text-sm text-slate-500">Belum ada komentar yang ditampilkan untuk mahasiswa.</p>
            @endforelse
        </div>
    </section>
</div>
@endsection
