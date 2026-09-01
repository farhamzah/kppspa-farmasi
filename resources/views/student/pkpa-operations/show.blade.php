@extends('layouts.app')
@section('title', 'Detail Rotasi PKPA')
@section('page_title', 'Detail Rotasi PKPA')
@section('content')
@php
    $attendanceStatuses = [
        'draft' => 'Draf',
        'submitted' => 'Terkirim - Menunggu Preseptor',
        'approved' => 'Disetujui',
        'revision_requested' => 'Perlu Revisi',
        'rejected' => 'Ditolak',
    ];
    $logbookStatuses = [
        'draft' => 'Draf',
        'submitted' => 'Menunggu Validasi Preseptor',
        'field_approved' => 'Tervalidasi Preseptor',
        'internal_approved' => 'Tervalidasi Pembimbing Dalam',
        'revision_requested' => 'Perlu Revisi',
        'rejected' => 'Ditolak',
    ];
    $attendanceDraftCount = $run->attendanceRecords->whereIn('submission_status', ['draft', 'revision_requested'])->count();
    $attendanceSubmittedCount = $run->attendanceRecords->where('submission_status', 'submitted')->count();
    $logbookDraftCount = $run->logbookEntries->whereIn('status', ['draft', 'revision_requested'])->count();
    $logbookSubmittedCount = $run->logbookEntries->where('status', 'submitted')->count();
    $logbookFinalCount = $run->logbookEntries->where('status', 'internal_approved')->count();
    $assignmentSupervisors = $run->currentAssignment?->supervisors ?? collect();
    $fieldSupervisor = $run->activeSupervisor('field')
        ?? $run->supervisorHistories->firstWhere('supervisor_type', 'field')
        ?? $assignmentSupervisors->firstWhere('supervisor_type', 'field');
    $internalSupervisor = $run->activeSupervisor('internal')
        ?? $run->supervisorHistories->firstWhere('supervisor_type', 'internal')
        ?? $assignmentSupervisors->firstWhere('supervisor_type', 'internal');
    $identityRows = [
        'Nama Mahasiswa' => $run->studentDisplayName(),
        'NIM' => $run->enrollment?->student_number ?: '-',
        'Universitas' => 'Universitas Buana Perjuangan Karawang',
        'Wahana PKPA' => $run->practiceDomain?->name ?: '-',
        'Periode PKPA' => trim(collect([
            optional($run->scheduled_start_date)->format('d M Y'),
            optional($run->scheduled_end_date)->format('d M Y'),
        ])->filter()->implode(' - ')) ?: '-',
        'Preseptor' => $fieldSupervisor?->name_snapshot ?: '-',
    ];
    $editableLogbookStatuses = ['draft', 'revision_requested'];
    $editableAttendanceStatuses = ['draft', 'revision_requested'];
    $attendanceList = $run->attendanceRecords->sortByDesc(fn ($record) => sprintf('%d-%s', $record->submission_status === 'submitted' ? 2 : ($record->submission_status === 'draft' ? 0 : 1), optional($record->attendance_date)->format('Y-m-d')))->values();
    $logbookList = $run->logbookEntries->sortByDesc(fn ($entry) => sprintf('%d-%s', $entry->status === 'submitted' ? 3 : (in_array($entry->status, ['field_approved', 'internal_approved'], true) ? 2 : ($entry->status === 'draft' ? 0 : 1)), optional($entry->entry_date)->format('Y-m-d')))->values();
