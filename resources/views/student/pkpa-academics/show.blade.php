@extends('layouts.app')
@section('title', 'Detail Akademik Rotasi')
@section('page_title', 'Detail Akademik Rotasi')
@section('content')
@php
    $readiness = $run->academicReadinessReviews->sortByDesc('reviewed_at')->first();
    $progress = optional($run->progressSnapshots->first())->progress_percentage ?? 0;
    $competencyStatusLabels = [
        'draft' => 'Draf',
        'in_progress' => 'Sedang Dikerjakan',
        'submitted' => 'Menunggu Verifikasi',
        'verified' => 'Terverifikasi',
        'revision_requested' => 'Perlu Revisi',
        'rejected' => 'Ditolak',
    ];
    $taskStatusLabels = [
        'draft' => 'Draf',
        'submitted' => 'Terkirim',
        'approved' => 'Disetujui',
        'revision_requested' => 'Perlu Revisi',
        'rejected' => 'Ditolak',
        'marked_reviewed' => 'Sudah Ditinjau',
    ];
    $reportStatusLabels = [
        'draft' => 'Draf',
        'submitted' => 'Terkirim',
        'internal_review' => 'Sedang Ditinjau',
        'approved' => 'Disetujui',
        'revision_requested' => 'Perlu Revisi',
        'rejected' => 'Ditolak',
        'confirmed_by_field' => 'Dikonfirmasi Pembimbing Lapangan',
    ];
