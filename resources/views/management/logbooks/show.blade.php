@extends('layouts.app')
@section('title','Detail Monitoring Logbook - '.config('app.name'))
@section('page_title','Detail Monitoring Logbook')
@section('content')
<div class="grid gap-5 lg:grid-cols-[1fr_360px]">
    <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <p class="text-sm text-slate-500">{{ $logbook->assignment->student->user->name }} | {{ $logbook->assignment->student->nim ?: '-' }}</p>
        <h2 class="mt-1 text-2xl font-bold text-slate-950">{{ $logbook->activity_title }}</h2>
        <span class="mt-3 inline-flex rounded-full px-3 py-1 text-xs font-semibold ring-1 {{ $logbook->statusBadgeClass() }}">{{ $logbook->statusLabel() }}</span>
        <div class="mt-6 grid gap-4 md:grid-cols-2 text-sm text-slate-700">
            <div class="rounded-xl bg-slate-50 p-4"><p class="font-semibold">Tanggal</p><p>{{ $logbook->activity_date->format('d M Y') }} | {{ $logbook->activityDurationLabel() }}</p></div>
            <div class="rounded-xl bg-slate-50 p-4"><p class="font-semibold">Tempat</p><p>{{ $logbook->assignment->place->name }}</p></div>
            <div class="rounded-xl bg-slate-50 p-4 md:col-span-2"><p class="font-semibold">Uraian</p><p class="mt-1 whitespace-pre-line">{{ $logbook->activity_description }}</p></div>
            <div class="rounded-xl bg-slate-50 p-4"><p class="font-semibold">Hasil Pembelajaran</p><p>{{ $logbook->learning_outcome ?: '-' }}</p></div>
            <div class="rounded-xl bg-slate-50 p-4"><p class="font-semibold">Kendala/Solusi</p><p>{{ $logbook->obstacle ?: '-' }} / {{ $logbook->solution ?: '-' }}</p></div>
        </div>
        <div class="mt-5">
            @include('partials.logbook-evidence-actions', ['downloadRoute' => 'management.logbooks.evidence.download'])
        </div>
    </section>
    <aside class="space-y-5">
        <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <h3 class="font-bold text-slate-950">Komentar Monitoring</h3>
            <form method="POST" action="{{ route('management.logbooks.comments',$logbook) }}" class="mt-4">@csrf<textarea name="comment" rows="4" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea><select name="visibility" class="mt-3 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="visible_to_student">Terlihat Mahasiswa</option><option value="internal">Internal</option></select><button class="mt-3 w-full rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white">Simpan Komentar</button></form>
        </section>
        <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <h3 class="font-bold text-slate-950">Log Aktivitas</h3>
            <div class="mt-3 space-y-2">@foreach($logbook->logs as $log)<p class="text-xs text-slate-500">{{ $log->created_at->format('d M Y H:i') }} - {{ str_replace('_',' ', $log->action) }} oleh {{ $log->user?->name ?? '-' }}</p>@endforeach</div>
        </section>
    </aside>
</div>
@endsection
