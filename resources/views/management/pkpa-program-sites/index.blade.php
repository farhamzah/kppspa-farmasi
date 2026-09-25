@extends('layouts.app')
@section('title', ($pageTitle ?? 'Tempat Tersedia').' - '.config('app.name'))
@section('page_title', $pageTitle ?? 'Tempat Tersedia')
@section('content')
@php
    $reportQuery = array_filter($filters, fn ($value) => filled($value));
    $groups = $programSites->getCollection()->groupBy(fn ($item) => $item->practiceDomain?->name ?? 'Wahana belum ditentukan');
@endphp
<div class="space-y-5">
    <section class="rounded-xl border border-cyan-100 bg-cyan-50 px-5 py-4 text-sm text-cyan-950">
        <h2 class="font-black">Tempat aktif per program</h2>
        <p class="mt-1">Daftar dikelompokkan menurut wahana. Kapasitas rinci setiap periode dikelola dari tombol <strong>Kelola</strong>.</p>
    </section>

    <section class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-end">
            <form method="GET" class="grid flex-1 gap-3 md:grid-cols-2 xl:grid-cols-[1.35fr_220px_210px_170px_auto]">
                <div><label class="text-xs font-bold uppercase text-slate-500">Cari</label><input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Nama, kode, atau kota" class="mt-1 min-h-11 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
                <div><label class="text-xs font-bold uppercase text-slate-500">Program</label><select name="program_id" class="mt-1 min-h-11 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="">Semua program</option>@foreach($programs as $program)<option value="{{ $program->id }}" @selected(($filters['program_id'] ?? '') == $program->id)>{{ $program->code }}</option>@endforeach</select></div>
                <div><label class="text-xs font-bold uppercase text-slate-500">Wahana</label><select name="practice_domain_id" class="mt-1 min-h-11 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="">Semua wahana</option>@foreach($domains as $domain)<option value="{{ $domain->id }}" @selected(($filters['practice_domain_id'] ?? '') == $domain->id)>{{ $domain->name }}</option>@endforeach</select></div>
                <div><label class="text-xs font-bold uppercase text-slate-500">Status</label><select name="status" class="mt-1 min-h-11 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="">Semua status</option>@foreach(\App\Models\PkpaProgramSite::STATUSES as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ str($status)->headline() }}</option>@endforeach</select></div>
                <button class="min-h-11 self-end rounded-lg bg-slate-950 px-4 py-2 text-sm font-black text-white">Terapkan</button>
            </form>
            <a href="{{ route('management.pkpa-program-sites.create') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-cyan-700 px-4 py-2 text-sm font-black text-white">Tambah Tempat</a>
        </div>
        <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-4">
            <span class="mr-1 text-xs font-bold uppercase text-slate-500">Laporan sesuai filter</span>
            <a target="_blank" href="{{ route('management.pkpa-capacity-reports.preview', ['type' => 'program-sites'] + $reportQuery) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-bold text-slate-700">Pratinjau</a>
            <a target="_blank" href="{{ route('management.pkpa-capacity-reports.preview', ['type' => 'program-sites', 'print' => 1] + $reportQuery) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-bold text-slate-700">Print</a>
            <a href="{{ route('management.pkpa-capacity-reports.download', ['type' => 'program-sites', 'format' => 'pdf'] + $reportQuery) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-bold text-slate-700">PDF</a>
            <a href="{{ route('management.pkpa-capacity-reports.download', ['type' => 'program-sites', 'format' => 'xlsx'] + $reportQuery) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-bold text-slate-700">Excel</a>
            <a href="{{ route('management.pkpa-capacity-reports.download', ['type' => 'program-sites', 'format' => 'word'] + $reportQuery) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-bold text-slate-700">Word</a>
        </div>
    </section>

    <div class="grid gap-3 sm:grid-cols-3">
        <div class="border-l-4 border-cyan-500 bg-white px-5 py-4 shadow-sm"><div class="text-xs font-bold uppercase text-slate-500">Tempat ditampilkan</div><div class="mt-1 text-2xl font-black">{{ $programSites->total() }}</div></div>
        <div class="border-l-4 border-emerald-500 bg-white px-5 py-4 shadow-sm"><div class="text-xs font-bold uppercase text-slate-500">Kelompok wahana</div><div class="mt-1 text-2xl font-black">{{ $groups->count() }}</div></div>
        <div class="border-l-4 border-amber-500 bg-white px-5 py-4 shadow-sm"><div class="text-xs font-bold uppercase text-slate-500">Kapasitas di halaman ini</div><div class="mt-1 text-2xl font-black">{{ $programSites->sum('maximum_students_sum') }}</div></div>
    </div>

    @forelse($groups as $domainName => $items)
        <section class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
            <header class="flex items-center justify-between border-b border-cyan-100 bg-cyan-50 px-5 py-4">
                <div><p class="text-xs font-black uppercase text-cyan-700">Wahana PKPA</p><h2 class="mt-1 text-xl font-black text-slate-950">{{ $domainName }}</h2></div>
                <span class="rounded-full bg-white px-3 py-1 text-xs font-black text-cyan-800 ring-1 ring-cyan-200">{{ $items->count() }} tempat</span>
            </header>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-bold uppercase text-slate-500"><tr><th class="px-4 py-3">Program</th><th class="px-4 py-3">Tempat Praktik</th><th class="px-4 py-3">Jenis</th><th class="px-4 py-3 text-center">Periode</th><th class="px-4 py-3 text-center">Kapasitas</th><th class="px-4 py-3">Status</th><th class="px-4 py-3 text-right">Aksi</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($items as $programSite)
                            <tr>
                                <td class="px-4 py-4"><div class="font-black text-slate-950">{{ $programSite->program?->code }}</div><div class="text-xs text-slate-500">{{ $programSite->program?->name }}</div></td>
                                <td class="px-4 py-4"><div class="font-bold text-slate-950">{{ $programSite->practiceSite?->name }}</div><div class="text-xs text-slate-500">{{ $programSite->practiceSite?->code }} / {{ $programSite->practiceSite?->city ?: '-' }}</div></td>
                                <td class="px-4 py-4">{{ $programSite->practiceDomainOption?->name ?? $domainName }}</td>
                                <td class="px-4 py-4 text-center"><span class="font-black">{{ $programSite->availability_periods_count }}</span><div class="text-xs text-slate-500">periode tersedia</div></td>
                                <td class="px-4 py-4 text-center"><span class="font-black">{{ $programSite->maximum_students_sum ?? 0 }}</span><div class="text-xs text-slate-500">mahasiswa</div></td>
                                <td class="px-4 py-4"><span class="rounded-full bg-emerald-50 px-2 py-1 text-xs font-bold text-emerald-700">{{ $programSite->statusLabel() }}</span></td>
                                <td class="px-4 py-4 text-right"><a href="{{ route('management.pkpa-program-sites.show', $programSite) }}" class="rounded-lg border border-cyan-200 px-3 py-2 text-xs font-black text-cyan-700">Kelola</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @empty
        <div class="rounded-xl bg-white px-6 py-12 text-center text-slate-500 shadow-sm ring-1 ring-slate-200">Belum ada tempat sesuai filter.</div>
    @endforelse

    {{ $programSites->links() }}
</div>
@endsection
