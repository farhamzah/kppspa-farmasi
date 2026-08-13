@extends('layouts.app')
@section('title',$title.' - '.config('app.name'))
@section('page_title',$title)
@section('content')
<div class="space-y-6">
    <section class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-100">
        @php($reportQuery = array_filter(['period' => $filters['period'] ?? null, 'status' => $filters['status'] ?? null, 'q' => $filters['q'] ?? null]))
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div><h2 class="text-xl font-black text-slate-950">{{ $title }}</h2><p class="text-sm text-slate-500">Total data: {{ $rows->count() }}</p></div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('management.recaps.preview', array_merge(['type' => $type], $reportQuery)) }}" target="_blank" class="rounded-2xl border border-slate-200 px-4 py-2 text-sm font-bold text-slate-700">Pratinjau Cetak</a>
                <a href="{{ route('management.recaps.preview', array_merge(['type' => $type, 'print' => 1], $reportQuery)) }}" target="_blank" class="rounded-2xl border border-cyan-200 px-4 py-2 text-sm font-bold text-cyan-700">Cetak</a>
                <a href="{{ route('management.recaps.download', array_merge(['type' => $type, 'format' => 'word'], $reportQuery)) }}" class="rounded-2xl border border-indigo-200 px-4 py-2 text-sm font-bold text-indigo-700">Word</a>
                <a href="{{ route('management.recaps.download', array_merge(['type' => $type, 'format' => 'excel'], $reportQuery)) }}" class="rounded-2xl bg-cyan-700 px-4 py-2 text-sm font-bold text-white">Excel</a>
                <a href="{{ route('management.recaps.download', array_merge(['type' => $type, 'format' => 'pdf'], $reportQuery)) }}" class="rounded-2xl border border-rose-200 px-4 py-2 text-sm font-bold text-rose-700">PDF</a>
            </div>
        </div>
        <form class="mt-5 grid gap-3 md:grid-cols-4">
            <input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Cari nama/NIM" class="rounded-2xl border-slate-200 text-sm">
            <select name="period" class="rounded-2xl border-slate-200 text-sm"><option value="">Semua periode</option>@foreach($periods as $period)<option value="{{ $period->id }}" @selected(($filters['period'] ?? '') == $period->id)>{{ $period->name }}</option>@endforeach</select>
            <select name="status" class="rounded-2xl border-slate-200 text-sm"><option value="">Semua status</option>@foreach(['draft','aktif','berjalan','selesai','dibatalkan','dijadwalkan','published','locked'] as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '')===$status)>{{ ucfirst(str_replace('_',' ',$status)) }}</option>@endforeach</select>
            <button class="rounded-2xl border border-cyan-200 px-4 py-2 text-sm font-bold text-cyan-700">Filter</button>
        </form>
    </section>
    <section class="overflow-hidden rounded-3xl bg-white shadow-sm ring-1 ring-slate-100">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-black uppercase tracking-widest text-slate-500">
                    <tr>@foreach(array_keys($rows->first() ?? []) as $heading)<th class="whitespace-nowrap px-5 py-3">{{ $heading }}</th>@endforeach</tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($rows as $row)
                        <tr>@foreach($row as $value)<td class="whitespace-nowrap px-5 py-4">{{ $value }}</td>@endforeach</tr>
                    @empty
                        <tr><td class="px-5 py-10 text-center text-slate-500">Belum ada data rekap.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
