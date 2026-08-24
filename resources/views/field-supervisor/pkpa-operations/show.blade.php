@extends('layouts.app')
@section('title', 'Validasi Operasional PKPA')
@section('page_title', 'Validasi Operasional PKPA')
@section('content')
@php
    $pendingAttendances = $run->attendanceRecords->where('submission_status', 'submitted');
    $pendingLogbooks = $run->logbookEntries->where('status', 'submitted');
    $progress = optional($run->progressSnapshots->first())->progress_percentage ?? 0;
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
            <div class="rounded-xl bg-slate-50 px-4 py-3"><p class="text-xs font-black uppercase tracking-widest text-slate-500">Presensi Menunggu</p><p class="mt-1 font-black text-slate-950">{{ $pendingAttendances->count() }}</p></div>
            <div class="rounded-xl bg-slate-50 px-4 py-3"><p class="text-xs font-black uppercase tracking-widest text-slate-500">Logbook Menunggu</p><p class="mt-1 font-black text-slate-950">{{ $pendingLogbooks->count() }}</p></div>
        </div>
    </section>
    <div class="grid gap-5 xl:grid-cols-2">
        <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-sky-100"><h3 class="text-lg font-black">Presensi Terkirim</h3><p class="mt-1 text-sm text-slate-500">Periksa tanggal, jam praktik, dan catatan mahasiswa sebelum menyetujui.</p><div class="mt-4 space-y-3">@forelse($pendingAttendances as $record)<form method="POST" action="{{ route('field-supervisor.pkpa-attendance.review', $record) }}" class="rounded-xl bg-slate-50 p-3">@csrf<p class="font-bold text-slate-950">{{ $record->attendance_date?->format('d M Y') }} / {{ $record->check_in_time }}-{{ $record->check_out_time }}</p><p class="mt-1 text-sm text-slate-500">{{ $record->student_notes ?: 'Tanpa catatan mahasiswa.' }}</p><textarea name="notes" class="mt-2 w-full rounded-xl border-slate-200 text-sm" placeholder="Catatan validasi"></textarea><div class="mt-2 flex flex-wrap gap-2"><button name="action" value="approved" class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-black text-white">Setujui</button><button name="action" value="revision_requested" class="rounded-lg border border-amber-200 px-3 py-1.5 text-xs font-black text-amber-700">Revisi</button><button name="action" value="rejected" class="rounded-lg border border-rose-200 px-3 py-1.5 text-xs font-black text-rose-700">Tolak</button></div></form>@empty<div class="rounded-xl border border-dashed border-slate-200 px-4 py-8 text-center text-sm text-slate-500">Tidak ada presensi yang sedang menunggu validasi.</div>@endforelse</div></section>
        <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-sky-100"><h3 class="text-lg font-black">Logbook Terkirim</h3><p class="mt-1 text-sm text-slate-500">Pastikan isi kegiatan, pembelajaran, dan refleksi sudah cukup sebelum diputuskan.</p><div class="mt-4 space-y-3">@forelse($pendingLogbooks as $entry)<form method="POST" action="{{ route('field-supervisor.pkpa-logbooks.review', $entry) }}" class="rounded-xl bg-slate-50 p-3">@csrf<p class="font-bold text-slate-950">{{ $entry->title }}</p><p class="mt-1 text-sm text-slate-500">{{ $entry->activity_summary }}</p><p class="mt-1 text-xs text-slate-500">{{ $entry->attachments->count() }} lampiran</p><textarea name="comments" class="mt-2 w-full rounded-xl border-slate-200 text-sm" placeholder="Catatan review"></textarea><div class="mt-2 flex flex-wrap gap-2"><button name="action" value="approved" class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-black text-white">Setujui</button><button name="action" value="revision_requested" class="rounded-lg border border-amber-200 px-3 py-1.5 text-xs font-black text-amber-700">Revisi</button><button name="action" value="rejected" class="rounded-lg border border-rose-200 px-3 py-1.5 text-xs font-black text-rose-700">Tolak</button></div></form>@empty<div class="rounded-xl border border-dashed border-slate-200 px-4 py-8 text-center text-sm text-slate-500">Tidak ada logbook yang sedang menunggu validasi.</div>@endforelse</div></section>
    </div>
</div>
@endsection
