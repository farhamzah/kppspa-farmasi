@extends('layouts.app')
@section('title', 'Detail Rotasi PKPA')
@section('page_title', 'Detail Rotasi PKPA')
@section('content')
<div class="space-y-5">
    @if($errors->any())<div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-bold text-rose-700">{{ $errors->first() }}</div>@endif
    <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-sky-100">
        <p class="text-xs font-black uppercase tracking-widest text-cyan-700">{{ $run->practiceDomain?->name }}</p>
        <h2 class="mt-1 text-2xl font-black text-slate-950">{{ $run->practiceSite?->name }}</h2>
        <p class="mt-2 text-sm text-slate-500">{{ $run->scheduled_start_date?->format('d M Y') }} - {{ $run->scheduled_end_date?->format('d M Y') }} / status {{ $run->status }}</p>
    </section>
    <div class="grid gap-5 xl:grid-cols-2">
        <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-sky-100">
            <h3 class="text-lg font-black text-slate-950">Presensi</h3>
            <form method="POST" action="{{ route('student.pkpa-operations.attendance.store', $run) }}" class="mt-4 grid gap-3 sm:grid-cols-2">
                @csrf
                <input name="attendance_date" type="date" class="rounded-xl border-slate-200 text-sm" required>
                <select name="attendance_type" class="rounded-xl border-slate-200 text-sm"><option value="present">Hadir</option><option value="sick">Sakit</option><option value="permit">Izin</option><option value="institution_closed">Tempat tutup</option></select>
                <input name="check_in_time" type="time" class="rounded-xl border-slate-200 text-sm">
                <input name="check_out_time" type="time" class="rounded-xl border-slate-200 text-sm">
                <textarea name="student_notes" class="rounded-xl border-slate-200 text-sm sm:col-span-2" placeholder="Catatan"></textarea>
                <button class="rounded-xl bg-cyan-700 px-4 py-2 text-sm font-black text-white sm:col-span-2">Simpan Presensi</button>
            </form>
            <div class="mt-5 space-y-2">
                @foreach($run->attendanceRecords->sortByDesc('attendance_date') as $record)
                    <div class="flex flex-wrap items-center justify-between gap-2 rounded-xl bg-slate-50 p-3 text-sm">
                        <span><b>{{ $record->attendance_date?->format('d M Y') }}</b> / {{ $record->submission_status }}</span>
                        @if(in_array($record->submission_status, ['draft', 'revision_requested']))
                            <form method="POST" action="{{ route('student.pkpa-attendance.submit', $record) }}">@csrf<button class="rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-black text-white">Kirim</button></form>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>
        <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-sky-100">
            <h3 class="text-lg font-black text-slate-950">Logbook</h3>
            <form method="POST" action="{{ route('student.pkpa-logbooks.store', $run) }}" class="mt-4 grid gap-3">
                @csrf
                <input name="entry_date" type="date" class="rounded-xl border-slate-200 text-sm" required>
                <input name="title" class="rounded-xl border-slate-200 text-sm" placeholder="Judul kegiatan" required>
                <textarea name="activity_summary" class="rounded-xl border-slate-200 text-sm" placeholder="Ringkasan kegiatan" required></textarea>
                <textarea name="learning_outcomes" class="rounded-xl border-slate-200 text-sm" placeholder="Capaian pembelajaran" required></textarea>
                <textarea name="reflection" class="rounded-xl border-slate-200 text-sm" placeholder="Refleksi" required></textarea>
                <input name="practice_minutes" type="number" min="0" class="rounded-xl border-slate-200 text-sm" placeholder="Durasi praktik dalam menit">
                <button class="rounded-xl bg-cyan-700 px-4 py-2 text-sm font-black text-white">Simpan Logbook</button>
            </form>
            <div class="mt-5 space-y-2">
                @foreach($run->logbookEntries->sortByDesc('entry_date') as $entry)
                    <div class="rounded-xl bg-slate-50 p-3 text-sm">
                        <div class="flex flex-wrap items-center justify-between gap-2"><span><b>{{ $entry->title }}</b> / {{ $entry->status }}</span>@if(in_array($entry->status, ['draft', 'revision_requested']))<form method="POST" action="{{ route('student.pkpa-logbooks.submit', $entry) }}">@csrf<button class="rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-black text-white">Kirim</button></form>@endif</div>
                        @if(in_array($entry->status, ['draft', 'revision_requested']))
                            <form method="POST" enctype="multipart/form-data" action="{{ route('student.pkpa-logbooks.attachments.store', $entry) }}" class="mt-2 flex flex-wrap gap-2">@csrf<input name="attachment" type="file" class="text-xs"><button class="rounded-lg border border-slate-200 px-3 py-1 text-xs font-black">Unggah</button></form>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>
    </div>
</div>
@endsection
