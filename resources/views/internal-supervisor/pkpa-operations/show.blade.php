@extends('layouts.app')
@section('title', 'Pemantauan Mahasiswa')
@section('page_title', $selectedLogbook ? 'Validasi Logbook' : 'Pemantauan Mahasiswa')
@section('content')
@php
    $statusLabel = [
        'submitted' => 'Menunggu Preseptor',
        'field_approved' => 'Siap Validasi Akhir',
        'approved' => 'Siap Validasi Akhir',
        'internal_approved' => 'Tervalidasi Final',
        'revision_requested' => 'Perlu Revisi',
        'rejected' => 'Ditolak',
    ];
    $statusClass = [
        'submitted' => 'bg-amber-50 text-amber-700',
        'field_approved' => 'bg-cyan-50 text-cyan-700',
        'approved' => 'bg-cyan-50 text-cyan-700',
        'internal_approved' => 'bg-emerald-50 text-emerald-700',
        'revision_requested' => 'bg-orange-50 text-orange-700',
        'rejected' => 'bg-rose-50 text-rose-700',
    ];
    $canValidate = $selectedLogbook && in_array($selectedLogbook->status, ['field_approved', 'approved'], true);
    $internalReview = $selectedLogbook?->reviews
        ?->where('reviewer_type', 'internal')
        ->sortByDesc('reviewed_at')
        ->first();
@endphp

