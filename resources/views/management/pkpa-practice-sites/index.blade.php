@extends('layouts.app')
@section('title', 'Tempat Praktik - '.config('app.name'))
@section('page_title', 'Tempat Praktik')
@section('content')
@php
    $reportQuery = array_filter($filters, fn ($value) => filled($value));
    $groups = $sites->getCollection()->groupBy(fn ($site) => $site->practiceDomain?->name ?? 'Wahana belum ditentukan');
@endphp
<div class="space-y-5">
    @if($errors->any())<div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $errors->first() }}</div>@endif

    <section class="rounded-xl border border-cyan-100 bg-cyan-50 px-5 py-4 text-sm text-cyan-950">
        <h2 class="font-black">Master tempat praktik per wahana</h2>
        <p class="mt-1">Tempat Praktik adalah daftar master. Tempat baru dihitung sebagai tersedia dan memiliki kapasitas setelah diaktifkan pada program serta diberi periode kapasitas.</p>
    </section>

    <section class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <div class="flex flex-col gap-4 2xl:flex-row 2xl:items-end">
            <form method="GET" class="grid flex-1 gap-3 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-[1.35fr_210px_170px_150px_170px_auto]">
                <div><label class="text-xs font-bold uppercase text-slate-500">Cari</label><input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Kode, nama, atau kota" class="mt-1 min-h-11 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
                <div><label class="text-xs font-bold uppercase text-slate-500">Wahana</label><select name="practice_domain_id" class="mt-1 min-h-11 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="">Semua wahana</option>@foreach($domains as $domain)<option value="{{ $domain->id }}" @selected((string)($filters['practice_domain_id'] ?? '') === (string)$domain->id)>{{ $domain->name }}</option>@endforeach</select></div>
                <div><label class="text-xs font-bold uppercase text-slate-500">Status</label><select name="status" class="mt-1 min-h-11 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="">Semua status</option>@foreach(\App\Models\PkpaPracticeSite::STATUSES as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ str($status)->headline() }}</option>@endforeach</select></div>
                <div><label class="text-xs font-bold uppercase text-slate-500">Aktif</label><select name="active" class="mt-1 min-h-11 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="">Semua</option><option value="1" @selected(($filters['active'] ?? '') === '1')>Aktif</option><option value="0" @selected(($filters['active'] ?? '') === '0')>Nonaktif</option></select></div>
                <div><label class="text-xs font-bold uppercase text-slate-500">Kerja Sama</label><select name="cooperation" class="mt-1 min-h-11 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="">Semua</option><option value="valid" @selected(($filters['cooperation'] ?? '') === 'valid')>Berlaku</option><option value="expired" @selected(($filters['cooperation'] ?? '') === 'expired')>Berakhir</option></select></div>
                <button class="min-h-11 self-end rounded-lg bg-slate-950 px-4 py-2 text-sm font-black text-white">Terapkan</button>
            </form>
            <a href="{{ route('management.pkpa-practice-sites.create') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-cyan-700 px-4 py-2 text-sm font-black text-white">Tambah Tempat</a>
        </div>
        <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-4">
            <span class="mr-1 text-xs font-bold uppercase text-slate-500">Laporan sesuai filter</span>
            <a target="_blank" href="{{ route('management.pkpa-capacity-reports.preview', ['type' => 'practice-sites'] + $reportQuery) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-bold text-slate-700">Pratinjau</a>
            <a target="_blank" href="{{ route('management.pkpa-capacity-reports.preview', ['type' => 'practice-sites', 'print' => 1] + $reportQuery) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-bold text-slate-700">Print</a>
            <a href="{{ route('management.pkpa-capacity-reports.download', ['type' => 'practice-sites', 'format' => 'pdf'] + $reportQuery) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-bold text-slate-700">PDF</a>
            <a href="{{ route('management.pkpa-capacity-reports.download', ['type' => 'practice-sites', 'format' => 'xlsx'] + $reportQuery) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-bold text-slate-700">Excel</a>
            <a href="{{ route('management.pkpa-capacity-reports.download', ['type' => 'practice-sites', 'format' => 'word'] + $reportQuery) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-bold text-slate-700">Word</a>
        </div>
    </section>

    @if($coverage['program'])
        <section class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
            <header class="border-b border-slate-200 px-5 py-4">
                <h2 class="font-black text-slate-950">Cakupan {{ $coverage['program']->code }}</h2>
                <p class="mt-1 text-sm text-slate-600">Perbandingan ini menunjukkan tahap penggunaan tempat. Selisih bukan duplikasi: nama yang belum masuk tahap berikutnya ditampilkan pada kolom keterangan.</p>
            </header>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-bold uppercase text-slate-500"><tr><th class="px-4 py-3">Wahana</th><th class="px-4 py-3 text-center">Master</th><th class="px-4 py-3 text-center">Aktif di Program</th><th class="px-4 py-3 text-center">Punya Kapasitas</th><th class="px-4 py-3">Keterangan</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($coverage['rows'] as $row)
                            <tr>
                                <td class="px-4 py-4 font-black text-slate-950">{{ $row['domain']->name }}</td>
                                <td class="px-4 py-4 text-center font-black">{{ $row['master'] }}</td>
                                <td class="px-4 py-4 text-center font-black">{{ $row['program'] }}</td>
                                <td class="px-4 py-4 text-center font-black">{{ $row['capacity'] }}</td>
                                <td class="px-4 py-4 text-sm">
                                    @if($row['missing_program']->isEmpty() && $row['missing_capacity']->isEmpty())
                                        <span class="rounded-full bg-emerald-50 px-2 py-1 text-xs font-bold text-emerald-700">Lengkap</span>
                                    @else
                                        @if($row['missing_program']->isNotEmpty())<div><strong>Belum masuk program:</strong> {{ $row['missing_program']->join(', ') }}</div>@endif
                                        @if($row['missing_capacity']->isNotEmpty())<div class="mt-1"><strong>Belum punya kapasitas:</strong> {{ $row['missing_capacity']->join(', ') }}</div>@endif
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    <div class="grid gap-3 sm:grid-cols-2">
        <div class="border-l-4 border-cyan-500 bg-white px-5 py-4 shadow-sm"><div class="text-xs font-bold uppercase text-slate-500">Tempat ditampilkan</div><div class="mt-1 text-2xl font-black">{{ $sites->total() }}</div></div>
        <div class="border-l-4 border-emerald-500 bg-white px-5 py-4 shadow-sm"><div class="text-xs font-bold uppercase text-slate-500">Kelompok wahana</div><div class="mt-1 text-2xl font-black">{{ $groups->count() }}</div></div>
    </div>

    @forelse($groups as $domainName => $items)
        <section class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
            <header class="flex items-center justify-between border-b border-cyan-100 bg-cyan-50 px-5 py-4">
                <div><p class="text-xs font-black uppercase text-cyan-700">Wahana PKPA</p><h2 class="mt-1 text-xl font-black text-slate-950">{{ $domainName }}</h2></div>
                <span class="rounded-full bg-white px-3 py-1 text-xs font-black text-cyan-800 ring-1 ring-cyan-200">{{ $items->count() }} tempat</span>
            </header>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-bold uppercase text-slate-500"><tr><th class="px-4 py-3">Tempat Praktik</th><th class="px-4 py-3">Jenis</th><th class="px-4 py-3">Lokasi</th><th class="px-4 py-3">Kerja Sama</th><th class="px-4 py-3">Status</th><th class="px-4 py-3 text-right">Aksi</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($items as $site)
                            <tr>
                                <td class="px-4 py-4"><div class="font-black text-slate-950">{{ $site->name }}</div><div class="text-xs text-slate-500">{{ $site->code }}{{ $site->legal_name ? ' / '.$site->legal_name : '' }}</div></td>
                                <td class="px-4 py-4">{{ $site->practiceDomainOption?->name ?: 'Tanpa subjenis' }}</td>
                                <td class="px-4 py-4">{{ $site->city ?: '-' }}<div class="text-xs text-slate-500">{{ $site->province ?: '-' }}</div></td>
                                <td class="px-4 py-4">{{ $site->cooperationStatusLabel() }}<div class="text-xs text-slate-500">{{ $site->cooperation_end_date?->format('d M Y') ?: 'Tanpa batas akhir' }}</div></td>
                                <td class="px-4 py-4"><span class="rounded-full px-2 py-1 text-xs font-black {{ $site->statusBadgeClass() }}">{{ $site->statusLabel() }}</span><div class="mt-1 text-xs text-slate-500">{{ $site->is_active ? 'Aktif digunakan' : 'Nonaktif' }}</div></td>
                                <td class="whitespace-nowrap px-4 py-4 text-right"><a href="{{ route('management.pkpa-practice-sites.show', $site) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-bold">Detail</a> <a href="{{ route('management.pkpa-practice-sites.edit', $site) }}" class="rounded-lg border border-cyan-200 px-3 py-2 text-xs font-bold text-cyan-700">Edit</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @empty
        <div class="rounded-xl bg-white px-6 py-12 text-center text-slate-500 shadow-sm ring-1 ring-slate-200">Belum ada tempat praktik sesuai filter.</div>
    @endforelse

    {{ $sites->links() }}
</div>
@endsection
