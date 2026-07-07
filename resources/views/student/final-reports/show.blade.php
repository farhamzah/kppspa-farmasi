@extends('layouts.app')

@section('title','Laporan Akhir - '.config('app.name'))
@section('page_title','Laporan Akhir')

@section('content')
<div class="space-y-5">
    @if($errors->any())
        <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">{{ $errors->first() }}</div>
    @endif

    @if(! $assignment)
        <x-ui.empty-state title="Anda belum memiliki penempatan KP aktif." description="Laporan akhir dapat diupload setelah penempatan KP aktif atau berjalan." />
    @else
        @php
            $guidanceLogs = $assignment->reportGuidanceLogs->sortByDesc('guidance_date');
            $approvedGuidance = $assignment->reportGuidanceLogs->where('status', 'disetujui')->count();
            $eligibilityItems = $examEligibility['items'] ?? [];
        @endphp

        <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 md:p-6">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                <div class="min-w-0">
                    <p class="text-xs font-black uppercase tracking-widest text-cyan-700">Dokumen kerja & laporan final</p>
                    <h2 class="mt-1 text-2xl font-black text-slate-950">{{ $assignment->place->name }}</h2>
                    <div class="mt-3 grid gap-2 text-sm text-slate-600 md:grid-cols-2">
                        <p>Pembimbing Dalam: <span class="font-bold text-slate-900">{{ $assignment->internalSupervisor ? lecturer_display_name($assignment->internalSupervisor) : '-' }}</span></p>
                        <p>Pembimbing Lapangan: <span class="font-bold text-slate-900">{{ $assignment->fieldSupervisor ? field_supervisor_display_name($assignment->fieldSupervisor) : '-' }}</span></p>
                    </div>
                </div>
                @if($report)
                    <span class="inline-flex w-fit rounded-full px-3 py-1 text-xs font-bold ring-1 {{ $report->statusBadgeClass() }}">{{ $report->statusLabel() }}</span>
                @endif
            </div>

            <div class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                @foreach($eligibilityItems as $item)
                    <div class="rounded-2xl border {{ $item['ready'] ? 'border-emerald-200 bg-emerald-50/60' : 'border-amber-200 bg-amber-50/60' }} p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-black text-slate-950">{{ $item['label'] }}</p>
                                <p class="mt-1 text-xs text-slate-600">{{ $item['description'] }}</p>
                            </div>
                            <span class="rounded-full px-2.5 py-1 text-[11px] font-black {{ $item['ready'] ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">{{ $item['ready'] ? 'OK' : 'Belum' }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="grid gap-5 xl:grid-cols-[1fr_380px]">
            <div class="space-y-5">
                <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 md:p-6">
                    <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                        <div>
                            <h3 class="text-lg font-black text-slate-950">Link Laporan Final</h3>
                            <p class="mt-1 text-sm text-slate-500">Gunakan link Google Docs/Drive final yang sudah disepakati pembimbing. Link ini terlihat oleh pembimbing dalam dan lapangan.</p>
                        </div>
                        @if($report?->final_document_url)
                            <a href="{{ $report->final_document_url }}" target="_blank" rel="noopener" class="rounded-xl border border-cyan-200 px-4 py-2 text-sm font-bold text-cyan-700">Buka Link</a>
                        @endif
                    </div>

                    @if(! $report || $report->canBeEditedByStudent())
                        <form method="POST" action="{{ route('student.final-reports.final-link') }}" class="mt-4 grid gap-3 md:grid-cols-[minmax(0,1fr)_260px_auto]">
                            @csrf
                            <input name="final_document_url" value="{{ old('final_document_url', $report?->final_document_url) }}" placeholder="https://docs.google.com/..." class="rounded-xl border border-slate-300 px-3 py-2 text-sm shadow-sm">
                            <input name="final_document_label" value="{{ old('final_document_label', $report?->final_document_label) }}" placeholder="Judul/link opsional" class="rounded-xl border border-slate-300 px-3 py-2 text-sm shadow-sm">
                            <button class="rounded-xl bg-cyan-700 px-4 py-2 text-sm font-bold text-white shadow-lg shadow-cyan-700/15">Simpan Link</button>
                        </form>
                    @else
                        <p class="mt-4 rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-500">Link final terkunci saat laporan sedang direview atau sudah disetujui.</p>
                    @endif
                </section>

                <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 md:p-6">
                    <h3 class="text-lg font-black text-slate-950">Upload File Laporan</h3>
                    <p class="mt-1 text-sm text-slate-500">Opsional jika laporan final memakai Google Docs/Drive. Format PDF, DOC, atau DOCX. Maksimal 10MB.</p>
                    @if(! $report || $report->canBeEditedByStudent())
                        <form method="POST" action="{{ route('student.final-reports.upload') }}" enctype="multipart/form-data" class="mt-4 grid gap-3 md:grid-cols-[1fr_1fr_auto]">
                            @csrf
                            <input type="file" name="report_file" class="rounded-xl border border-slate-300 px-3 py-2 text-sm shadow-sm">
                            <input name="note" placeholder="Catatan upload opsional" class="rounded-xl border border-slate-300 px-3 py-2 text-sm shadow-sm">
                            <button class="rounded-xl bg-teal-600 px-4 py-2 text-sm font-bold text-white shadow-lg shadow-teal-700/15">Upload</button>
                        </form>
                    @else
                        <p class="mt-4 rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-500">Upload tidak tersedia pada status saat ini.</p>
                    @endif

                    <div class="mt-4 space-y-3">
                        @forelse($report?->files ?? [] as $file)
                            <div class="flex flex-col gap-2 rounded-xl border border-slate-200 p-4 md:flex-row md:items-center md:justify-between">
                                <div>
                                    <p class="font-bold text-slate-950">Versi {{ $file->version }} - {{ $file->original_filename }}</p>
                                    <p class="text-xs text-slate-500">{{ $file->humanFileSize() }} | {{ $file->uploaded_at->format('d M Y H:i') }}</p>
                                </div>
                                <a href="{{ route('student.final-reports.files.download',$file) }}" class="rounded-lg border border-cyan-200 px-3 py-1.5 text-xs font-bold text-cyan-700">Download</a>
                            </div>
                        @empty
                            <p class="rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-500">Belum ada file laporan.</p>
                        @endforelse
                    </div>
                </section>
            </div>

            <aside class="space-y-5">
                <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                    <h3 class="font-black text-slate-950">Status Persetujuan</h3>
                    <div class="mt-4 space-y-3 text-sm">
                        <div class="rounded-xl bg-slate-50 p-4">
                            <p class="text-xs font-black uppercase tracking-widest text-slate-500">Pembimbing Dalam</p>
                            <p class="mt-1 font-black text-slate-950">{{ $report?->internalReviewStatusLabel() ?? 'Belum Review' }}</p>
                            @if($report?->internal_review_note)<p class="mt-2 text-xs text-slate-600">{{ $report->internal_review_note }}</p>@endif
                        </div>
                        <div class="rounded-xl bg-slate-50 p-4">
                            <p class="text-xs font-black uppercase tracking-widest text-slate-500">Pembimbing Lapangan</p>
                            <p class="mt-1 font-black text-slate-950">{{ $report?->fieldReviewStatusLabel() ?? 'Belum Review' }}</p>
                            @if($report?->field_review_note)<p class="mt-2 text-xs text-slate-600">{{ $report->field_review_note }}</p>@endif
                        </div>
                    </div>
                    @if($report?->review_note)
                        <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">{{ $report->review_note }}</div>
                    @endif
                    @if($report?->canBeSubmitted())
                        <form method="POST" action="{{ route('student.final-reports.submit') }}" class="mt-4">
                            @csrf
                            <button onclick="return confirm('Submit laporan final untuk review kedua pembimbing?')" class="w-full rounded-xl bg-slate-950 px-4 py-3 text-sm font-bold text-white">Submit Final untuk Review</button>
                        </form>
                    @endif
                </section>

                <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                    <h3 class="font-black text-slate-950">Bimbingan Laporan</h3>
                    <p class="mt-1 text-sm text-slate-500">{{ $approvedGuidance }}/8 bimbingan disetujui pembimbing dalam.</p>
                    <form method="POST" action="{{ route('student.final-reports.guidance.store') }}" class="mt-4 space-y-3">
                        @csrf
                        <input type="date" name="guidance_date" value="{{ old('guidance_date', now()->toDateString()) }}" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm shadow-sm">
                        <input name="topic" value="{{ old('topic') }}" placeholder="Topik bimbingan laporan" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm shadow-sm">
                        <textarea name="student_note" rows="3" placeholder="Catatan mahasiswa opsional" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm shadow-sm">{{ old('student_note') }}</textarea>
                        <button class="w-full rounded-xl bg-cyan-700 px-4 py-2 text-sm font-bold text-white">Kirim Log Bimbingan</button>
                    </form>
                    <div class="mt-4 max-h-96 space-y-2 overflow-y-auto pr-1">
                        @forelse($guidanceLogs as $guidance)
                            <div class="rounded-xl border border-slate-200 p-3 text-sm">
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <p class="font-bold text-slate-950">{{ $guidance->topic }}</p>
                                        <p class="text-xs text-slate-500">{{ $guidance->guidance_date->format('d M Y') }}</p>
                                    </div>
                                    <span class="rounded-full px-2.5 py-1 text-[11px] font-bold ring-1 {{ $guidance->statusBadgeClass() }}">{{ $guidance->statusLabel() }}</span>
                                </div>
                                @if($guidance->validation_note)<p class="mt-2 text-xs text-slate-600">{{ $guidance->validation_note }}</p>@endif
                            </div>
                        @empty
                            <p class="rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-500">Belum ada log bimbingan laporan.</p>
                        @endforelse
                    </div>
                </section>
            </aside>
        </section>
    @endif
</div>
@endsection
