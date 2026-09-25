@extends('layouts.app')
@section('title','Rekap & Laporan - '.config('app.name'))
@section('page_title','Rekap & Laporan')
@section('content')
@php($reportQuery = array_filter(request()->only(['program', 'domain', 'site', 'internal_supervisor', 'field_supervisor', 'q'])))
<div class="space-y-5">
    <section class="border-b border-slate-200 bg-white px-6 py-6">
        <p class="text-xs font-black uppercase tracking-widest text-cyan-700">Pusat Laporan PKPA</p>
        <div class="mt-2 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h2 class="text-2xl font-black text-slate-950">Data siap dibaca, dicetak, atau diunduh</h2>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">Semua laporan mengambil data dari publikasi penempatan resmi yang sedang aktif. Gunakan satu filter untuk membuka pratinjau, Excel, PDF, atau cetak A4.</p>
            </div>
            <a href="{{ route('management.pkpa-analytics.index') }}" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 px-4 text-sm font-bold text-slate-700 hover:bg-slate-50">Buka Analitik</a>
        </div>
    </section>

    <section class="grid gap-px overflow-hidden rounded-lg border border-slate-200 bg-slate-200 sm:grid-cols-2 xl:grid-cols-4">
        @foreach($summary as $label => $value)
            <div class="bg-white px-5 py-4">
                <p class="text-xs font-black uppercase tracking-widest text-slate-500">{{ $label }}</p>
                <p class="mt-2 text-3xl font-black text-slate-950">{{ number_format($value) }}</p>
            </div>
        @endforeach
    </section>

    <section class="border-y border-slate-200 bg-white px-6 py-5">
        <form method="get" class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            <label class="block"><span class="mb-1 block text-xs font-bold text-slate-600">Program / Periode</span><select name="program" class="w-full rounded-md border-slate-300 text-sm"><option value="">Semua program</option>@foreach($programs as $program)<option value="{{ $program->id }}" @selected(request('program') == $program->id)>{{ $program->name }}</option>@endforeach</select></label>
            <label class="block"><span class="mb-1 block text-xs font-bold text-slate-600">Wahana</span><select name="domain" class="w-full rounded-md border-slate-300 text-sm"><option value="">Semua wahana</option>@foreach($domains as $domain)<option value="{{ $domain->id }}" @selected(request('domain') == $domain->id)>{{ $domain->name }}</option>@endforeach</select></label>
            <label class="block"><span class="mb-1 block text-xs font-bold text-slate-600">Tempat Praktik</span><select name="site" class="w-full rounded-md border-slate-300 text-sm"><option value="">Semua tempat</option>@foreach($sites as $site)<option value="{{ $site->id }}" @selected(request('site') == $site->id)>{{ $site->name }}</option>@endforeach</select></label>
            <label class="block"><span class="mb-1 block text-xs font-bold text-slate-600">Pembimbing Dalam</span><select name="internal_supervisor" class="w-full rounded-md border-slate-300 text-sm"><option value="">Semua pembimbing</option>@foreach($internalSupervisors as $supervisor)<option value="{{ $supervisor->core_user_id }}" @selected(request('internal_supervisor') == $supervisor->core_user_id)>{{ $supervisor->name_snapshot ?: $supervisor->core_user_id }}</option>@endforeach</select></label>
            <label class="block"><span class="mb-1 block text-xs font-bold text-slate-600">Preseptor</span><select name="field_supervisor" class="w-full rounded-md border-slate-300 text-sm"><option value="">Semua preseptor</option>@foreach($fieldSupervisors as $supervisor)<option value="{{ $supervisor->core_user_id }}" @selected(request('field_supervisor') == $supervisor->core_user_id)>{{ $supervisor->name_snapshot ?: $supervisor->core_user_id }}</option>@endforeach</select></label>
            <label class="block"><span class="mb-1 block text-xs font-bold text-slate-600">Cari</span><input name="q" value="{{ request('q') }}" placeholder="Nama, NIM, atau tempat" class="w-full rounded-md border-slate-300 text-sm"></label>
            <div class="flex items-end gap-2 xl:col-span-3"><button class="min-h-10 rounded-md bg-cyan-700 px-4 text-sm font-bold text-white hover:bg-cyan-800">Terapkan Filter</button>@if($reportQuery)<a href="{{ route('management.recaps.index') }}" class="inline-flex min-h-10 items-center rounded-md border border-slate-300 px-3 text-sm font-bold text-slate-600">Reset</a>@endif</div>
        </form>
    </section>

    <section>
        <div class="mb-3 flex items-end justify-between gap-4"><div><h3 class="text-lg font-black text-slate-950">Pilih Laporan</h3><p class="text-sm text-slate-500">Filter di atas otomatis dibawa ke setiap format.</p></div><span class="text-xs font-bold text-slate-500">{{ count($reports) }} laporan tersedia</span></div>
        <div class="grid gap-3 lg:grid-cols-2">
            @foreach($reports as $type => $report)
                <article class="border-b border-slate-200 bg-white p-5 sm:rounded-lg sm:border">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"><div class="min-w-0"><div class="flex flex-wrap items-center gap-2"><h4 class="font-black text-slate-950">{{ $report['title'] }}</h4><span class="rounded-md bg-slate-100 px-2 py-1 text-xs font-black text-slate-600">{{ number_format($reportCounts[$type] ?? 0) }} data</span></div><p class="mt-1 text-sm leading-6 text-slate-600">{{ $report['description'] }}</p></div><a href="{{ route('management.recaps.'.$type, $reportQuery) }}" class="shrink-0 rounded-md bg-cyan-700 px-4 py-2 text-center text-sm font-bold text-white hover:bg-cyan-800">Buka</a></div>
                    <div class="mt-4 flex flex-wrap gap-x-4 gap-y-2 border-t border-slate-100 pt-3 text-sm font-bold">
                        <a href="{{ route('management.recaps.preview', array_merge(['type' => $type], $reportQuery)) }}" target="_blank" class="text-cyan-700 hover:text-cyan-900">Pratinjau</a>
                        <a href="{{ route('management.recaps.download', array_merge(['type' => $type, 'format' => 'excel'], $reportQuery)) }}" class="text-emerald-700 hover:text-emerald-900">Excel</a>
                        <a href="{{ route('management.recaps.download', array_merge(['type' => $type, 'format' => 'pdf'], $reportQuery)) }}" class="text-rose-700 hover:text-rose-900">PDF</a>
                        <a href="{{ route('management.recaps.preview', array_merge(['type' => $type, 'print' => 1], $reportQuery)) }}" target="_blank" class="text-slate-700 hover:text-slate-950">Cetak A4</a>
                    </div>
                </article>
            @endforeach
        </div>
    </section>
</div>
@endsection
