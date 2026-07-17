@extends('layouts.app')
@section('title', 'Tempat Praktik - '.config('app.name'))
@section('page_title', 'Tempat Praktik')
@section('content')
<div class="space-y-5">
    @if($errors->any())<div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $errors->first() }}</div>@endif
    <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <form method="GET" class="grid gap-3 md:grid-cols-2 xl:grid-cols-[1fr_180px_160px_140px_150px_130px_auto]">
            <div><label class="text-xs font-black uppercase tracking-widest text-slate-500">Cari</label><input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Kode, nama, kota" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"></div>
            <div><label class="text-xs font-black uppercase tracking-widest text-slate-500">Wahana</label><select name="practice_domain_id" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"><option value="">Semua</option>@foreach($domains as $domain)<option value="{{ $domain->id }}" @selected((string)($filters['practice_domain_id'] ?? '') === (string)$domain->id)>{{ $domain->name }}</option>@endforeach</select></div>
            <div><label class="text-xs font-black uppercase tracking-widest text-slate-500">Status</label><select name="status" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"><option value="">Semua</option>@foreach(\App\Models\PkpaPracticeSite::STATUSES as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst($status) }}</option>@endforeach</select></div>
            <div><label class="text-xs font-black uppercase tracking-widest text-slate-500">Aktif</label><select name="active" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"><option value="">Semua</option><option value="1" @selected(($filters['active'] ?? '') === '1')>Ya</option><option value="0" @selected(($filters['active'] ?? '') === '0')>Tidak</option></select></div>
            <div><label class="text-xs font-black uppercase tracking-widest text-slate-500">Kerja Sama</label><select name="cooperation" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"><option value="">Semua</option><option value="valid" @selected(($filters['cooperation'] ?? '') === 'valid')>Berlaku</option><option value="expired" @selected(($filters['cooperation'] ?? '') === 'expired')>Berakhir</option></select></div>
            <button class="self-end rounded-xl bg-slate-900 px-4 py-2 text-sm font-black text-white">Filter</button>
            <a href="{{ route('management.pkpa-practice-sites.create') }}" class="self-end rounded-xl bg-cyan-700 px-4 py-2 text-center text-sm font-black text-white">Tambah</a>
        </form>
    </div>
    <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-black uppercase tracking-widest text-slate-500"><tr><th class="px-4 py-3">Tempat</th><th class="px-4 py-3">Wahana</th><th class="px-4 py-3">Kota</th><th class="px-4 py-3">Kerja Sama</th><th class="px-4 py-3">Status</th><th class="px-4 py-3 text-right">Aksi</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @forelse($sites as $site)
                    <tr>
                        <td class="px-4 py-4"><div class="font-black text-slate-950">{{ $site->name }}</div><div class="text-xs text-slate-500">{{ $site->code }}{{ $site->legal_name ? ' · '.$site->legal_name : '' }}</div></td>
                        <td class="px-4 py-4">{{ $site->practiceDomain?->name ?: '-' }}<br><span class="text-xs text-slate-500">{{ $site->practiceDomainOption?->name ?: 'Tanpa subjenis' }}</span></td>
                        <td class="px-4 py-4">{{ $site->city ?: '-' }}<br><span class="text-xs text-slate-500">{{ $site->province ?: '-' }}</span></td>
                        <td class="px-4 py-4">{{ $site->cooperation_start_date?->format('d M Y') ?: '-' }}<br><span class="text-xs {{ $site->cooperationStatusLabel() === 'Berakhir' ? 'text-rose-600' : 'text-slate-500' }}">{{ $site->cooperation_end_date?->format('d M Y') ?: '-' }} · {{ $site->cooperationStatusLabel() }}</span></td>
                        <td class="px-4 py-4"><span class="rounded-full px-2 py-1 text-xs font-black {{ $site->statusBadgeClass() }}">{{ $site->statusLabel() }}</span><br><span class="mt-1 inline-block text-xs text-slate-500">{{ $site->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                        <td class="px-4 py-4 text-right"><div class="flex flex-wrap justify-end gap-2"><a href="{{ route('management.pkpa-practice-sites.show', $site) }}" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-bold">Detail</a><a href="{{ route('management.pkpa-practice-sites.edit', $site) }}" class="rounded-lg border border-cyan-200 px-3 py-1.5 text-xs font-bold text-cyan-700">Edit</a></div></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-10 text-center text-slate-500">Belum ada tempat praktik.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-200 px-4 py-3">{{ $sites->links() }}</div>
    </div>
</div>
@endsection
