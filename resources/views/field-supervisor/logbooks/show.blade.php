@extends('layouts.app')
@section('title','Review Logbook - '.config('app.name'))
@section('page_title','Review Logbook')
@section('content')
<div class="grid gap-5 lg:grid-cols-[1fr_360px]">
    <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <a href="{{ route('field-supervisor.pkpa-journals.index', ['assignment' => $logbook->kp_assignment_id]) }}" class="mb-5 inline-flex rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Kembali ke Daftar Logbook</a>
        <p class="text-sm text-slate-500">{{ $logbook->assignment->student->user->name }} | {{ $logbook->assignment->student->nim ?: '-' }}</p>
        <h2 class="mt-1 text-2xl font-bold text-slate-950">{{ $logbook->activity_title }}</h2>
        <span class="mt-3 inline-flex rounded-full px-3 py-1 text-xs font-semibold ring-1 {{ $logbook->statusBadgeClass() }}">{{ $logbook->statusLabel() }}</span>
        <div class="mt-6 space-y-4 text-sm text-slate-700">
            <p><span class="font-semibold">Tanggal:</span> {{ $logbook->activity_date->format('d M Y') }} | {{ $logbook->activityDurationLabel() }}</p>
            <div><p class="font-semibold">Uraian Kegiatan</p><p class="mt-1 whitespace-pre-line">{{ $logbook->activity_description }}</p></div>
            <div><p class="font-semibold">Hasil Pembelajaran</p><p class="mt-1 whitespace-pre-line">{{ $logbook->learning_outcome ?: '-' }}</p></div>
            <div><p class="font-semibold">Kendala/Solusi</p><p class="mt-1 whitespace-pre-line">{{ $logbook->obstacle ?: '-' }} / {{ $logbook->solution ?: '-' }}</p></div>
            @include('partials.logbook-evidence-actions', ['downloadRoute' => 'field-supervisor.pkpa-journals.evidence.download'])
        </div>
    </section>
    <aside class="space-y-5">
        <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <h3 class="font-bold text-slate-950">Aksi Validasi</h3>
            <form method="POST" action="{{ route('field-supervisor.pkpa-journals.approve',$logbook) }}" class="mt-4">@csrf<textarea name="validation_note" rows="3" placeholder="Catatan opsional" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea><button class="mt-3 w-full rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white">Setujui</button></form>
            <form method="POST" action="{{ route('field-supervisor.pkpa-journals.revision',$logbook) }}" class="mt-4">@csrf<textarea name="validation_note" rows="3" required placeholder="Catatan revisi wajib" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea><button class="mt-3 w-full rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white">Minta Revisi</button></form>
            <form method="POST" action="{{ route('field-supervisor.pkpa-journals.reject',$logbook) }}" class="mt-4">@csrf<textarea name="validation_note" rows="3" required placeholder="Alasan penolakan wajib" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea><button onclick="return confirm('Tolak logbook ini?')" class="mt-3 w-full rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white">Tolak</button></form>
        </section>
        <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <h3 class="font-bold text-slate-950">Riwayat</h3>
            <div class="mt-3 space-y-2">@foreach($logbook->logs as $log)<p class="text-xs text-slate-500">{{ $log->created_at->format('d M Y H:i') }} - {{ str_replace('_',' ', $log->action) }}</p>@endforeach</div>
        </section>
    </aside>
</div>
@endsection
