@extends('layouts.app')
@section('title', 'Akademik PKPA')
@section('page_title', 'Akademik PKPA')
@section('content')
<div class="grid gap-4 lg:grid-cols-2">
@forelse($runs as $run)
<div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-sky-100"><p class="text-xs font-black uppercase tracking-widest text-cyan-700">{{ $run->student_core_user_id }}</p><h2 class="text-xl font-black">{{ $run->practiceSite?->name }}</h2><p class="mt-2 text-sm text-slate-500">{{ $run->competencyRecords->where('status','submitted')->count() }} kompetensi menunggu / {{ $run->specialTasks->where('status','submitted')->count() }} tugas menunggu</p><div class="mt-4 space-y-2">@foreach($run->competencyRecords->where('status','submitted') as $record)<form method="POST" action="{{ route('field-supervisor.pkpa-competencies.review', $record) }}" class="rounded-xl bg-slate-50 p-3">@csrf<p class="font-bold">{{ $record->competency_title_snapshot }}</p><textarea name="comments" class="mt-2 w-full rounded-xl border-slate-200 text-sm" placeholder="Catatan"></textarea><button name="action" value="verified" class="mt-2 rounded-lg bg-emerald-600 px-3 py-1 text-xs font-black text-white">Verifikasi</button><button name="action" value="revision_requested" class="mt-2 rounded-lg border border-amber-200 px-3 py-1 text-xs font-black text-amber-700">Revisi</button></form>@endforeach</div>@if($run->rotationReport && $run->rotationReport->status === 'submitted')<form method="POST" action="{{ route('field-supervisor.pkpa-rotation-reports.confirm', $run->rotationReport) }}" class="mt-3">@csrf<button class="rounded-xl border border-cyan-200 px-3 py-2 text-xs font-black text-cyan-700">Konfirmasi Laporan</button></form>@endif</div>
@empty
<div class="rounded-2xl bg-white p-6 text-sm text-slate-500 shadow-sm ring-1 ring-sky-100">Belum ada queue akademik.</div>
@endforelse
</div>
@endsection
