@extends('layouts.app')
@section('title', 'Monitoring Logbook PKPA')
@section('page_title', 'Monitoring Logbook PKPA')
@section('content')
@php
    $progress = optional($run->progressSnapshots->first())->progress_percentage ?? 0;
    $monitorableEntries = $run->logbookEntries->whereIn('status', ['approved', 'reviewed_by_internal']);
@endphp
<div class="space-y-5">
    @if(session('status'))<div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-bold text-rose-700">{{ $errors->first() }}</div>@endif
    <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-sky-100">
        <p class="text-xs font-black uppercase tracking-widest text-cyan-700">{{ $run->practiceDomain?->name }}</p>
        <h2 class="mt-1 text-2xl font-black text-slate-950">{{ $run->studentDisplayName() }}</h2>
        <p class="mt-1 text-sm text-slate-500">{{ $run->studentDisplaySecondary() }} / {{ $run->practiceSite?->name }}</p>
        <div class="mt-4 grid gap-3 md:grid-cols-3">
            <div class="rounded-xl bg-slate-50 px-4 py-3"><p class="text-xs font-black uppercase tracking-widest text-slate-500">Kemajuan</p><p class="mt-1 font-black text-slate-950">{{ $progress }}%</p></div>
            <div class="rounded-xl bg-slate-50 px-4 py-3"><p class="text-xs font-black uppercase tracking-widest text-slate-500">Siap Dimonitor</p><p class="mt-1 font-black text-slate-950">{{ $monitorableEntries->count() }}</p></div>
            <div class="rounded-xl bg-slate-50 px-4 py-3"><p class="text-xs font-black uppercase tracking-widest text-slate-500">Total Presensi</p><p class="mt-1 font-black text-slate-950">{{ $run->attendanceRecords->count() }}</p></div>
        </div>
    </section>
    <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-sky-100">
        <h3 class="text-lg font-black">Logbook Tervalidasi Preseptor</h3>
        <p class="mt-1 text-sm text-slate-500">Tambahkan catatan monitoring akademik setelah logbook lolos pemeriksaan preseptor.</p>
        <div class="mt-4 space-y-4">
            @forelse($monitorableEntries as $entry)
                <form method="POST" action="{{ route('internal-supervisor.pkpa-logbooks.monitoring', $entry) }}" class="rounded-2xl bg-slate-50 p-4 ring-1 ring-slate-200">
                    @csrf
                    <p class="text-lg font-black text-slate-950">{{ $entry->title }} / {{ $entry->entry_date?->format('d M Y') }}</p>
                    <p class="mt-3 text-sm leading-6 text-slate-700">{{ $entry->activity_summary }}</p>
                    <div class="mt-3 grid gap-3 lg:grid-cols-2">
                        <div class="rounded-xl bg-white px-4 py-3 ring-1 ring-slate-200">
                            <p class="text-xs font-black uppercase tracking-widest text-slate-500">Kompetensi</p>
                            <p class="mt-2 text-sm leading-6 text-slate-700">{{ $entry->learning_outcomes }}</p>
                        </div>
                        <div class="rounded-xl bg-white px-4 py-3 ring-1 ring-slate-200">
                            <p class="text-xs font-black uppercase tracking-widest text-slate-500">Refleksi</p>
                            <p class="mt-2 text-sm leading-6 text-slate-700">{{ $entry->reflection }}</p>
                        </div>
                    </div>
                    <div class="mt-3 space-y-2">
                        @forelse($entry->attachments as $attachment)
                            <div class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 lg:flex-row lg:items-center lg:justify-between">
                                <div>
                                    <p class="text-sm font-bold text-slate-900">{{ $attachment->displayLabel() }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ $attachment->isExternalLink() ? 'Tautan eksternal / Google Drive' : 'File unggahan / '.$attachment->humanFileSize() }}</p>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    @if($attachment->isExternalLink())
                                        <a href="{{ $attachment->previewUrl() }}" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-10 items-center justify-center rounded-xl border border-cyan-200 bg-white px-4 py-2 text-sm font-bold text-cyan-700">Preview Link</a>
                                        <a href="{{ $attachment->externalDownloadUrl() }}" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-10 items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-700">Buka Drive</a>
                                    @else
                                        <a href="{{ route('internal-supervisor.pkpa-logbooks.attachments.download', $attachment) }}" class="inline-flex min-h-10 items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-700">Unduh File</a>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="rounded-xl border border-dashed border-slate-200 px-4 py-4 text-sm text-slate-500">Belum ada bukti yang disertakan.</div>
                        @endforelse
                    </div>
                    @if($entry->reviews->isNotEmpty())
                        <p class="mt-2 text-xs text-slate-500">Sudah ada {{ $entry->reviews->count() }} catatan review.</p>
                    @endif
                    <textarea name="comments" class="mt-3 min-h-28 w-full rounded-2xl border-slate-200 px-4 py-3 text-sm" placeholder="Catatan monitoring pembimbing dalam" required></textarea>
                    <button class="mt-3 rounded-xl bg-cyan-700 px-4 py-2 text-sm font-black text-white">Simpan Catatan</button>
                </form>
            @empty
                <div class="rounded-xl border border-dashed border-slate-200 px-4 py-8 text-center text-sm text-slate-500">Belum ada logbook yang siap dimonitor dari sisi pembimbing dalam.</div>
            @endforelse
        </div>
    </section>
</div>
@endsection
