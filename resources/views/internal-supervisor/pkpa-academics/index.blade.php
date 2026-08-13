@extends('layouts.app')
@section('title', 'Akademik PKPA')
@section('page_title', 'Akademik PKPA')
@section('content')
<div class="grid gap-4 lg:grid-cols-2">
@forelse($runs as $run)
<div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-sky-100"><p class="text-xs font-black uppercase tracking-widest text-cyan-700">{{ $run->student_core_user_id }}</p><h2 class="text-xl font-black">{{ $run->practiceSite?->name }}</h2><p class="mt-2 text-sm text-slate-500">Laporan: {{ $run->rotationReport?->status ?? '-' }} / Bimbingan {{ $run->guidanceSessions->count() }}</p><form method="POST" action="{{ route('internal-supervisor.pkpa-guidance.store', $run) }}" class="mt-3 grid gap-2">@csrf<input name="topic" class="rounded-xl border-slate-200 text-sm" placeholder="Topik bimbingan" required><select name="guidance_type" class="rounded-xl border-slate-200 text-sm"><option value="comment_only">Komentar</option><option value="meeting">Pertemuan</option><option value="document_review">Pemeriksaan Dokumen</option><option value="online_consultation">Konsultasi Daring</option><option value="other">Lainnya</option></select><input name="guidance_date" type="date" class="rounded-xl border-slate-200 text-sm" value="{{ now()->toDateString() }}" required><textarea name="supervisor_notes" class="rounded-xl border-slate-200 text-sm" placeholder="Catatan pembimbing"></textarea><button class="rounded-xl bg-cyan-700 px-3 py-2 text-xs font-black text-white">Catat Bimbingan</button></form>@if($run->rotationReport && in_array($run->rotationReport->status, ['submitted','internal_review']))<form method="POST" action="{{ route('internal-supervisor.pkpa-rotation-reports.review', $run->rotationReport) }}" class="mt-3">@csrf<textarea name="comments" class="w-full rounded-xl border-slate-200 text-sm" placeholder="Catatan pemeriksaan"></textarea><button name="action" value="approved" class="mt-2 rounded-lg bg-emerald-600 px-3 py-1 text-xs font-black text-white">Setujui</button><button name="action" value="revision_requested" class="mt-2 rounded-lg border border-amber-200 px-3 py-1 text-xs font-black text-amber-700">Revisi</button></form>@endif</div>
@empty
<div class="rounded-2xl bg-white p-6 text-sm text-slate-500 shadow-sm ring-1 ring-sky-100">Belum ada monitoring akademik.</div>
@endforelse
</div>
@endsection