@endphp
<div class="space-y-5">
@if(session('status'))<div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-700">{{ session('status') }}</div>@endif
@if($errors->any())<div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-bold text-rose-700">{{ $errors->first() }}</div>@endif
<section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-sky-100"><p class="text-xs font-black uppercase tracking-widest text-cyan-700">{{ $run->practiceDomain?->name }}</p><h2 class="mt-1 text-2xl font-black">{{ $run->practiceSite?->name }}</h2><p class="mt-2 text-sm text-slate-500">Status kesiapan terakhir: {{ match($readiness?->status) {
    'ready_for_assessment' => 'Siap untuk penilaian',
    'assessment_blocked' => 'Masih ada yang perlu dilengkapi',
    null => 'Belum dicek',
    default => str($readiness?->status)->replace('_', ' ')->headline(),
} }}</p><div class="mt-4 grid gap-3 md:grid-cols-3"><div class="rounded-xl bg-slate-50 px-4 py-3"><p class="text-xs font-black uppercase tracking-widest text-slate-500">Kemajuan</p><p class="mt-1 font-black text-slate-950">{{ $progress }}%</p></div><div class="rounded-xl bg-slate-50 px-4 py-3"><p class="text-xs font-black uppercase tracking-widest text-slate-500">Kompetensi Wajib</p><p class="mt-1 font-black text-slate-950">{{ $run->competencyRecords->where('status','verified')->count() }}/{{ $run->competencyRecords->where('is_required_snapshot', true)->count() }}</p></div><div class="rounded-xl bg-slate-50 px-4 py-3"><p class="text-xs font-black uppercase tracking-widest text-slate-500">Tugas Wajib</p><p class="mt-1 font-black text-slate-950">{{ $run->specialTasks->where('status','approved')->count() }}/{{ $run->specialTasks->where('is_required_snapshot', true)->count() }}</p></div></div></section>
<div class="grid gap-5 xl:grid-cols-2">
<section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-sky-100"><h3 class="text-lg font-black">Kompetensi</h3><div class="mt-4 space-y-3">@foreach($run->competencyRecords as $record)<div class="rounded-xl bg-slate-50 p-3"><div class="flex flex-wrap items-start justify-between gap-2"><div><p class="font-black">{{ $record->competency_title_snapshot }}</p><p class="text-xs text-slate-500">{{ $competencyStatusLabels[$record->status] ?? str($record->status)->replace('_', ' ')->headline() }} / bukti {{ $record->evidences->where('status','active')->count() }}/{{ $record->minimum_evidence_count_snapshot }}</p></div></div><form method="POST" action="{{ route('student.pkpa-competencies.progress', $record) }}" class="mt-2">@csrf<textarea name="student_notes" class="w-full rounded-xl border-slate-200 text-sm" placeholder="Catatan capaian"></textarea><button class="mt-2 rounded-lg border border-cyan-200 px-3 py-1 text-xs font-black text-cyan-700">Tandai Kemajuan</button></form><form method="POST" enctype="multipart/form-data" action="{{ route('student.pkpa-competencies.evidences.store', $record) }}" class="mt-2 grid gap-2">@csrf<select name="evidence_type" class="rounded-xl border-slate-200 text-sm"><option value="text_note">Catatan</option><option value="file">Berkas</option><option value="external_reference">Tautan</option></select><input name="title" class="rounded-xl border-slate-200 text-sm" placeholder="Judul bukti"><input name="external_reference_url" class="rounded-xl border-slate-200 text-sm" placeholder="https://..."><input name="file" type="file" class="text-xs"><button class="rounded-lg border border-slate-200 px-3 py-1 text-xs font-black">Tambah Bukti</button></form><form method="POST" action="{{ route('student.pkpa-competencies.submit', $record) }}" class="mt-2">@csrf<button class="rounded-lg bg-slate-900 px-3 py-1 text-xs font-black text-white">Kirim Kompetensi</button></form></div>@endforeach</div></section>
<section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-sky-100"><h3 class="text-lg font-black">Tugas Khusus</h3><div class="mt-4 space-y-3">@foreach($run->specialTasks as $task)<form method="POST" enctype="multipart/form-data" action="{{ route('student.pkpa-special-tasks.submit', $task) }}" class="rounded-xl bg-slate-50 p-3">@csrf<p class="font-black">{{ $task->task_title_snapshot }}</p><p class="text-xs text-slate-500">{{ $taskStatusLabels[$task->status] ?? str($task->status)->replace('_', ' ')->headline() }} / tenggat {{ optional($task->due_date)->format('d M Y') ?: '-' }}</p><input name="title" class="mt-2 w-full rounded-xl border-slate-200 text-sm" placeholder="Judul submission"><textarea name="submission_notes" class="mt-2 w-full rounded-xl border-slate-200 text-sm" placeholder="Catatan submission"></textarea><input name="file" type="file" class="mt-2 text-xs"><button class="mt-2 rounded-lg bg-cyan-700 px-3 py-1 text-xs font-black text-white">Kirim Tugas</button></form>@endforeach</div></section>
</div>
<section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-sky-100"><h3 class="text-lg font-black">Laporan Rotasi</h3>@if(!$run->rotationReport)<form method="POST" action="{{ route('student.pkpa-rotation-reports.store', $run) }}" class="mt-3">@csrf<button class="rounded-xl bg-cyan-700 px-4 py-2 text-sm font-black text-white">Buat Draf Laporan</button></form>@else<form method="POST" enctype="multipart/form-data" action="{{ route('student.pkpa-rotation-reports.versions.store', $run->rotationReport) }}" class="mt-3 grid gap-2">@csrf<p class="text-sm text-slate-500">Status: {{ $reportStatusLabels[$run->rotationReport->status] ?? str($run->rotationReport->status)->replace('_', ' ')->headline() }}</p><input name="file" type="file" class="text-sm" required><input name="change_summary" class="rounded-xl border-slate-200 text-sm" placeholder="Ringkasan perubahan"><button class="rounded-xl border border-cyan-200 px-4 py-2 text-sm font-black text-cyan-700">Unggah Versi</button></form><form method="POST" action="{{ route('student.pkpa-rotation-reports.submit', $run->rotationReport) }}" class="mt-2">@csrf<button class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-black text-white">Kirim Laporan</button></form><div class="mt-3 space-y-2">@foreach($run->rotationReport->versions as $version)<a href="{{ route('student.pkpa-rotation-reports.versions.download', $version) }}" class="block rounded-xl bg-slate-50 p-3 text-sm font-bold text-cyan-700">Versi {{ $version->version_number }} - {{ $version->original_filename }}</a>@endforeach</div>@endif</section>
<section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-sky-100"><h3 class="text-lg font-black">Bimbingan</h3><div class="mt-3 space-y-2">@foreach($run->guidanceSessions as $session)<div class="rounded-xl bg-slate-50 p-3 text-sm"><p class="font-black">{{ $session->topic }} / {{ $session->guidance_date?->format('d M Y') }}</p><p class="text-slate-500">{{ $session->supervisor_notes }}</p>@if(!$session->student_acknowledged_at)<form method="POST" action="{{ route('student.pkpa-guidance.acknowledge', $session) }}" class="mt-2">@csrf<button class="rounded-lg border border-cyan-200 px-3 py-1 text-xs font-black text-cyan-700">Tandai Dibaca</button></form>@endif</div>@endforeach</div></section>
</div>
@endsection
