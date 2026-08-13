@extends('layouts.app')
@section('title','Detail Laporan Akhir - '.config('app.name'))
@section('page_title','Detail Laporan Akhir')
@section('content')
<div class="grid gap-5 lg:grid-cols-[1fr_360px]">
    <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <p class="text-sm text-slate-500">{{ $report->assignment->student->user->name }} | {{ $report->assignment->student->nim ?: '-' }}</p>
        <h2 class="mt-1 text-2xl font-bold text-slate-950">{{ $report->assignment->place->name }}</h2>
        <span class="mt-3 inline-flex rounded-full px-3 py-1 text-xs font-semibold ring-1 {{ $report->statusBadgeClass() }}">{{ $report->statusLabel() }}</span>
        <div class="mt-6 grid gap-4 md:grid-cols-2 text-sm"><div class="rounded-xl bg-slate-50 p-4"><p class="font-semibold">Pembimbing Dalam</p><p>{{ $report->assignment->internalSupervisor ? lecturer_display_name($report->assignment->internalSupervisor) : '-' }}</p></div><div class="rounded-xl bg-slate-50 p-4"><p class="font-semibold">Versi Saat Ini</p><p>{{ $report->current_version }}</p></div></div>
        <h3 class="mt-6 font-bold text-slate-950">Riwayat Versi</h3>
        <div class="mt-3 space-y-3">@foreach($report->files as $file)<div class="flex items-center justify-between rounded-xl border border-slate-200 p-4 text-sm"><div><p class="font-semibold">Versi {{ $file->version }} - {{ $file->original_filename }}</p><p class="text-xs text-slate-500">{{ $file->humanFileSize() }} | {{ $file->uploaded_at->format('d M Y H:i') }}</p></div><a href="{{ route('management.final-reports.files.download',$file) }}" class="rounded-lg border border-teal-200 px-3 py-1.5 text-xs font-semibold text-teal-700">Unduh</a></div>@endforeach</div>
    </section>
    <aside class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200"><h3 class="font-bold text-slate-950">Log Aktivitas</h3><div class="mt-3 space-y-2">@foreach($report->logs as $log)<p class="text-xs text-slate-500">{{ $log->created_at->format('d M Y H:i') }} - {{ str_replace('_',' ', $log->action) }} oleh {{ $log->user?->name ?? '-' }}</p>@endforeach</div></aside>
</div>
@endsection
