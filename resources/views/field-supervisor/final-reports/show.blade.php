@extends('layouts.app')

@section('title','Detail Pemeriksaan Laporan - '.config('app.name'))
@section('page_title','Detail Pemeriksaan Laporan')

@section('content')
@php
    $approvedLogbooks = $report->assignment->logbooks()->where('status', 'disetujui')->count();
    $openLogbooks = $report->assignment->logbooks()->whereIn('status', ['menunggu_validasi', 'revisi', 'ditolak'])->count();
@endphp
<div class="space-y-5">
    @if($errors->any())
        <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">{{ $errors->first() }}</div>
    @endif
    <a href="{{ route('field-supervisor.final-reports.index', request()->query()) }}" class="inline-flex rounded-xl border border-sky-200 bg-white px-4 py-2 text-sm font-bold text-cyan-700 shadow-sm">Kembali ke daftar pemeriksaan</a>

    <div class="grid gap-5 xl:grid-cols-[1fr_380px]">
        <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 md:p-6">
            <p class="text-sm text-slate-500">{{ $report->assignment->student->user->name }} | {{ $report->assignment->student->nim ?: '-' }}</p>
            <div class="mt-1 flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                <div>
                    <h2 class="text-2xl font-black text-slate-950">{{ $report->assignment->place->name }}</h2>
                    <p class="mt-1 text-sm text-slate-500">Pembimbing Dalam: {{ $report->assignment->internalSupervisor ? lecturer_display_name($report->assignment->internalSupervisor) : '-' }}</p>
                </div>
                <span class="inline-flex w-fit rounded-full px-3 py-1 text-xs font-bold ring-1 {{ $report->statusBadgeClass() }}">{{ $report->statusLabel() }}</span>
            </div>

            <div class="mt-5 grid gap-3 md:grid-cols-3">
                <div class="rounded-2xl bg-slate-50 p-4">
                    <p class="text-xs font-black uppercase tracking-widest text-slate-500">Logbook PKPA</p>
                    <p class="mt-1 font-black text-slate-950">{{ $approvedLogbooks }} disetujui</p>
                    <p class="mt-1 text-xs text-slate-500">{{ $openLogbooks }} perlu tindak lanjut</p>
                </div>
                <div class="rounded-2xl bg-slate-50 p-4">
                    <p class="text-xs font-black uppercase tracking-widest text-slate-500">Pemeriksaan Pembimbing Dalam</p>
                    <p class="mt-1 font-black text-slate-950">{{ $report->internalReviewStatusLabel() }}</p>
                </div>
                <div class="rounded-2xl bg-slate-50 p-4">
                    <p class="text-xs font-black uppercase tracking-widest text-slate-500">Pemeriksaan Anda</p>
                    <p class="mt-1 font-black text-slate-950">{{ $report->fieldReviewStatusLabel() }}</p>
                </div>
            </div>

            <h3 class="mt-6 font-black text-slate-950">Dokumen Final</h3>
            @if($report->final_document_url)
                <div class="mt-3 rounded-2xl border border-cyan-200 bg-cyan-50/50 p-4">
                    <p class="text-sm font-bold text-slate-950">{{ $report->final_document_label ?: 'Tautan laporan final' }}</p>
                    <a href="{{ $report->final_document_url }}" target="_blank" rel="noopener" class="mt-2 inline-flex rounded-xl bg-cyan-700 px-4 py-2 text-sm font-bold text-white">Buka Tautan Google Docs/Drive</a>
                </div>
            @endif
            <div class="mt-3 space-y-3">
                @forelse($report->files as $file)
                    <div class="flex flex-col gap-2 rounded-xl border border-slate-200 p-4 text-sm md:flex-row md:items-center md:justify-between">
                        <div>
                            <p class="font-bold text-slate-950">Versi {{ $file->version }} - {{ $file->original_filename }}</p>
                            <p class="text-xs text-slate-500">{{ $file->humanFileSize() }} | {{ $file->uploaded_at->format('d M Y H:i') }}</p>
                        </div>
                        <a href="{{ route('field-supervisor.final-reports.files.download',$file) }}" class="rounded-lg border border-cyan-200 px-3 py-1.5 text-xs font-bold text-cyan-700">Unduh</a>
                    </div>
                @empty
                    @unless($report->final_document_url)
                        <p class="rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-500">Belum ada dokumen final.</p>
                    @endunless
                @endforelse
            </div>
        </section>

        <aside class="space-y-5">
            <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <h3 class="font-black text-slate-950">Aksi Pemeriksaan Laporan</h3>
                @if($report->status === 'menunggu_review')
                    <form method="POST" action="{{ route('field-supervisor.final-reports.approve',$report) }}" class="mt-4">
                        @csrf
                        <textarea name="review_note" rows="3" placeholder="Catatan opsional" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea>
                        <button class="mt-3 w-full rounded-lg bg-emerald-600 px-4 py-2 text-sm font-bold text-white">Setujui Laporan</button>
                    </form>
                    <form method="POST" action="{{ route('field-supervisor.final-reports.revision',$report) }}" class="mt-4">
                        @csrf
                        <textarea name="review_note" rows="3" required placeholder="Catatan revisi wajib" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea>
                        <button class="mt-3 w-full rounded-lg bg-blue-600 px-4 py-2 text-sm font-bold text-white">Minta Revisi</button>
                    </form>
                    <form method="POST" action="{{ route('field-supervisor.final-reports.reject',$report) }}" class="mt-4">
                        @csrf
                        <textarea name="review_note" rows="3" required placeholder="Alasan penolakan wajib" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea>
                        <button onclick="return confirm('Tolak laporan ini?')" class="mt-3 w-full rounded-lg bg-red-600 px-4 py-2 text-sm font-bold text-white">Tolak</button>
                    </form>
                @else
                    <p class="mt-4 rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-500">Aksi pemeriksaan tersedia setelah mahasiswa mengirim laporan final.</p>
                @endif
            </section>
            <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <h3 class="font-black text-slate-950">Catatan</h3>
                <p class="mt-2 text-sm text-slate-600">Validasi laporan final dilakukan setelah mahasiswa mengunggah tautan atau berkas final. Validasi logbook PKPA tetap dilakukan dari menu validasi logbook.</p>
            </section>
        </aside>
    </div>
</div>
@endsection
