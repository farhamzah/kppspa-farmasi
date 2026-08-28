@extends('layouts.app')
@section('title','Log Ujian - '.config('app.name'))
@section('page_title','Log Ujian')
@section('content')
<div class="space-y-5">
<x-ui.card><form method="GET" class="grid gap-3 md:grid-cols-[220px_auto]"><select name="period" class="rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="">Semua Periode</option>@foreach($periods as $period)<option value="{{ $period->id }}" @selected(($filters['period'] ?? '') == $period->id)>{{ $period->name }}</option>@endforeach</select><button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Filter</button></form></x-ui.card>
<section class="si-table-wrap"><div class="overflow-x-auto"><table><thead><tr><th>Waktu</th><th>User</th><th>Mahasiswa</th><th>Action</th><th>Status</th><th>Catatan</th></tr></thead><tbody>@forelse($logs as $log)<tr><td>{{ $log->created_at->format('d M Y H:i') }}</td><td>{{ $log->user?->name ?? '-' }}</td><td>{{ $log->request?->assignment?->student?->user?->name ?? '-' }}</td><td>{{ str_replace('_',' ', $log->action) }}</td><td>{{ $log->old_status ?: '-' }} -> {{ $log->new_status ?: '-' }}</td><td>{{ $log->note ?: '-' }}</td></tr>@empty<tr><td colspan="6" class="py-10 text-center text-slate-500">Belum ada log ujian.</td></tr>@endforelse</tbody></table></div><div class="border-t px-4 py-3">{{ $logs->links() }}</div></section>
</div>
@endsection