<div class="space-y-5">
    @if($errors->any())
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-bold text-rose-700">{{ $errors->first() }}</div>
    @endif

    @if($selectedLogbook)
        <div class="flex flex-wrap items-center justify-between gap-3">
            <a href="{{ route('internal-supervisor.pkpa-operations.show', ['run' => $run, 'view' => 'ready']) }}" class="inline-flex min-h-10 items-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-bold text-slate-700">Kembali ke Antrean Validasi</a>
            <p class="text-sm text-slate-500">{{ $run->studentDisplayName() }} · {{ $run->practiceSite?->name }}</p>
        </div>

        <section class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 {{ $canValidate ? 'ring-cyan-200' : 'ring-slate-200' }}">
            <header class="border-b border-slate-100 px-5 py-5 sm:px-6">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <p class="text-xs font-black uppercase tracking-widest text-cyan-700">{{ $run->practiceDomain?->name }} · {{ $selectedLogbook->entry_date?->translatedFormat('d M Y') }}</p>
                        <h2 class="mt-2 text-2xl font-black text-slate-950">{{ $selectedLogbook->title }}</h2>
                    </div>
                    <span class="w-fit shrink-0 rounded-full px-3 py-1 text-xs font-bold {{ $statusClass[$selectedLogbook->status] ?? 'bg-slate-100 text-slate-700' }}">{{ $statusLabel[$selectedLogbook->status] ?? str($selectedLogbook->status)->replace('_', ' ')->headline() }}</span>
                </div>
            </header>

            <div class="grid gap-0 xl:grid-cols-[minmax(0,1fr)_23rem]">
                <div class="space-y-5 p-5 sm:p-6">
                    <section>
                        <h3 class="text-xs font-black uppercase tracking-wide text-slate-500">Uraian Aktivitas</h3>
                        <p class="mt-2 whitespace-pre-line text-sm leading-7 text-slate-800">{{ $selectedLogbook->activity_summary }}</p>
                    </section>
                    <section class="border-t border-slate-100 pt-5">
                        <h3 class="text-xs font-black uppercase tracking-wide text-slate-500">Kompetensi yang Dicapai</h3>
                        <p class="mt-2 whitespace-pre-line text-sm leading-7 text-slate-800">{{ $selectedLogbook->learning_outcomes }}</p>
                    </section>
                    <section class="border-t border-slate-100 pt-5">
                        <h3 class="text-xs font-black uppercase tracking-wide text-slate-500">Refleksi Mahasiswa</h3>
                        <p class="mt-2 whitespace-pre-line text-sm leading-7 text-slate-800">{{ $selectedLogbook->reflection }}</p>
                    </section>
                    <section class="border-t border-slate-100 pt-5">
                        <h3 class="text-xs font-black uppercase tracking-wide text-slate-500">Bukti Kegiatan</h3>
                        <div class="mt-3 space-y-2">
                            @forelse($selectedLogbook->attachments as $attachment)
                                <a href="{{ $attachment->isExternalLink() ? $attachment->previewUrl() : route('internal-supervisor.pkpa-logbooks.attachments.download', $attachment) }}" target="_blank" class="flex min-h-11 items-center justify-between gap-3 rounded-lg border border-slate-200 px-4 py-3 text-sm font-bold text-slate-700 hover:border-cyan-300 hover:text-cyan-800">
                                    <span>{{ $attachment->displayLabel() }}</span>
                                    <span>{{ $attachment->isExternalLink() ? 'Preview Bukti' : 'Unduh Bukti' }}</span>
                                </a>
                            @empty
                                <p class="text-sm text-slate-500">Tidak ada bukti terlampir.</p>
                            @endforelse
                        </div>
                    </section>
                </div>

                <aside class="border-t border-slate-100 bg-slate-50 p-5 xl:border-l xl:border-t-0 sm:p-6">
                    <div class="xl:sticky xl:top-28">
                        @if($canValidate)
                            <p class="text-xs font-black uppercase tracking-widest text-cyan-700">Keputusan Pembimbing Dalam</p>
                            <h3 class="mt-2 text-xl font-black text-slate-950">Validasi logbook ini</h3>
                            <p class="mt-2 text-sm leading-6 text-slate-600">Pilih Validasi Final jika isi sudah benar. Catatan wajib untuk revisi atau penolakan.</p>
                            <form method="POST" action="{{ route('internal-supervisor.pkpa-logbooks.monitoring', $selectedLogbook) }}" class="mt-5 space-y-3">
                                @csrf
                                <label class="grid gap-2">
                                    <span class="text-sm font-bold text-slate-700">Catatan</span>
                                    <textarea name="comments" rows="5" class="w-full rounded-xl border-slate-200 text-sm" placeholder="Tulis catatan yang spesifik bila perlu"></textarea>
                                </label>
                                <button name="action" value="approved" class="flex min-h-11 w-full items-center justify-center rounded-xl bg-emerald-600 px-4 py-3 text-sm font-bold text-white">Validasi Final</button>
                                <div class="grid grid-cols-2 gap-2">
                                    <button name="action" value="revision_requested" class="min-h-11 rounded-xl border border-amber-300 bg-white px-3 py-2 text-sm font-bold text-amber-700">Minta Revisi</button>
                                    <button name="action" value="rejected" class="min-h-11 rounded-xl border border-rose-300 bg-white px-3 py-2 text-sm font-bold text-rose-700">Tolak</button>
                                </div>
                            </form>
                        @elseif($selectedLogbook->status === 'internal_approved')
                            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                                <p class="text-xs font-black uppercase tracking-widest text-emerald-700">Validasi Selesai</p>
                                <h3 class="mt-2 text-lg font-black text-emerald-950">Logbook sudah dikunci</h3>
                                <p class="mt-2 text-sm leading-6 text-emerald-800">Keputusan final sudah tersimpan. Formulir validasi dinonaktifkan agar keputusan tidak terisi dua kali.</p>
                            </div>
                            <dl class="mt-5 space-y-4 text-sm">
                                <div><dt class="font-bold text-slate-500">Waktu validasi</dt><dd class="mt-1 font-semibold text-slate-900">{{ $selectedLogbook->internal_reviewed_at?->translatedFormat('d M Y, H:i') ?? $internalReview?->reviewed_at?->translatedFormat('d M Y, H:i') ?? '-' }}</dd></div>
                                <div><dt class="font-bold text-slate-500">Keputusan</dt><dd class="mt-1 font-semibold text-emerald-700">Tervalidasi Final</dd></div>
                                <div><dt class="font-bold text-slate-500">Catatan</dt><dd class="mt-1 whitespace-pre-line leading-6 text-slate-800">{{ filled($internalReview?->comments) ? $internalReview->comments : 'Tidak ada catatan tambahan.' }}</dd></div>
                            </dl>
                            @if($nextReadyLogbook)
                                <a href="{{ route('internal-supervisor.pkpa-operations.show', ['run' => $run, 'view' => 'ready', 'logbook' => $nextReadyLogbook]) }}" class="mt-6 inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-cyan-700 px-4 py-2 text-center text-sm font-bold text-white">Validasi Logbook Berikutnya</a>
                            @else
                                <a href="{{ route('internal-supervisor.pkpa-operations.show', ['run' => $run, 'view' => 'ready']) }}" class="mt-6 inline-flex min-h-11 w-full items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-center text-sm font-bold text-slate-700">Kembali ke Antrean Validasi</a>
                            @endif
                        @else
                            <p class="text-xs font-black uppercase tracking-widest text-slate-500">Status Logbook</p>
                            <h3 class="mt-2 text-lg font-black text-slate-950">Belum dapat divalidasi</h3>
                            <p class="mt-2 text-sm leading-6 text-slate-600">{{ $selectedLogbook->status === 'submitted' ? 'Logbook masih menunggu keputusan Preseptor.' : 'Logbook ini tidak memerlukan validasi akhir pada status saat ini.' }}</p>
                            <a href="{{ route('internal-supervisor.pkpa-operations.show', ['run' => $run, 'view' => 'ready']) }}" class="mt-5 inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-cyan-700 px-4 py-2 text-sm font-bold text-white">Buka yang Siap Divalidasi</a>
                        @endif
                    </div>
                </aside>
            </div>
        </section>
    @else
        <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-sky-100 sm:p-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <p class="text-xs font-black uppercase tracking-widest text-cyan-700">{{ $run->practiceDomain?->name }}</p>
                    <h2 class="mt-1 text-2xl font-black text-slate-950">{{ $run->studentDisplayName() }}</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ $run->studentDisplaySecondary() }} · {{ $run->practiceSite?->name }}</p>
                </div>
                @if($readyCount > 0)
                    <a href="{{ route('internal-supervisor.pkpa-operations.show', ['run' => $run, 'view' => 'ready']) }}" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-cyan-700 px-5 py-2 text-sm font-bold text-white">Validasi {{ $readyCount }} Logbook</a>
                @endif
            </div>
            <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div class="border-l-4 border-slate-300 bg-slate-50 px-4 py-3"><p class="text-xs font-black uppercase text-slate-500">Presensi</p><p class="mt-1 text-2xl font-black">{{ $attendanceCount }}</p></div>
                <div class="border-l-4 border-amber-300 bg-amber-50 px-4 py-3"><p class="text-xs font-black uppercase text-amber-700">Menunggu Preseptor</p><p class="mt-1 text-2xl font-black">{{ $waitingFieldCount }}</p></div>
                <div class="border-l-4 border-cyan-400 bg-cyan-50 px-4 py-3"><p class="text-xs font-black uppercase text-cyan-700">Siap Divalidasi</p><p class="mt-1 text-2xl font-black">{{ $readyCount }}</p></div>
                <div class="border-l-4 border-emerald-300 bg-emerald-50 px-4 py-3"><p class="text-xs font-black uppercase text-emerald-700">Selesai</p><p class="mt-1 text-2xl font-black">{{ $completedCount }}</p></div>
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-sky-100">
            <nav class="flex overflow-x-auto border-b border-slate-200 px-3 sm:px-5" aria-label="Jenis data mahasiswa">
                @foreach([
                    'ready' => ['Siap Divalidasi', $readyCount],
                    'logbooks' => ['Semua Logbook', $logbookCount],
                    'attendance' => ['Presensi', $attendanceCount],
                ] as $key => [$label, $count])
                    <a href="{{ route('internal-supervisor.pkpa-operations.show', ['run' => $run, 'view' => $key]) }}" class="inline-flex min-h-12 shrink-0 items-center gap-2 border-b-2 px-4 text-sm font-bold {{ $view === $key ? 'border-cyan-700 text-cyan-800' : 'border-transparent text-slate-500 hover:text-slate-800' }}" @if($view === $key) aria-current="page" @endif>
                        {{ $label }} <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs">{{ $count }}</span>
                    </a>
                @endforeach
            </nav>

            <form method="GET" class="grid gap-3 border-b border-slate-100 bg-slate-50 px-4 py-4 sm:grid-cols-2 xl:grid-cols-5 sm:px-5">
                <input type="hidden" name="view" value="{{ $view }}">
                @if($view !== 'attendance')
                    <label class="grid gap-1"><span class="text-xs font-bold uppercase text-slate-500">Cari Logbook</span><input name="q" value="{{ $search }}" placeholder="Judul atau kegiatan" class="rounded-xl border-slate-200 text-sm"></label>
                @endif
                <label class="grid gap-1"><span class="text-xs font-bold uppercase text-slate-500">Dari Tanggal</span><input type="date" name="date_from" value="{{ $dateFrom }}" class="rounded-xl border-slate-200 text-sm"></label>
                <label class="grid gap-1"><span class="text-xs font-bold uppercase text-slate-500">Sampai Tanggal</span><input type="date" name="date_to" value="{{ $dateTo }}" class="rounded-xl border-slate-200 text-sm"></label>
                @if($view === 'logbooks')
                    <label class="grid gap-1"><span class="text-xs font-bold uppercase text-slate-500">Status</span><select name="status" class="rounded-xl border-slate-200 text-sm"><option value="all" @selected($status === 'all')>Semua</option><option value="waiting" @selected($status === 'waiting')>Menunggu Preseptor</option><option value="ready" @selected($status === 'ready')>Siap Divalidasi</option><option value="completed" @selected($status === 'completed')>Tervalidasi Final</option><option value="revision" @selected($status === 'revision')>Revisi atau Ditolak</option></select></label>
                @endif
                <div class="flex items-end gap-2"><button class="min-h-11 rounded-xl bg-slate-900 px-4 py-2 text-sm font-bold text-white">Terapkan</button><a href="{{ route('internal-supervisor.pkpa-operations.show', ['run' => $run, 'view' => $view]) }}" class="inline-flex min-h-11 items-center rounded-xl border border-slate-200 bg-white px-4 text-sm font-bold text-slate-600">Reset</a></div>
            </form>

            <div class="p-4 sm:p-5">
                @if($view === 'attendance')
                    <div class="divide-y divide-slate-100">
                        @forelse($attendances as $record)
                            <article class="flex flex-col gap-1 py-4 sm:flex-row sm:items-center sm:justify-between">
                                <div><p class="font-bold text-slate-950">{{ $record->attendance_date?->translatedFormat('d M Y') }}</p><p class="mt-1 text-sm text-slate-500">{{ $record->check_in_time ?: '-' }} - {{ $record->check_out_time ?: '-' }}</p></div>
                                <span class="w-fit rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">{{ $record->submission_status === 'approved' ? 'Disetujui Preseptor' : ($record->submission_status === 'submitted' ? 'Menunggu Preseptor' : 'Perlu tindak lanjut') }}</span>
                            </article>
                        @empty
                            <p class="py-10 text-center text-sm text-slate-500">Tidak ada presensi pada filter ini.</p>
                        @endforelse
                    </div>
                    @if($attendances->hasPages())<div class="border-t border-slate-100 pt-4">{{ $attendances->links() }}</div>@endif
                @else
                    <div class="divide-y divide-slate-100">
                        @forelse($logbooks as $entry)
                            <article class="flex flex-col gap-3 py-4 sm:flex-row sm:items-center sm:justify-between">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2"><p class="font-bold text-slate-950">{{ $entry->entry_date?->translatedFormat('d M Y') }} · {{ $entry->title }}</p><span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $statusClass[$entry->status] ?? 'bg-slate-100 text-slate-600' }}">{{ $statusLabel[$entry->status] ?? str($entry->status)->replace('_', ' ')->headline() }}</span></div>
                                    <p class="mt-1 truncate text-sm text-slate-500">{{ str($entry->activity_summary)->limit(110) }}</p>
                                </div>
                                <a href="{{ route('internal-supervisor.pkpa-operations.show', ['run' => $run, 'view' => $view, 'logbook' => $entry->id]) }}" class="inline-flex min-h-10 shrink-0 items-center justify-center rounded-lg {{ in_array($entry->status, ['field_approved', 'approved'], true) ? 'bg-cyan-700 text-white' : 'border border-cyan-200 text-cyan-800' }} px-4 py-2 text-sm font-bold">{{ in_array($entry->status, ['field_approved', 'approved'], true) ? 'Lihat & Validasi' : 'Lihat Detail' }}</a>
                            </article>
                        @empty
                            <div class="py-12 text-center"><p class="font-bold text-slate-700">{{ $view === 'ready' ? 'Tidak ada logbook yang menunggu validasi.' : 'Tidak ada logbook pada filter ini.' }}</p><p class="mt-1 text-sm text-slate-500">{{ $view === 'ready' ? 'Logbook akan masuk setelah disetujui Preseptor.' : 'Ubah atau reset filter untuk melihat data lainnya.' }}</p></div>
                        @endforelse
                    </div>
                    @if($logbooks->hasPages())<div class="border-t border-slate-100 pt-4">{{ $logbooks->links() }}</div>@endif
                @endif
            </div>
        </section>
    @endif
</div>
@endsection
