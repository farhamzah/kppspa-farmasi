@extends('layouts.app')
@section('title', 'Monitoring Logbook PKPA')
@section('page_title', 'Monitoring Logbook PKPA')
@section('content')
@php
    $progress = optional($run->progressSnapshots->first())->progress_percentage ?? 0;
    $attendanceStatuses = ['submitted' => 'Terkirim - Menunggu Preseptor', 'approved' => 'Disetujui Preseptor', 'revision_requested' => 'Perlu Revisi', 'rejected' => 'Ditolak'];
    $logbookStatuses = ['submitted' => 'Menunggu Preseptor', 'field_approved' => 'Disetujui Preseptor', 'internal_approved' => 'Tervalidasi Final', 'revision_requested' => 'Perlu Revisi', 'rejected' => 'Ditolak'];
    $visibleAttendances = $run->attendanceRecords->where('submission_status', '!=', 'draft')->sortByDesc('attendance_date')->values();
    $visibleLogbooks = $run->logbookEntries->where('status', '!=', 'draft')->sortByDesc(fn ($entry) => sprintf('%d-%s', in_array($entry->status, ['field_approved', 'approved'], true) ? 3 : ($entry->status === 'submitted' ? 2 : 1), optional($entry->entry_date)->format('Y-m-d')))->values();
    $validatableEntries = $visibleLogbooks->whereIn('status', ['field_approved', 'approved']);
    $waitingFieldEntries = $visibleLogbooks->where('status', 'submitted');
