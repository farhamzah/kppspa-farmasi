@extends('layouts.app')
@section('title', 'Kapasitas Tempat PKPA - '.config('app.name'))
@section('page_title', 'Kapasitas Tempat PKPA')
@section('content')
@php
    $reportQuery = array_filter($filters, fn ($value) => filled($value));
    $groups = $quotas->getCollection()->groupBy(fn ($quota) => $quota->place?->typeLabel() ?? 'Wahana belum ditentukan');
@endphp
<div class="space-y-5">
    <section class="rounded-xl border border-cyan-100 bg-cyan-50 px-5 py-4 text-sm text-cyan-950">
        <h2 class="font-black">Kuota operasional per program/periode</h2>
        <p class="mt-1">Terisi dihitung dari penempatan resmi. Sisa adalah kapasitas yang belum ditempati mahasiswa.</p>
    </section>

    <section class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-end">
            <form method="GET" class="grid flex-1 gap-3 md:grid-cols-2 xl:grid-cols-[1.35fr_220px_190px_170px_auto]">
                <div><label class="text-xs font-bold uppercase text-slate-500">Cari tempat</label><input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Nama tempat praktik" class="mt-1 min-h-11 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
                <div><label class="text-xs font-bold uppercase text-slate-500">Program/Periode</label><select name="period" class="mt-1 min-h-11 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="">Semua periode</option>@foreach($periods as $period)<option value="{{ $period->id }}" @selected(($filters['period'] ?? '') == $period->id)>{{ $period->name }}</option>@endforeach</select></div>
                <div><label class="text-xs font-bold uppercase text-slate-500">Wahana</label><select name="type" class="mt-1 min-h-11 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="">Semua wahana</option>@foreach(\App\Models\KpPlace::TYPES as $type)<option value="{{ $type }}" @selected(($filters['type'] ?? '') === $type)>{{ (new \App\Models\KpPlace(['type' => $type]))->typeLabel() }}</option>@endforeach</select></div>
                <div><label class="text-xs font-bold uppercase text-slate-500">Status</label><select name="status" class="mt-1 min-h-11 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="">Semua status</option><option value="open" @selected(($filters['status'] ?? '') === 'open')>Dibuka</option><option value="closed" @selected(($filters['status'] ?? '') === 'closed')>Ditutup</option></select></div>
                <button class="min-h-11 self-end rounded-lg bg-slate-950 px-4 py-2 text-sm font-black text-white">Terapkan</button>
            </form>
            <a href="{{ route('management.kp-place-quotas.create') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-cyan-700 px-4 py-2 text-sm font-black text-white">Tambah Kapasitas</a>
        </div>
        <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-4">
            <span class="mr-1 text-xs font-bold uppercase text-slate-500">Laporan sesuai filter</span>
            <a target="_blank" href="{{ route('management.pkpa-capacity-reports.preview', ['type' => 'quotas'] + $reportQuery) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-bold text-slate-700">Pratinjau</a>
            <a target="_blank" href="{{ route('management.pkpa-capacity-reports.preview', ['type' => 'quotas', 'print' => 1] + $reportQuery) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-bold text-slate-700">Print</a>
            <a href="{{ route('management.pkpa-capacity-reports.download', ['type' => 'quotas', 'format' => 'pdf'] + $reportQuery) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-bold text-slate-700">PDF</a>
            <a href="{{ route('management.pkpa-capacity-reports.download', ['type' => 'quotas', 'format' => 'xlsx'] + $reportQuery) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-bold text-slate-700">Excel</a>
            <a href="{{ route('management.pkpa-capacity-reports.download', ['type' => 'quotas', 'format' => 'word'] + $reportQuery) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-bold text-slate-700">Word</a>
        </div>
    </section>

    @forelse($groups as $domainName => $items)
        <section class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
            <header class="flex items-center justify-between border-b border-cyan-100 bg-cyan-50 px-5 py-4">
                <div><p class="text-xs font-black uppercase text-cyan-700">Wahana PKPA</p><h2 class="mt-1 text-xl font-black text-slate-950">{{ $domainName }}</h2></div>
                <div class="flex gap-2 text-xs font-black"><span class="rounded-full bg-white px-3 py-1 text-cyan-800 ring-1 ring-cyan-200">{{ $items->count() }} tempat</span><span class="rounded-full bg-white px-3 py-1 text-slate-700 ring-1 ring-slate-200">Kapasitas {{ $items->sum('quota') }}</span></div>
            </header>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-bold uppercase text-slate-500"><tr><th class="px-4 py-3">Program/Periode</th><th class="px-4 py-3">Tempat Praktik</th><th class="px-4 py-3 text-center">Kapasitas</th><th class="px-4 py-3 text-center">Terisi</th><th class="px-4 py-3 text-center">Sisa</th><th class="px-4 py-3">Status</th><th class="px-4 py-3 text-right">Aksi</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($items as $quota)
                            <tr>
                                <td class="px-4 py-4">{{ $quota->period?->name }}</td>
                                <td class="px-4 py-4"><div class="font-bold text-slate-950">{{ $quota->place?->name }}</div><div class="text-xs text-slate-500">{{ $domainName }}</div></td>
                                <td class="px-4 py-4 text-center font-black">{{ $quota->quota }}</td>
                                <td class="px-4 py-4 text-center font-black">{{ $quota->filledCount() }}</td>
                                <td class="px-4 py-4 text-center font-black {{ $quota->remainingQuota() === 0 ? 'text-emerald-700' : 'text-amber-700' }}">{{ $quota->remainingQuota() }}</td>
                                <td class="px-4 py-4"><span class="rounded-full {{ $quota->statusBadgeClass() }} px-2 py-1 text-xs font-bold">{{ $quota->statusLabel() }}</span></td>
                                <td class="whitespace-nowrap px-4 py-4 text-right"><a href="{{ route('management.kp-place-quotas.show', $quota) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-bold text-slate-700">Detail</a> <a href="{{ route('management.kp-place-quotas.edit', $quota) }}" class="rounded-lg border border-cyan-200 px-3 py-2 text-xs font-bold text-cyan-700">Edit</a><form class="ml-1 inline-block" method="POST" action="{{ route('management.kp-place-quotas.toggle-open', $quota) }}" onsubmit="return confirm('Ubah status buka/tutup kapasitas ini?')">@csrf<button class="rounded-lg border border-amber-200 px-3 py-2 text-xs font-bold text-amber-700">{{ $quota->is_open ? 'Tutup' : 'Buka' }}</button></form></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @empty
        <div class="rounded-xl bg-white px-6 py-12 text-center text-slate-500 shadow-sm ring-1 ring-slate-200">Belum ada kapasitas sesuai filter.</div>
    @endforelse

    {{ $quotas->links() }}
</div>
@endsection
