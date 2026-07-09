@extends('layouts.app')
@section('title','Detail Logbook Mahasiswa - '.config('app.name'))
@section('page_title','Detail Logbook Mahasiswa')
@section('content')
<div class="grid gap-5 lg:grid-cols-[1fr_360px]">
    <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <p class="text-sm text-slate-500">{{ $logbook->assignment->student->user->name }} | {{ $logbook->assignment->student->nim ?: '-' }}</p>
        <h2 class="mt-1 text-2xl font-bold text-slate-950">{{ $logbook->activity_title }}</h2>
        <span class="mt-3 inline-flex rounded-full px-3 py-1 text-xs font-semibold ring-1 {{ $logbook->statusBadgeClass() }}">{{ $logbook->statusLabel() }}</span>
        <div class="mt-6 space-y-4 text-sm text-slate-700">
            <p><span class="font-semibold">Tanggal:</span> {{ $logbook->activity_date->format('d M Y') }} | {{ $logbook->activityDurationLabel() }}</p>
            <p><span class="font-semibold">Tempat:</span> {{ $logbook->assignment->place->name }}</p>
            <div><p class="font-semibold">Uraian</p><p class="mt-1 whitespace-pre-line">{{ $logbook->activity_description }}</p></div>
            <div><p class="font-semibold">Hasil Pembelajaran</p><p class="mt-1 whitespace-pre-line">{{ $logbook->learning_outcome ?: '-' }}</p></div>
            @include('partials.logbook-evidence-actions', ['downloadRoute' => 'internal-supervisor.logbooks.evidence.download'])
        </div>
    </section>
    <aside class="space-y-5">
        <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <h3 class="font-bold text-slate-950">Tambah Komentar</h3>
            <form method="POST" action="{{ route('internal-supervisor.logbooks.comments',$logbook) }}" class="mt-4">@csrf<textarea name="comment" rows="4" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea><select name="visibility" class="mt-3 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="visible_to_student">Terlihat Mahasiswa</option><option value="internal">Internal</option></select><button class="mt-3 w-full rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white">Simpan Komentar</button></form>
        </section>
        <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <h3 class="font-bold text-slate-950">Komentar</h3>
            <div class="mt-3 space-y-3">@forelse($logbook->comments as $comment)<div class="rounded-lg border border-slate-200 p-3 text-sm"><p class="font-semibold">{{ $comment->user->name }}</p><p class="text-slate-600">{{ $comment->comment }}</p><p class="mt-1 text-xs text-slate-400">{{ $comment->visibility === 'internal' ? 'Internal' : 'Terlihat mahasiswa' }}</p></div>@empty<p class="text-sm text-slate-500">Belum ada komentar.</p>@endforelse</div>
        </section>
    </aside>
</div>
@endsection