@endphp
<div class="space-y-5">
    @if(session('status'))<div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-bold text-rose-700">{{ $errors->first() }}</div>@endif
    <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-sky-100">
        <p class="text-xs font-black uppercase tracking-widest text-cyan-700">{{ $run->practiceDomain?->name }}</p>
        <h2 class="mt-1 text-2xl font-black text-slate-950">{{ $run->studentDisplayName() }}</h2>
        <p class="mt-1 text-sm text-slate-500">{{ $run->studentDisplaySecondary() }} / {{ $run->practiceSite?->name }}</p>
        <div class="mt-4 rounded-xl border border-sky-100 bg-sky-50 px-4 py-3 text-sm text-sky-900">Anda dapat memantau presensi dan logbook yang sudah dikirim mahasiswa. <span class="font-black">Aksi validasi akhir</span> hanya tersedia setelah logbook disetujui Preseptor. Draf mahasiswa tidak ditampilkan.</div>
        <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-xl bg-slate-50 px-4 py-3"><p class="text-xs font-black uppercase tracking-widest text-slate-500">Kemajuan</p><p class="mt-1 font-black text-slate-950">{{ $progress }}%</p></div>
            <div class="rounded-xl bg-slate-50 px-4 py-3"><p class="text-xs font-black uppercase tracking-widest text-slate-500">Presensi Terkirim</p><p class="mt-1 font-black text-slate-950">{{ $visibleAttendances->count() }}</p></div>
            <div class="rounded-xl bg-amber-50 px-4 py-3 ring-1 ring-amber-100"><p class="text-xs font-black uppercase tracking-widest text-amber-700">Menunggu Preseptor</p><p class="mt-1 font-black text-slate-950">{{ $waitingFieldEntries->count() }}</p></div>
            <div class="rounded-xl bg-emerald-50 px-4 py-3 ring-1 ring-emerald-100"><p class="text-xs font-black uppercase tracking-widest text-emerald-700">Siap Validasi Akhir</p><p class="mt-1 font-black text-slate-950">{{ $validatableEntries->count() }}</p></div>
        </div>
    </section>
    <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-sky-100">
        <div class="flex flex-wrap items-end justify-between gap-3"><div><h3 class="text-lg font-black text-slate-950">Presensi Mahasiswa</h3><p class="mt-1 text-sm text-slate-500">Presensi ditinjau oleh Preseptor. Pembimbing Dalam melihat progres dan catatan sebagai pemantauan.</p></div><span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">{{ $visibleAttendances->count() }} kiriman</span></div>
        <div class="mt-4 grid gap-3 lg:grid-cols-2">
            @forelse($visibleAttendances as $record)
                <article class="rounded-xl border border-slate-200 bg-slate-50 p-4"><div class="flex flex-wrap items-center justify-between gap-2"><p class="font-black text-slate-950">{{ $record->attendance_date?->format('d M Y') }}</p><span class="rounded-full px-3 py-1 text-xs font-bold {{ $record->submission_status === 'approved' ? 'bg-emerald-50 text-emerald-700' : ($record->submission_status === 'submitted' ? 'bg-amber-50 text-amber-700' : 'bg-rose-50 text-rose-700') }}">{{ $attendanceStatuses[$record->submission_status] ?? str($record->submission_status)->replace('_', ' ')->headline() }}</span></div><p class="mt-2 text-sm text-slate-700">{{ $record->attendance_type === 'present' ? 'Hadir' : str($record->attendance_type)->replace('_', ' ')->headline() }} · {{ $record->check_in_time ?: '-' }} - {{ $record->check_out_time ?: '-' }}</p><p class="mt-2 text-sm leading-6 text-slate-600">{{ $record->student_notes ?: 'Tanpa catatan mahasiswa.' }}</p>@if($record->field_supervisor_notes)<div class="mt-3 rounded-lg bg-white px-3 py-2 text-sm text-slate-700 ring-1 ring-slate-200"><span class="font-bold">Catatan Preseptor:</span> {{ $record->field_supervisor_notes }}</div>@endif</article>
            @empty
                <div class="rounded-xl border border-dashed border-slate-200 px-4 py-8 text-center text-sm text-slate-500 lg:col-span-2">Belum ada presensi terkirim dari mahasiswa. Draf tidak tampil pada halaman pembimbing.</div>
            @endforelse
        </div>
    </section>
    <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-sky-100">
        <h3 class="text-lg font-black text-slate-950">Pemantauan dan Validasi Logbook</h3>
        <p class="mt-1 text-sm text-slate-500">Logbook yang belum disetujui Preseptor tetap dapat dibaca. Tombol validasi hanya muncul pada logbook yang sudah disetujui Preseptor.</p>
        <div class="mt-4 space-y-4">
            @forelse($visibleLogbooks as $entry)
                @php
                    $canValidate = in_array($entry->status, ['field_approved', 'approved'], true);
                    $fieldReview = $entry->reviews->where('reviewer_type', 'field')->sortByDesc('reviewed_at')->first();
                    $statusClass = $canValidate ? 'bg-emerald-50 text-emerald-800 ring-emerald-200' : ($entry->status === 'submitted' ? 'bg-amber-50 text-amber-800 ring-amber-200' : ($entry->status === 'internal_approved' ? 'bg-cyan-50 text-cyan-800 ring-cyan-200' : 'bg-rose-50 text-rose-800 ring-rose-200'));
                @endphp
                @if($canValidate)<form method="POST" action="{{ route('internal-supervisor.pkpa-logbooks.monitoring', $entry) }}" class="rounded-2xl bg-white p-4 ring-1 ring-emerald-200">@csrf @else<article class="rounded-2xl bg-slate-50 p-4 ring-1 ring-slate-200">@endif
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between"><div><div class="flex flex-wrap items-center gap-2"><h4 class="text-lg font-black text-slate-950">{{ $entry->title }}</h4><span class="rounded-full px-3 py-1 text-xs font-bold ring-1 {{ $statusClass }}">{{ $logbookStatuses[$entry->status] ?? str($entry->status)->replace('_', ' ')->headline() }}</span></div><p class="mt-1 text-sm text-slate-500">{{ $entry->entry_date?->format('d M Y') }} · {{ $entry->practice_minutes ?: 0 }} menit praktik</p></div>@if($canValidate)<span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-black text-emerald-800">Siap Anda validasi</span>@endif</div>
                    <div class="mt-4 grid gap-3 lg:grid-cols-2"><div class="rounded-xl bg-white px-4 py-3 ring-1 ring-slate-200"><p class="text-xs font-black uppercase tracking-widest text-slate-500">Uraian Aktivitas</p><p class="mt-2 text-sm leading-6 text-slate-700">{{ $entry->activity_summary }}</p></div><div class="rounded-xl bg-white px-4 py-3 ring-1 ring-slate-200"><p class="text-xs font-black uppercase tracking-widest text-slate-500">Kompetensi</p><p class="mt-2 text-sm leading-6 text-slate-700">{{ $entry->learning_outcomes }}</p></div><div class="rounded-xl bg-white px-4 py-3 ring-1 ring-slate-200 lg:col-span-2"><p class="text-xs font-black uppercase tracking-widest text-slate-500">Refleksi Mahasiswa</p><p class="mt-2 text-sm leading-6 text-slate-700">{{ $entry->reflection }}</p></div></div>
                    @if($fieldReview?->comments)<div class="mt-3 rounded-xl border border-sky-100 bg-sky-50 px-4 py-3 text-sm text-sky-900"><p class="font-black">Catatan Preseptor</p><p class="mt-1 leading-6">{{ $fieldReview->comments }}</p></div>@endif
                    <div class="mt-3 space-y-2">@forelse($entry->attachments as $attachment)<div class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 lg:flex-row lg:items-center lg:justify-between"><div><p class="text-sm font-bold text-slate-900">{{ $attachment->displayLabel() }}</p><p class="mt-1 text-xs text-slate-500">{{ $attachment->isExternalLink() ? 'Tautan eksternal / Google Drive' : 'File unggahan / '.$attachment->humanFileSize() }}</p></div><div class="flex flex-wrap gap-2">@if($attachment->isExternalLink())<a href="{{ $attachment->previewUrl() }}" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-10 items-center justify-center rounded-xl border border-cyan-200 bg-white px-4 py-2 text-sm font-bold text-cyan-700">Preview Link</a><a href="{{ $attachment->externalDownloadUrl() }}" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-10 items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-700">Buka Drive</a>@else<a href="{{ route('internal-supervisor.pkpa-logbooks.attachments.download', $attachment) }}" class="inline-flex min-h-10 items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-700">Unduh File</a>@endif</div></div>@empty<div class="rounded-xl border border-dashed border-slate-200 px-4 py-4 text-sm text-slate-500">Belum ada bukti yang disertakan.</div>@endforelse</div>
                    @if($canValidate)<textarea name="comments" class="mt-3 min-h-28 w-full rounded-2xl border-slate-200 px-4 py-3 text-sm" placeholder="Catatan validasi pembimbing dalam (wajib untuk revisi atau penolakan)"></textarea><div class="mt-3 flex flex-wrap gap-2"><button name="action" value="approved" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-black text-white">Validasi Final</button><button name="action" value="revision_requested" class="rounded-xl border border-amber-200 px-4 py-2 text-sm font-black text-amber-700">Minta Revisi</button><button name="action" value="rejected" class="rounded-xl border border-rose-200 px-4 py-2 text-sm font-black text-rose-700">Tolak</button></div>@else<div class="mt-3 rounded-xl {{ $entry->status === 'submitted' ? 'bg-amber-50 text-amber-900' : ($entry->status === 'internal_approved' ? 'bg-cyan-50 text-cyan-900' : 'bg-rose-50 text-rose-900') }} px-4 py-3 text-sm">{{ $entry->status === 'submitted' ? 'Belum ada aksi untuk Pembimbing Dalam karena logbook masih menunggu keputusan Preseptor.' : ($entry->status === 'internal_approved' ? 'Logbook ini sudah tervalidasi final dan tidak memerlukan aksi lagi.' : 'Logbook ini memerlukan perbaikan atau sudah ditolak. Aksi validasi baru tersedia setelah mahasiswa mengirim ulang dan Preseptor menyetujuinya.') }}</div>@endif
                @if($canValidate)</form>@else</article>@endif
            @empty
                <div class="rounded-xl border border-dashed border-slate-200 px-4 py-8 text-center text-sm text-slate-500">Belum ada logbook terkirim dari mahasiswa. Draf tidak tampil pada halaman pembimbing.</div>
            @endforelse
        </div>
    </section>
</div>
@endsection
