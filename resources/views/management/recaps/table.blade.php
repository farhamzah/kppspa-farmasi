@extends('layouts.app')
@section('title',$title.' - '.config('app.name'))
@section('page_title',$title)
@section('content')
@php($reportQuery = array_filter(['program' => $filters['program'] ?? null, 'domain' => $filters['domain'] ?? null, 'site' => $filters['site'] ?? null, 'internal_supervisor' => $filters['internal_supervisor'] ?? null, 'field_supervisor' => $filters['field_supervisor'] ?? null, 'q' => $filters['q'] ?? null]))
<div class="space-y-5">
    <section class="border-b border-slate-200 bg-white px-6 py-5">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
            <div><a href="{{ route('management.recaps.index', $reportQuery) }}" class="text-sm font-bold text-cyan-700">Kembali ke pusat laporan</a><h2 class="mt-1 text-xl font-black text-slate-950">{{ $title }}</h2><p class="mt-1 text-sm text-slate-500">{{ number_format($rows->count()) }} data sesuai filter.</p></div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('management.recaps.preview', array_merge(['type' => $type], $reportQuery)) }}" target="_blank" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-bold text-slate-700">Pratinjau</a>
                <a href="{{ route('management.recaps.preview', array_merge(['type' => $type, 'print' => 1], $reportQuery)) }}" target="_blank" class="rounded-md border border-cyan-300 px-4 py-2 text-sm font-bold text-cyan-800">Cetak A4</a>
                <a href="{{ route('management.recaps.download', array_merge(['type' => $type, 'format' => 'excel'], $reportQuery)) }}" class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-bold text-white">Excel</a>
                <a href="{{ route('management.recaps.download', array_merge(['type' => $type, 'format' => 'pdf'], $reportQuery)) }}" class="rounded-md bg-rose-700 px-4 py-2 text-sm font-bold text-white">PDF</a>
            </div>
        </div>
        <form class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            <select name="program" aria-label="Program atau periode" class="rounded-md border-slate-300 text-sm"><option value="">Semua program</option>@foreach($programs as $program)<option value="{{ $program->id }}" @selected(($filters['program'] ?? '') == $program->id)>{{ $program->name }}</option>@endforeach</select>
            <select name="domain" aria-label="Wahana" class="rounded-md border-slate-300 text-sm"><option value="">Semua wahana</option>@foreach($domains as $domain)<option value="{{ $domain->id }}" @selected(($filters['domain'] ?? '') == $domain->id)>{{ $domain->name }}</option>@endforeach</select>
            <select name="site" aria-label="Tempat praktik" class="rounded-md border-slate-300 text-sm"><option value="">Semua tempat</option>@foreach($sites as $site)<option value="{{ $site->id }}" @selected(($filters['site'] ?? '') == $site->id)>{{ $site->name }}</option>@endforeach</select>
            <select name="internal_supervisor" aria-label="Pembimbing dalam" class="rounded-md border-slate-300 text-sm"><option value="">Semua pembimbing</option>@foreach($internalSupervisors as $supervisor)<option value="{{ $supervisor->core_user_id }}" @selected(($filters['internal_supervisor'] ?? '') == $supervisor->core_user_id)>{{ $supervisor->name_snapshot ?: $supervisor->core_user_id }}</option>@endforeach</select>
            <select name="field_supervisor" aria-label="Preseptor" class="rounded-md border-slate-300 text-sm"><option value="">Semua preseptor</option>@foreach($fieldSupervisors as $supervisor)<option value="{{ $supervisor->core_user_id }}" @selected(($filters['field_supervisor'] ?? '') == $supervisor->core_user_id)>{{ $supervisor->name_snapshot ?: $supervisor->core_user_id }}</option>@endforeach</select>
            <input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Cari nama, NIM, atau tempat" class="rounded-md border-slate-300 text-sm">
            <button class="rounded-md bg-cyan-700 px-4 py-2 text-sm font-bold text-white xl:col-span-3 xl:justify-self-start">Terapkan Filter</button>
        </form>
    </section>
    <section class="overflow-hidden rounded-lg border border-slate-200 bg-white"><div class="overflow-x-auto"><table class="min-w-full divide-y divide-slate-200 text-sm">
        <thead class="bg-slate-50 text-left text-xs font-black uppercase tracking-wider text-slate-600"><tr><th class="px-4 py-3">No</th>@foreach(array_keys($rows->first() ?? []) as $heading)<th class="whitespace-nowrap px-4 py-3">{{ $heading }}</th>@endforeach</tr></thead>
        <tbody class="divide-y divide-slate-100">@forelse($rows as $index => $row)<tr class="align-top hover:bg-slate-50"><td class="px-4 py-3 text-slate-500">{{ $index + 1 }}</td>@foreach($row as $value)<td class="min-w-32 px-4 py-3 text-slate-700">{{ $value }}</td>@endforeach</tr>@empty<tr><td colspan="20" class="px-5 py-12 text-center text-slate-500">Belum ada data sesuai filter yang dipilih.</td></tr>@endforelse</tbody>
    </table></div></section>
</div>
@endsection
