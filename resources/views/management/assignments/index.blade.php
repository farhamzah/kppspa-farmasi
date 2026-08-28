@extends('layouts.app')
@section('title','Penempatan PKPA - '.config('app.name'))
@section('page_title','Penempatan PKPA')
@section('content')
@php
    $reportQuery = array_filter([
        'q' => $filters['q'] ?? null,
        'place' => $filters['place'] ?? null,
        'period' => $filters['period'] ?? null,
        'status' => $filters['status'] ?? null,
        'internal_supervisor' => $filters['internal_supervisor'] ?? null,
        'field_supervisor' => $filters['field_supervisor'] ?? null,
        'sort' => $filters['sort'] ?? null,
    ], fn ($value) => filled($value));
@endphp
<div class="space-y-5">
<div class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
    <div class="grid gap-2 sm:grid-cols-2 lg:flex lg:flex-wrap">
        <a href="{{ route('management.kp-assignments.report.preview', $reportQuery) }}" target="_blank" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm">Pratinjau</a>
        <a href="{{ route('management.kp-assignments.report.preview', $reportQuery + ['print' => 1]) }}" target="_blank" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm">Print</a>
        <a href="{{ route('management.kp-assignments.report.download', ['format' => 'word'] + $reportQuery) }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm">Word</a>
        <a href="{{ route('management.kp-assignments.report.download', ['format' => 'excel'] + $reportQuery) }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm">Excel</a>
        <a href="{{ route('management.kp-assignments.report.download', ['format' => 'pdf'] + $reportQuery) }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm">PDF</a>
    </div>
    <a href="{{ route('management.kp-assignments.create') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white">Buat Penempatan</a>
</div>
<section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
    <form method="GET" class="space-y-3">
        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-[1.25fr_1fr_220px_220px]">
            <input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Cari nama/NIM mahasiswa" class="min-h-11 rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <input name="place" value="{{ $filters['place'] ?? '' }}" placeholder="Cari tempat PKPA" class="min-h-11 rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <select name="period" class="min-h-11 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Semua Periode</option>
                @foreach($periods as $period)
                    <option value="{{ $period->id }}" @selected(($filters['period'] ?? '') == $period->id)>{{ $period->name }}</option>
                @endforeach
            </select>
            <select name="status" class="min-h-11 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Semua Status</option>
                @foreach($statusOptions as $value=>$label)
                    <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-[1fr_1fr_220px_160px_96px]">
            <input name="internal_supervisor" value="{{ $filters['internal_supervisor'] ?? '' }}" placeholder="Cari pembimbing dalam" class="min-h-11 rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <input name="field_supervisor" value="{{ $filters['field_supervisor'] ?? '' }}" placeholder="Cari pembimbing lapangan" class="min-h-11 rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <select name="sort" class="min-h-11 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                @foreach($sortOptions as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['sort'] ?? 'latest') === $value)>Urut: {{ $label }}</option>
                @endforeach
            </select>
            <button class="min-h-11 rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Filter</button>
            <a href="{{ route('management.pkpa-assignments.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Reset</a>
        </div>
    </form>
</section>
<section class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200"><div class="overflow-x-auto"><table class="min-w-full divide-y divide-slate-200 text-sm"><thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500"><tr><th class="px-4 py-3">Mahasiswa</th><th class="px-4 py-3">Periode</th><th class="px-4 py-3">Tempat</th><th class="px-4 py-3">Pembimbing Dalam</th><th class="px-4 py-3">Pembimbing Lapangan</th><th class="px-4 py-3">Status</th><th class="px-4 py-3 text-right">Aksi</th></tr></thead><tbody class="divide-y divide-slate-100">@forelse($assignments as $assignment)@php($studentDisplay = app(\App\Services\KpMasterDataReadService::class)->getStudentDisplayData($assignment->student))@php($internalSupervisorDisplay = $assignment->internalSupervisor ? app(\App\Services\KpMasterDataReadService::class)->getLecturerDisplayData($assignment->internalSupervisor) : null)<tr><td class="px-4 py-4"><div class="font-semibold">{{ $studentDisplay->name }}</div><div class="text-xs text-slate-500">{{ $studentDisplay->studentNumber ?: '-' }}</div></td><td class="px-4 py-4">{{ $assignment->period->name }}</td><td class="px-4 py-4">{{ $assignment->place->name }}</td><td class="px-4 py-4">{{ $internalSupervisorDisplay?->name ?? 'Belum ada' }}</td><td class="px-4 py-4">{{ $assignment->fieldSupervisor ? field_supervisor_display_name($assignment->fieldSupervisor) : 'Belum ada' }}</td><td class="px-4 py-4"><span class="rounded-full {{ $assignment->statusBadgeClass() }} px-2 py-1 text-xs font-semibold">{{ $assignment->statusLabel() }}</span></td><td class="px-4 py-4 text-right"><a href="{{ route('management.kp-assignments.show', ['kp_assignment' => $assignment, 'return_url' => request()->fullUrl()]) }}" class="rounded-lg border border-teal-200 px-3 py-1.5 text-xs font-semibold text-teal-700">Detail</a></td></tr>@empty<tr><td colspan="7" class="px-4 py-10 text-center text-slate-500">Belum ada penempatan PKPA.</td></tr>@endforelse</tbody></table></div><div class="border-t px-4 py-3">{{ $assignments->links() }}</div></section>
</div>
@endsection