@endphp
<div class="space-y-5">
    @if(session('status'))<div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-bold text-rose-700">{{ $errors->first() }}</div>@endif
    <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-sky-100">
        <p class="text-xs font-black uppercase tracking-widest text-cyan-700">{{ $run->practiceDomain?->name }}</p>
        <h2 class="mt-1 text-2xl font-black text-slate-950">{{ $run->practiceSite?->name }}</h2>
        <p class="mt-2 text-sm text-slate-500">{{ $run->scheduled_start_date?->format('d M Y') }} - {{ $run->scheduled_end_date?->format('d M Y') }} / status {{ str($run->status)->replace('_', ' ')->headline() }}</p>
        <div class="mt-4 rounded-2xl border border-sky-100 bg-sky-50 px-4 py-3 text-sm text-sky-900">
            Draf hanya terlihat oleh Anda. Tombol <span class="font-black">Kirim ke Preseptor</span> mengubah draf menjadi kiriman yang dapat divalidasi. Logbook baru selesai setelah Preseptor dan Pembimbing Dalam menyetujui.
        </div>
        <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-xl bg-slate-50 px-4 py-3">
                <p class="text-xs font-black uppercase tracking-widest text-slate-500">Kemajuan</p>
                <p class="mt-1 font-black text-slate-950">{{ optional($run->progressSnapshots->first())->progress_percentage ?? 0 }}%</p>
            </div>
            <div class="rounded-xl bg-amber-50 px-4 py-3 ring-1 ring-amber-100">
                <p class="text-xs font-black uppercase tracking-widest text-amber-700">Presensi Belum Dikirim</p>
                <p class="mt-1 font-black text-slate-950">{{ $attendanceDraftCount }}</p>
            </div>
            <div class="rounded-xl bg-cyan-50 px-4 py-3 ring-1 ring-cyan-100">
                <p class="text-xs font-black uppercase tracking-widest text-cyan-700">Menunggu Preseptor</p>
                <p class="mt-1 font-black text-slate-950">{{ $attendanceSubmittedCount + $logbookSubmittedCount }}</p>
            </div>
            <div class="rounded-xl bg-emerald-50 px-4 py-3 ring-1 ring-emerald-100">
                <p class="text-xs font-black uppercase tracking-widest text-emerald-700">Logbook Validasi Final</p>
                <p class="mt-1 font-black text-slate-950">{{ $logbookFinalCount }}</p>
            </div>
        </div>
    </section>
    <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-sky-100">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="text-xs font-black uppercase tracking-widest text-cyan-700">Identitas Logbook Harian</p>
                <h3 class="mt-2 text-2xl font-black text-slate-950">Informasi Dasar Rotasi</h3>
                <p class="mt-2 max-w-3xl text-sm text-slate-500">Bagian ini membantu mahasiswa memastikan logbook diisi pada rotasi, wahana, dan periode yang benar sebelum menulis aktivitas harian.</p>
            </div>
            @if($internalSupervisor)
                <div class="rounded-2xl bg-slate-50 px-4 py-3 text-sm text-slate-600 ring-1 ring-slate-200">
                    <p class="text-xs font-black uppercase tracking-widest text-slate-500">Pembimbing Dalam</p>
                    <p class="mt-1 font-black text-slate-950">{{ $internalSupervisor->name_snapshot }}</p>
                </div>
            @endif
        </div>
        <dl class="mt-6 grid gap-x-8 gap-y-4 lg:grid-cols-2">
            @foreach($identityRows as $label => $value)
                <div class="border-b border-dashed border-slate-200 pb-3">
                    <dt class="text-sm font-black text-slate-700">{{ $label }}</dt>
                    <dd class="mt-1 text-lg font-semibold text-slate-950">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>
    </section>

    <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-sky-100">
        <h3 class="text-2xl font-black text-slate-950">Presensi Harian</h3>
        <p class="mt-2 max-w-3xl text-sm text-slate-500">Jika data sudah lengkap, langsung tekan <span class="font-bold text-cyan-700">Kirim ke Preseptor</span>. Pilih simpan draf hanya bila Anda memang ingin melanjutkan pengisian nanti.</p>
        <form method="POST" action="{{ route('student.pkpa-operations.attendance.store', $run) }}" class="mt-6 grid gap-5 lg:grid-cols-2" id="attendance-form">
            @csrf
            <input type="hidden" name="attendance_record_id" id="attendance_record_id">
            <label class="grid gap-2">
                <span class="text-sm font-black text-slate-700">Tanggal Praktik</span>
                <input name="attendance_date" id="attendance_date" type="date" class="rounded-2xl border-slate-200 px-4 py-3 text-base" required>
            </label>
            <label class="grid gap-2">
                <span class="text-sm font-black text-slate-700">Status Kehadiran</span>
                <select name="attendance_type" id="attendance_type" class="rounded-2xl border-slate-200 px-4 py-3 text-base">
                    <option value="present">Hadir</option>
                    <option value="sick">Sakit</option>
                    <option value="permit">Izin</option>
                    <option value="institution_closed">Tempat tutup</option>
                </select>
            </label>
            <label class="grid gap-2">
                <span class="text-sm font-black text-slate-700">Jam Masuk</span>
                <input name="check_in_time" id="check_in_time" type="time" class="rounded-2xl border-slate-200 px-4 py-3 text-base">
            </label>
            <label class="grid gap-2">
                <span class="text-sm font-black text-slate-700">Jam Pulang</span>
                <input name="check_out_time" id="check_out_time" type="time" class="rounded-2xl border-slate-200 px-4 py-3 text-base">
            </label>
            <label class="grid gap-2 lg:col-span-2">
                <span class="text-sm font-black text-slate-700">Catatan Presensi</span>
                <textarea name="student_notes" id="student_notes" rows="4" class="rounded-2xl border-slate-200 px-4 py-3 text-base" placeholder="Tuliskan keterangan singkat bila diperlukan, misalnya kegiatan utama hari ini atau alasan jika jam tidak lengkap."></textarea>
            </label>
            <div class="flex flex-wrap gap-3 lg:col-span-2">
                <button id="attendance-submit-button" name="submission_action" value="submit" class="inline-flex min-h-14 flex-1 items-center justify-center rounded-2xl bg-cyan-700 px-5 py-3 text-base font-black text-white">Kirim ke Preseptor</button>
                <button id="attendance-draft-button" name="submission_action" value="draft" class="inline-flex min-h-14 items-center justify-center rounded-2xl border border-slate-200 bg-white px-5 py-3 text-sm font-black text-slate-700">Simpan sebagai Draf</button>
                <button type="button" id="attendance-reset-button" class="inline-flex min-h-14 items-center justify-center rounded-2xl border border-slate-200 px-5 py-3 text-base font-black text-slate-700">Form Baru</button>
            </div>
        </form>
        <div class="mt-8 rounded-2xl border border-slate-200 bg-slate-50/60 p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h4 class="text-lg font-black text-slate-950">Daftar Presensi</h4>
                    <p class="text-sm text-slate-500">Item terkirim dan sedang diproses ditampilkan lebih dahulu. Draf tetap dapat diedit, dihapus, atau dikirim.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="inline-flex rounded-full bg-white px-3 py-1 text-xs font-bold text-slate-600 ring-1 ring-slate-200">Urut tanggal</span>
                    <span class="inline-flex rounded-full bg-white px-3 py-1 text-xs font-bold text-slate-600 ring-1 ring-slate-200">{{ $attendanceList->count() }} entri</span>
                </div>
            </div>
            <div class="mt-4 space-y-3">
                @forelse($attendanceList as $record)
                    <details class="group rounded-2xl {{ $record->submission_status === 'draft' ? 'border border-dashed border-slate-300 bg-slate-50 p-3' : 'bg-white p-4 ring-1 ring-slate-200' }}">
                        <summary class="flex cursor-pointer list-none flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="text-base font-black text-slate-950">Presensi {{ $record->attendance_date?->format('d M Y') }}</p>
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-bold ring-1 {{ $record->submission_status === 'draft' ? 'bg-slate-100 text-slate-600 ring-slate-200' : ($record->submission_status === 'submitted' ? 'bg-cyan-50 text-cyan-700 ring-cyan-200' : ($record->submission_status === 'approved' ? 'bg-emerald-50 text-emerald-700 ring-emerald-200' : 'bg-amber-50 text-amber-700 ring-amber-200')) }}">{{ $attendanceStatuses[$record->submission_status] ?? str($record->submission_status)->replace('_', ' ')->headline() }}</span>
                                </div>
                                <p class="mt-1 text-sm text-slate-500">{{ $record->attendance_type === 'present' ? 'Hadir' : str($record->attendance_type)->replace('_', ' ')->headline() }}{{ $record->check_in_time || $record->check_out_time ? ' / '.$record->check_in_time.' - '.$record->check_out_time : '' }}</p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                @if(in_array($record->submission_status, $editableAttendanceStatuses, true))
                                    <button type="button" class="attendance-edit-button inline-flex min-h-10 items-center justify-center rounded-xl border border-slate-200 px-4 py-2 text-sm font-bold text-slate-700"
                                        data-id="{{ $record->id }}"
                                        data-date="{{ $record->attendance_date?->format('Y-m-d') }}"
                                        data-type="{{ $record->attendance_type }}"
                                        data-check-in="{{ $record->check_in_time ? substr((string) $record->check_in_time, 0, 5) : '' }}"
                                        data-check-out="{{ $record->check_out_time ? substr((string) $record->check_out_time, 0, 5) : '' }}"
                                        data-notes="{{ e($record->student_notes ?? '') }}">Edit Draft</button>
                                    <form method="POST" action="{{ route('student.pkpa-attendance.destroy', $record) }}" onsubmit="return confirm('Hapus presensi draft ini?');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="inline-flex min-h-10 items-center justify-center rounded-xl border border-rose-200 px-4 py-2 text-sm font-bold text-rose-700">Hapus</button>
                                    </form>
                                    <form method="POST" action="{{ route('student.pkpa-attendance.submit', $record) }}">
                                        @csrf
                                        <button class="inline-flex min-h-10 items-center justify-center rounded-xl bg-cyan-700 px-4 py-2 text-sm font-bold text-white">Kirim ke Preseptor</button>
                                    </form>
                                @endif
                                <span class="inline-flex min-h-10 items-center justify-center rounded-xl border border-cyan-200 px-4 py-2 text-sm font-bold text-cyan-700 group-open:hidden">Lihat Detail</span>
                            </div>
                        </summary>
                        <div class="mt-4 grid gap-3 border-t border-dashed border-slate-200 pt-4 lg:grid-cols-2">
                            <div class="rounded-xl {{ $record->submission_status === 'draft' ? 'bg-amber-50 text-amber-900' : ($record->submission_status === 'submitted' ? 'bg-cyan-50 text-cyan-900' : 'bg-emerald-50 text-emerald-900') }} px-4 py-3 lg:col-span-2">
                                <p class="font-black">{{ $record->submission_status === 'draft' ? 'Belum dikirim ke Preseptor' : ($record->submission_status === 'submitted' ? 'Sudah terkirim ke Preseptor dan menunggu validasi' : 'Presensi sudah diputuskan Preseptor') }}</p>
                                <p class="mt-1 text-sm">{{ $record->submission_status === 'draft' ? 'Anda masih dapat mengedit atau menghapus draf ini.' : ($record->submitted_at ? 'Dikirim pada '.$record->submitted_at->format('d M Y H:i') : 'Status kiriman tercatat pada sistem.') }}</p>
                            </div>
                            <div class="rounded-xl bg-slate-50 px-4 py-3">
                                <p class="text-xs font-black uppercase tracking-widest text-slate-500">Tanggal Praktik</p>
                                <p class="mt-2 text-sm text-slate-800">{{ $record->attendance_date?->format('d M Y') }}</p>
                            </div>
                            <div class="rounded-xl bg-slate-50 px-4 py-3">
                                <p class="text-xs font-black uppercase tracking-widest text-slate-500">Status Kehadiran</p>
                                <p class="mt-2 text-sm text-slate-800">{{ $record->attendance_type === 'present' ? 'Hadir' : str($record->attendance_type)->replace('_', ' ')->headline() }}</p>
                            </div>
                            <div class="rounded-xl bg-slate-50 px-4 py-3">
                                <p class="text-xs font-black uppercase tracking-widest text-slate-500">Jam Masuk</p>
                                <p class="mt-2 text-sm text-slate-800">{{ $record->check_in_time ?: '-' }}</p>
                            </div>
                            <div class="rounded-xl bg-slate-50 px-4 py-3">
                                <p class="text-xs font-black uppercase tracking-widest text-slate-500">Jam Pulang</p>
                                <p class="mt-2 text-sm text-slate-800">{{ $record->check_out_time ?: '-' }}</p>
                            </div>
                            <div class="rounded-xl bg-slate-50 px-4 py-3 lg:col-span-2">
                                <p class="text-xs font-black uppercase tracking-widest text-slate-500">Catatan Presensi</p>
                                <p class="mt-2 text-sm leading-6 text-slate-800">{{ $record->student_notes ?: 'Belum ada catatan.' }}</p>
                            </div>
                        </div>
                    </details>
                @empty
                    <div class="rounded-xl border border-dashed border-slate-200 px-4 py-6 text-sm text-slate-500">Belum ada presensi tersimpan.</div>
                @endforelse
            </div>
        </div>
    </section>

    <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-sky-100">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="text-xs font-black uppercase tracking-widest text-cyan-700">Logbook Harian</p>
                <h3 class="mt-2 text-2xl font-black text-slate-950">Isi Aktivitas Harian PKPA</h3>
        <p class="mt-2 max-w-4xl text-sm text-slate-500">Agar seragam dengan format logbook harian, isilah tanggal, unit atau kegiatan, uraian aktivitas, dan kompetensi yang dicapai. Setelah dikirim, logbook diverifikasi Preseptor lalu divalidasi final oleh Pembimbing Dalam.</p>
            </div>
            <div class="rounded-2xl border border-sky-100 bg-sky-50 px-4 py-3 text-sm text-sky-900">
                <p class="font-black">Petunjuk Pengisian</p>
                <p class="mt-1">Logbook diisi setiap hari selama pelaksanaan PKPA sebagai bukti kegiatan yang telah dilakukan, diperiksa preseptor, dan dipantau pembimbing dalam.</p>
            </div>
        </div>
        <form method="POST" action="{{ route('student.pkpa-logbooks.store', $run) }}" class="mt-6 grid gap-5" id="logbook-form">
            @csrf
            <input type="hidden" name="id" id="logbook_id">
            <div class="grid gap-5 lg:grid-cols-2">
                <label class="grid gap-2">
                    <span class="text-sm font-black text-slate-700">Tanggal</span>
                    <input name="entry_date" id="logbook_entry_date" type="date" class="rounded-2xl border-slate-200 px-4 py-3 text-base" required>
                </label>
                <label class="grid gap-2">
                    <span class="text-sm font-black text-slate-700">Unit atau Kegiatan</span>
                    <input name="title" id="logbook_title" class="rounded-2xl border-slate-200 px-4 py-3 text-base" placeholder="Contoh: Pelayanan resep, konseling pasien, stock opname" required>
                </label>
            </div>

            <label class="grid gap-2">
                <span class="text-sm font-black text-slate-700">Uraian Aktivitas</span>
                <textarea name="activity_summary" id="logbook_activity_summary" rows="5" class="rounded-2xl border-slate-200 px-4 py-3 text-base" placeholder="Tuliskan aktivitas yang benar-benar dikerjakan hari ini secara runtut dan jelas." required></textarea>
            </label>

            <label class="grid gap-2">
                <span class="text-sm font-black text-slate-700">Kompetensi yang Dicapai</span>
                <textarea name="learning_outcomes" id="logbook_learning_outcomes" rows="4" class="rounded-2xl border-slate-200 px-4 py-3 text-base" placeholder="Tuliskan kompetensi, kemampuan, atau pembelajaran yang diperoleh dari aktivitas hari ini." required></textarea>
            </label>

            <label class="grid gap-2">
                <span class="text-sm font-black text-slate-700">Refleksi Mahasiswa</span>
                <textarea name="reflection" id="logbook_reflection" rows="4" class="rounded-2xl border-slate-200 px-4 py-3 text-base" placeholder="Tuliskan refleksi singkat: hal yang dipahami, kesulitan, dan perbaikan untuk praktik berikutnya." required></textarea>
            </label>

            <div class="grid gap-5 lg:grid-cols-2">
                <label class="grid gap-2">
                    <span class="text-sm font-black text-slate-700">Durasi Praktik dalam Menit</span>
                    <input name="practice_minutes" id="logbook_practice_minutes" type="number" min="0" class="rounded-2xl border-slate-200 px-4 py-3 text-base" placeholder="Contoh: 420">
                </label>
                <div class="grid gap-2">
                    <span class="text-sm font-black text-slate-700">Validasi</span>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="rounded-2xl bg-slate-50 px-4 py-3 ring-1 ring-slate-200">
                            <p class="text-xs font-black uppercase tracking-widest text-slate-500">Paraf Preseptor</p>
                            <p class="mt-2 text-sm text-slate-600">Akan tercatat setelah logbook dikirim dan diperiksa preseptor.</p>
                        </div>
                        <div class="rounded-2xl bg-slate-50 px-4 py-3 ring-1 ring-slate-200">
                            <p class="text-xs font-black uppercase tracking-widest text-slate-500">Paraf Dosen Pembimbing</p>
                            <p class="mt-2 text-sm text-slate-600">Akan tercatat setelah logbook yang lolos preseptor ditinjau pembimbing dalam.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap gap-3">
                <button id="logbook-submit-button" name="submission_action" value="submit" class="inline-flex min-h-14 flex-1 items-center justify-center rounded-2xl bg-cyan-700 px-5 py-3 text-base font-black text-white">Kirim ke Preseptor</button>
                <button id="logbook-draft-button" name="submission_action" value="draft" class="inline-flex min-h-14 items-center justify-center rounded-2xl border border-slate-200 bg-white px-5 py-3 text-sm font-black text-slate-700">Simpan sebagai Draf</button>
                <button type="button" id="logbook-reset-button" class="inline-flex min-h-14 items-center justify-center rounded-2xl border border-slate-200 px-5 py-3 text-base font-black text-slate-700">Form Baru</button>
            </div>
        </form>
        <div class="mt-8 rounded-2xl border border-slate-200 bg-slate-50/60 p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h4 class="text-lg font-black text-slate-950">Daftar Logbook</h4>
                    <p class="text-sm text-slate-500">Kiriman yang sedang diproses ditampilkan lebih dahulu. Draf tampil lebih ringan dan tetap dapat diedit sebelum dikirim.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="inline-flex rounded-full bg-white px-3 py-1 text-xs font-bold text-slate-600 ring-1 ring-slate-200">Urut tanggal</span>
                    <span class="inline-flex rounded-full bg-white px-3 py-1 text-xs font-bold text-slate-600 ring-1 ring-slate-200">{{ $logbookList->count() }} entri</span>
                </div>
            </div>
            <div class="mt-4 space-y-3">
                @forelse($logbookList as $entry)
                    <details class="group rounded-2xl {{ $entry->status === 'draft' ? 'border border-dashed border-slate-300 bg-slate-50 p-3' : 'bg-white p-4 ring-1 ring-slate-200' }}">
                        <summary class="flex cursor-pointer list-none flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="text-base font-black text-slate-950">{{ $entry->title }}</p>
                                    <span class="inline-flex rounded-full bg-slate-50 px-3 py-1 text-xs font-bold text-slate-600 ring-1 ring-slate-200">{{ $entry->entry_date?->format('d M Y') }}</span>
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-bold ring-1 {{ $entry->status === 'draft' ? 'bg-slate-100 text-slate-600 ring-slate-200' : ($entry->status === 'submitted' ? 'bg-cyan-50 text-cyan-700 ring-cyan-200' : ($entry->status === 'field_approved' ? 'bg-sky-50 text-sky-700 ring-sky-200' : ($entry->status === 'internal_approved' ? 'bg-emerald-50 text-emerald-700 ring-emerald-200' : 'bg-amber-50 text-amber-700 ring-amber-200'))) }}">{{ $logbookStatuses[$entry->status] ?? str($entry->status)->replace('_', ' ')->headline() }}</span>
                                </div>
                                <p class="mt-1 line-clamp-2 max-w-3xl text-sm text-slate-500">{{ $entry->activity_summary }}</p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                @if(in_array($entry->status, $editableLogbookStatuses, true))
                                    <button type="button" class="logbook-edit-button inline-flex min-h-10 items-center justify-center rounded-xl border border-slate-200 px-4 py-2 text-sm font-bold text-slate-700"
                                        data-id="{{ $entry->id }}"
                                        data-entry-date="{{ $entry->entry_date?->format('Y-m-d') }}"
                                        data-title="{{ e($entry->title) }}"
                                        data-activity-summary="{{ e($entry->activity_summary) }}"
                                        data-learning-outcomes="{{ e($entry->learning_outcomes) }}"
                                        data-reflection="{{ e($entry->reflection) }}"
                                        data-practice-minutes="{{ $entry->practice_minutes ?? '' }}">Edit Draft</button>
                                    <form method="POST" action="{{ route('student.pkpa-logbooks.destroy', $entry) }}" onsubmit="return confirm('Hapus logbook draft ini?');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="inline-flex min-h-10 items-center justify-center rounded-xl border border-rose-200 px-4 py-2 text-sm font-bold text-rose-700">Hapus</button>
                                    </form>
                                @endif
                                <span class="inline-flex min-h-10 items-center justify-center rounded-xl border border-cyan-200 px-4 py-2 text-sm font-bold text-cyan-700 group-open:hidden">Lihat Detail</span>
                            </div>
                        </summary>
                        <div class="mt-4 grid gap-4 border-t border-dashed border-slate-200 pt-4 xl:grid-cols-[minmax(0,1fr)_320px]">
                            <div class="rounded-xl {{ $entry->status === 'draft' ? 'bg-amber-50 text-amber-900' : ($entry->status === 'submitted' ? 'bg-cyan-50 text-cyan-900' : ($entry->status === 'field_approved' ? 'bg-sky-50 text-sky-900' : 'bg-emerald-50 text-emerald-900')) }} px-4 py-3 xl:col-span-2">
                                <p class="font-black">{{ $entry->status === 'draft' ? 'Draf belum dikirim' : ($entry->status === 'submitted' ? 'Sudah terkirim ke Preseptor' : ($entry->status === 'field_approved' ? 'Sudah disetujui Preseptor, menunggu Pembimbing Dalam' : ($entry->status === 'internal_approved' ? 'Tervalidasi final oleh Pembimbing Dalam' : 'Memerlukan tindak lanjut mahasiswa'))) }}</p>
                                <p class="mt-1 text-sm">{{ $entry->status === 'draft' ? 'Edit, simpan bukti Google Drive, lalu kirim ketika isi sudah lengkap.' : ($entry->submitted_at ? 'Dikirim pada '.$entry->submitted_at->format('d M Y H:i') : 'Status dicatat oleh sistem.') }}</p>
                            </div>
                            <div class="space-y-4">
                                <div class="grid gap-3 lg:grid-cols-2">
                                    <div class="rounded-xl bg-slate-50 px-4 py-3">
                                        <p class="text-xs font-black uppercase tracking-widest text-slate-500">Unit atau Kegiatan</p>
                                        <p class="mt-2 text-sm text-slate-800">{{ $entry->title }}</p>
                                    </div>
                                    <div class="rounded-xl bg-slate-50 px-4 py-3">
                                        <p class="text-xs font-black uppercase tracking-widest text-slate-500">Durasi Praktik</p>
                                        <p class="mt-2 text-sm text-slate-800">{{ $entry->practice_minutes ? $entry->practice_minutes.' menit' : '-' }}</p>
                                    </div>
                                </div>
                                <div class="rounded-xl bg-slate-50 px-4 py-3">
                                    <p class="text-xs font-black uppercase tracking-widest text-slate-500">Uraian Aktivitas</p>
                                    <p class="mt-2 text-sm leading-6 text-slate-800">{{ $entry->activity_summary }}</p>
                                </div>
                                <div class="grid gap-3 lg:grid-cols-2">
                                    <div class="rounded-xl bg-slate-50 px-4 py-3">
                                        <p class="text-xs font-black uppercase tracking-widest text-slate-500">Kompetensi yang Dicapai</p>
                                        <p class="mt-2 text-sm leading-6 text-slate-800">{{ $entry->learning_outcomes }}</p>
                                    </div>
                                    <div class="rounded-xl bg-slate-50 px-4 py-3">
                                        <p class="text-xs font-black uppercase tracking-widest text-slate-500">Refleksi Mahasiswa</p>
                                        <p class="mt-2 text-sm leading-6 text-slate-800">{{ $entry->reflection }}</p>
                                    </div>
                                </div>
                                <div class="rounded-xl bg-slate-50 px-4 py-3">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <p class="text-xs font-black uppercase tracking-widest text-slate-500">Bukti Kegiatan</p>
                                        <span class="text-xs font-semibold text-slate-500">{{ $entry->attachments->count() }} bukti</span>
                                    </div>
                                    <div class="mt-3 space-y-3">
                                        @forelse($entry->attachments as $attachment)
                                            <div class="rounded-xl border border-slate-200 bg-white px-4 py-3">
                                                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                                                    <div class="min-w-0">
                                                        <p class="text-sm font-bold text-slate-900">{{ $attachment->displayLabel() }}</p>
                                                        <p class="mt-1 text-xs text-slate-500">{{ $attachment->isExternalLink() ? 'Tautan eksternal / Google Drive' : 'File unggahan / '.$attachment->humanFileSize() }}</p>
                                                    </div>
                                                    <div class="flex flex-wrap gap-2">
                                                        @if($attachment->isExternalLink())
                                                            <a href="{{ $attachment->previewUrl() }}" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-10 items-center justify-center rounded-xl border border-cyan-200 bg-white px-4 py-2 text-sm font-bold text-cyan-700">Preview Link</a>
                                                            <a href="{{ $attachment->externalDownloadUrl() }}" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-10 items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-700">Buka Drive</a>
                                                        @else
                                                            <a href="{{ route('student.pkpa-logbooks.attachments.download', $attachment) }}" class="inline-flex min-h-10 items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-700">Unduh File</a>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        @empty
                                            <div class="rounded-xl border border-dashed border-slate-200 px-4 py-4 text-sm text-slate-500">Belum ada bukti yang dilampirkan.</div>
                                        @endforelse
                                    </div>
                                </div>
                            </div>
                            <div class="space-y-3">
                                @if(in_array($entry->status, $editableLogbookStatuses, true))
                                    <form method="POST" action="{{ route('student.pkpa-logbooks.attachment-links.store', $entry) }}" class="space-y-3 rounded-2xl border border-cyan-200 bg-cyan-50/60 p-4">
                                        @csrf
                                        <div>
                                            <p class="text-sm font-black text-slate-900">Tautan Bukti Google Drive</p>
                                            <p class="mt-1 text-xs leading-5 text-slate-500">Simpan tautan yang bisa dipreview agar reviewer membuka bukti yang sama.</p>
                                        </div>
                                        <input name="link_label" class="block w-full rounded-xl border-slate-200 px-4 py-3 text-sm" placeholder="Judul bukti, misalnya Foto pelayanan resep">
                                        <input name="external_url" class="block w-full rounded-xl border-slate-200 px-4 py-3 text-sm" placeholder="https://drive.google.com/file/d/.../view">
                                        <button class="inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-cyan-700 px-4 py-2 text-sm font-black text-white">Simpan Link Drive</button>
                                    </form>
                                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-xs leading-5 text-slate-600">
                                        Draft ini masih bisa diubah, disimpan ulang, atau dihapus sebelum dikirim ke preseptor dan pembimbing dalam.
                                    </div>
                                    <form method="POST" action="{{ route('student.pkpa-logbooks.submit', $entry) }}">@csrf<button class="inline-flex min-h-12 w-full items-center justify-center rounded-2xl bg-cyan-700 px-4 py-3 text-sm font-black text-white">Kirim ke Preseptor</button></form>
                                @else
                                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-xs leading-5 text-slate-600">
                                        Logbook ini sudah masuk alur review sehingga tidak bisa diedit atau dihapus langsung dari sisi mahasiswa.
                                    </div>
                                @endif
                            </div>
                        </div>
                    </details>
                @empty
                    <div class="rounded-xl border border-dashed border-slate-200 px-4 py-6 text-sm text-slate-500">Belum ada logbook tersimpan.</div>
                @endforelse
            </div>
        </div>
    </section>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const attendanceForm = document.getElementById('attendance-form');
    const attendanceRecordId = document.getElementById('attendance_record_id');
    const attendanceDate = document.getElementById('attendance_date');
    const attendanceType = document.getElementById('attendance_type');
    const attendanceCheckIn = document.getElementById('check_in_time');
    const attendanceCheckOut = document.getElementById('check_out_time');
    const attendanceNotes = document.getElementById('student_notes');
    const attendanceSubmitButton = document.getElementById('attendance-submit-button');
    const attendanceDraftButton = document.getElementById('attendance-draft-button');
    const attendanceResetButton = document.getElementById('attendance-reset-button');

    document.querySelectorAll('.attendance-edit-button').forEach((button) => {
        button.addEventListener('click', () => {
            attendanceRecordId.value = button.dataset.id || '';
            attendanceDate.value = button.dataset.date || '';
            attendanceType.value = button.dataset.type || 'present';
            attendanceCheckIn.value = button.dataset.checkIn || '';
            attendanceCheckOut.value = button.dataset.checkOut || '';
            attendanceNotes.value = button.dataset.notes || '';
            attendanceSubmitButton.textContent = 'Perbarui dan Kirim ke Preseptor';
            attendanceDraftButton.textContent = 'Simpan Perubahan Draf';
            attendanceForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });

    attendanceResetButton?.addEventListener('click', () => {
        attendanceForm.reset();
        attendanceRecordId.value = '';
        attendanceSubmitButton.textContent = 'Kirim ke Preseptor';
        attendanceDraftButton.textContent = 'Simpan sebagai Draf';
    });

    const logbookForm = document.getElementById('logbook-form');
    const logbookId = document.getElementById('logbook_id');
    const logbookEntryDate = document.getElementById('logbook_entry_date');
    const logbookTitle = document.getElementById('logbook_title');
    const logbookActivitySummary = document.getElementById('logbook_activity_summary');
    const logbookLearningOutcomes = document.getElementById('logbook_learning_outcomes');
    const logbookReflection = document.getElementById('logbook_reflection');
    const logbookPracticeMinutes = document.getElementById('logbook_practice_minutes');
    const logbookSubmitButton = document.getElementById('logbook-submit-button');
    const logbookDraftButton = document.getElementById('logbook-draft-button');
    const logbookResetButton = document.getElementById('logbook-reset-button');

    document.querySelectorAll('.logbook-edit-button').forEach((button) => {
        button.addEventListener('click', () => {
            logbookId.value = button.dataset.id || '';
            logbookEntryDate.value = button.dataset.entryDate || '';
            logbookTitle.value = button.dataset.title || '';
            logbookActivitySummary.value = button.dataset.activitySummary || '';
            logbookLearningOutcomes.value = button.dataset.learningOutcomes || '';
            logbookReflection.value = button.dataset.reflection || '';
            logbookPracticeMinutes.value = button.dataset.practiceMinutes || '';
            logbookSubmitButton.textContent = 'Perbarui dan Kirim ke Preseptor';
            logbookDraftButton.textContent = 'Simpan Perubahan Draf';
            logbookForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });

    logbookResetButton?.addEventListener('click', () => {
        logbookForm.reset();
        logbookId.value = '';
        logbookSubmitButton.textContent = 'Kirim ke Preseptor';
        logbookDraftButton.textContent = 'Simpan sebagai Draf';
    });
});
</script>
@endsection
