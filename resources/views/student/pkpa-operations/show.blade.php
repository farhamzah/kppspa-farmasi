@extends('layouts.app')
@section('title', 'Detail Rotasi PKPA')
@section('page_title', 'Detail Rotasi PKPA')
@section('content')
@php
    $attendanceStatuses = [
        'draft' => 'Draf',
        'submitted' => 'Menunggu Validasi',
        'approved' => 'Disetujui',
        'revision_requested' => 'Perlu Revisi',
        'rejected' => 'Ditolak',
    ];
    $logbookStatuses = [
        'draft' => 'Draf',
        'submitted' => 'Menunggu Validasi',
        'approved' => 'Disetujui Preseptor',
        'revision_requested' => 'Perlu Revisi',
        'rejected' => 'Ditolak',
        'reviewed_by_internal' => 'Sudah Dimonitor Pembimbing Dalam',
    ];
    $attendancePending = $run->attendanceRecords->whereIn('submission_status', ['draft', 'revision_requested'])->count();
    $logbookPending = $run->logbookEntries->whereIn('status', ['draft', 'revision_requested'])->count();
@endphp
<div class="space-y-5">
    @if(session('status'))<div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-bold text-rose-700">{{ $errors->first() }}</div>@endif
    <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-sky-100">
        <p class="text-xs font-black uppercase tracking-widest text-cyan-700">{{ $run->practiceDomain?->name }}</p>
        <h2 class="mt-1 text-2xl font-black text-slate-950">{{ $run->practiceSite?->name }}</h2>
        <p class="mt-2 text-sm text-slate-500">{{ $run->scheduled_start_date?->format('d M Y') }} - {{ $run->scheduled_end_date?->format('d M Y') }} / status {{ str($run->status)->replace('_', ' ')->headline() }}</p>
        <div class="mt-4 rounded-2xl border border-sky-100 bg-sky-50 px-4 py-3 text-sm text-sky-900">
            Halaman ini adalah pusat aktivitas rotasi Anda. Gunakan bagian kiri untuk presensi harian dan bagian kanan untuk logbook kegiatan pada rotasi yang sama.
        </div>
        <div class="mt-4 grid gap-3 md:grid-cols-3">
            <div class="rounded-xl bg-slate-50 px-4 py-3">
                <p class="text-xs font-black uppercase tracking-widest text-slate-500">Kemajuan</p>
                <p class="mt-1 font-black text-slate-950">{{ optional($run->progressSnapshots->first())->progress_percentage ?? 0 }}%</p>
            </div>
            <div class="rounded-xl bg-slate-50 px-4 py-3">
                <p class="text-xs font-black uppercase tracking-widest text-slate-500">Presensi Perlu Aksi</p>
                <p class="mt-1 font-black text-slate-950">{{ $attendancePending }}</p>
            </div>
            <div class="rounded-xl bg-slate-50 px-4 py-3">
                <p class="text-xs font-black uppercase tracking-widest text-slate-500">Logbook Perlu Aksi</p>
                <p class="mt-1 font-black text-slate-950">{{ $logbookPending }}</p>
            </div>
        </div>
    </section>
    <div class="grid gap-5 xl:grid-cols-2">
        <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-sky-100">
            <h3 class="text-lg font-black text-slate-950">Presensi</h3>
            <p class="mt-1 text-sm text-slate-500">Isi presensi untuk tanggal praktik yang dipilih. Simpan dulu sebagai draf, lalu kirim saat data jam masuk, jam pulang, dan catatan sudah lengkap.</p>
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
                        <div>
                            <p class="font-black text-slate-950">{{ $record->attendance_date?->format('d M Y') }}</p>
                            <p class="text-xs text-slate-500">{{ $attendanceStatuses[$record->submission_status] ?? str($record->submission_status)->replace('_', ' ')->headline() }}{{ $record->check_in_time || $record->check_out_time ? ' / '.$record->check_in_time.' - '.$record->check_out_time : '' }}</p>
                        </div>
                        @if(in_array($record->submission_status, ['draft', 'revision_requested']))
                            <form method="POST" action="{{ route('student.pkpa-attendance.submit', $record) }}">@csrf<button class="rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-black text-white">Kirim</button></form>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>
        <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-sky-100">
            <h3 class="text-lg font-black text-slate-950">Logbook</h3>
            <p class="mt-1 text-sm text-slate-500">Isi logbook untuk kegiatan pada rotasi ini. Tulis ringkasan kegiatan, capaian pembelajaran, dan refleksi. Tambahkan lampiran bila perlu sebelum dikirim.</p>
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
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <p class="font-black text-slate-950">{{ $entry->title }}</p>
                                <p class="text-xs text-slate-500">{{ $entry->entry_date?->format('d M Y') }} / {{ $logbookStatuses[$entry->status] ?? str($entry->status)->replace('_', ' ')->headline() }}</p>
                            </div>
                            @if(in_array($entry->status, ['draft', 'revision_requested']))<form method="POST" action="{{ route('student.pkpa-logbooks.submit', $entry) }}">@csrf<button class="rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-black text-white">Kirim</button></form>@endif
                        </div>
                        <p class="mt-2 text-slate-600">{{ $entry->activity_summary }}</p>
                        @if($entry->attachments->isNotEmpty())
                            <p class="mt-2 text-xs text-slate-500">{{ $entry->attachments->count() }} lampiran tersimpan.</p>
                        @endif
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
