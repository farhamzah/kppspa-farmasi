@extends('layouts.app')
@section('title', 'Pemantauan Pemilihan - '.config('app.name'))
@section('page_title', 'Pemantauan Pemilihan Tempat')
@section('content')
@php
    $reportQuery = array_filter($filters ?? [], fn ($value) => filled($value));
@endphp

<div class="space-y-5">
    @if(session('status'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif

    <section class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-cyan-700">Laporan Pemilihan</p>
                <p class="mt-1 text-sm text-slate-500">Pratinjau, cetak, atau unduh seluruh hasil sesuai filter aktif.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('management.place-selections.report.preview', $reportQuery) }}" target="_blank" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Pratinjau Cetak</a>
                <a href="{{ route('management.place-selections.report.preview', array_merge($reportQuery, ['print' => 1])) }}" target="_blank" class="rounded-lg border border-cyan-200 px-4 py-2 text-sm font-semibold text-cyan-700">Cetak</a>
                <a href="{{ route('management.place-selections.report.download', array_merge(['format' => 'word'], $reportQuery)) }}" class="rounded-lg border border-indigo-200 px-4 py-2 text-sm font-semibold text-indigo-700">Word</a>
                <a href="{{ route('management.place-selections.report.download', array_merge(['format' => 'excel'], $reportQuery)) }}" class="rounded-lg border border-emerald-200 px-4 py-2 text-sm font-semibold text-emerald-700">Excel</a>
                <a href="{{ route('management.place-selections.report.download', array_merge(['format' => 'pdf'], $reportQuery)) }}" class="rounded-lg border border-rose-200 px-4 py-2 text-sm font-semibold text-rose-700">PDF</a>
                <a href="{{ route('management.place-selections.manual') }}" class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white">Penempatan Manual</a>
            </div>
        </div>
    </section>

    <div class="grid gap-3 md:grid-cols-4 xl:grid-cols-7">
        @foreach([['Terverifikasi',$stats['verified']],['Sudah Memilih',$stats['selected']],['Belum Memilih',$stats['not_selected']],['Daftar Tunggu',$stats['waiting']],['Total Kuota',$stats['total_quota']],['Sisa Kuota',$stats['remaining_quota']],['Tempat Penuh',$stats['full_places']]] as [$label,$value])
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
                <p class="text-xs text-slate-500">{{ $label }}</p>
                <p class="mt-2 text-2xl font-bold">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <form method="GET" class="grid gap-3 md:grid-cols-[1fr_220px_180px_auto]">
            <input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Cari nama, NIM, tempat" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <select name="period" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Semua Periode</option>
                @foreach($periods as $period)
                    <option value="{{ $period->id }}" @selected(($filters['period'] ?? '') == $period->id)>{{ $period->name }}</option>
                @endforeach
            </select>
            <select name="status" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Semua Status</option>
                <option value="aktif" @selected(($filters['status'] ?? '') === 'aktif')>Aktif</option>
                <option value="dibatalkan" @selected(($filters['status'] ?? '') === 'dibatalkan')>Dibatalkan</option>
                <option value="dipindahkan" @selected(($filters['status'] ?? '') === 'dipindahkan')>Dipindahkan</option>
            </select>
            <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Filter</button>
        </form>
    </section>

    <section class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Mahasiswa</th>
                        <th class="px-4 py-3">Periode</th>
                        <th class="px-4 py-3">Tempat</th>
                        <th class="px-4 py-3">Waktu</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($selections as $selection)
                        <tr>
                            <td class="px-4 py-4">
                                <div class="font-semibold">{{ $selection->student->user->name }}</div>
                                <div class="text-xs text-slate-500">{{ $selection->student->nim ?: '-' }}</div>
                            </td>
                            <td class="px-4 py-4">{{ $selection->period->name }}</td>
                            <td class="px-4 py-4">{{ $selection->place->name }}</td>
                            <td class="px-4 py-4">{{ $selection->selected_at?->format('d M Y H:i') }}</td>
                            <td class="px-4 py-4">
                                <span class="rounded-full {{ $selection->statusBadgeClass() }} px-2 py-1 text-xs font-semibold">{{ $selection->statusLabel() }}</span>
                            </td>
                            <td class="px-4 py-4 text-right">
                                <a href="{{ route('management.place-selections.show', $selection) }}" class="rounded-lg border border-teal-200 px-3 py-1.5 text-xs font-semibold text-teal-700">Detail</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center text-slate-500">Belum ada pilihan tempat.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t px-4 py-3">{{ $selections->links() }}</div>
    </section>
</div>
@endsection
