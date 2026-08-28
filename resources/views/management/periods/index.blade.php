@extends('layouts.app')

@section('title', 'Periode PKPA - '.config('app.name'))
@section('page_title', 'Periode PKPA')

@section('content')
<div class="space-y-5">
    @if($errors->any())<div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>@endif
    <div class="flex flex-col gap-3 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 lg:flex-row lg:items-end lg:justify-between">
        <form method="GET" class="grid flex-1 gap-3 md:grid-cols-[1fr_220px_auto]">
            <div>
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Cari</label>
                <input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Nama atau tahun akademik" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status</label>
                <select name="status" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Semua</option>
                    @foreach(\App\Models\KpPeriod::STATUSES as $status)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
            </div>
            <button class="self-end rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Filter</button>
        </form>
        <a href="{{ route('management.kp-periods.create') }}" class="rounded-lg bg-teal-600 px-4 py-2 text-center text-sm font-semibold text-white">Tambah Periode</a>
    </div>
    <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr><th class="px-4 py-3">Periode</th><th class="px-4 py-3">Pendaftaran</th><th class="px-4 py-3">Penetapan Tempat</th><th class="px-4 py-3">Tanggal PKPA</th><th class="px-4 py-3">Status</th><th class="px-4 py-3 text-right">Aksi</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($periods as $period)
                        <tr>
                            <td class="px-4 py-4"><div class="font-semibold text-slate-950">{{ $period->name }}</div><div class="text-xs text-slate-500">{{ $period->academic_year ?: '-' }} {{ $period->semester ? '('.$period->semester.')' : '' }}</div></td>
                            <td class="px-4 py-4 text-slate-600">{{ $period->registration_start_at?->format('d M Y H:i') ?? '-' }}<br>{{ $period->registration_end_at?->format('d M Y H:i') ?? '-' }}</td>
                            <td class="px-4 py-4 text-slate-600">{{ $period->selection_start_at?->format('d M Y H:i') ?? '-' }}<br>{{ $period->selection_end_at?->format('d M Y H:i') ?? '-' }}</td>
                            <td class="px-4 py-4 text-slate-600">{{ $period->kp_start_date?->format('d M Y') ?? '-' }}<br>{{ $period->kp_end_date?->format('d M Y') ?? '-' }}</td>
                            <td class="px-4 py-4"><span class="rounded-full {{ $period->statusBadgeClass() }} px-2 py-1 text-xs font-semibold">{{ $period->statusLabel() }}</span></td>
                            <td class="px-4 py-4 text-right"><a href="{{ route('management.kp-periods.show', $period) }}" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700">Detail</a> <a href="{{ route('management.kp-periods.edit', $period) }}" class="rounded-lg border border-teal-200 px-3 py-1.5 text-xs font-semibold text-teal-700">Edit</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-10 text-center text-slate-500">Belum ada periode PKPA.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-200 px-4 py-3">{{ $periods->links() }}</div>
    </div>
</div>
@endsection
