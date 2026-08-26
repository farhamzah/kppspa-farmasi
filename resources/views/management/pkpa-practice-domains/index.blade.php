@extends('layouts.app')
@section('title', 'Wahana PKPA - '.config('app.name'))
@section('page_title', 'Wahana PKPA')
@section('content')
<div class="space-y-5">
    @if($errors->any())<div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $errors->first() }}</div>@endif
    <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <form method="GET" class="grid flex-1 gap-3 md:grid-cols-[1fr_140px_auto]">
                <div><label class="text-xs font-black uppercase tracking-widest text-slate-500">Cari</label><input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Kode atau nama wahana" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"></div>
                <div><label class="text-xs font-black uppercase tracking-widest text-slate-500">Aktif</label><select name="active" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"><option value="">Semua</option><option value="1" @selected(($filters['active'] ?? '') === '1')>Ya</option><option value="0" @selected(($filters['active'] ?? '') === '0')>Tidak</option></select></div>
                <button class="self-end rounded-xl bg-slate-900 px-4 py-2 text-sm font-black text-white">Filter</button>
            </form>
            <a href="{{ route('management.pkpa-practice-domains.create') }}" class="rounded-xl bg-cyan-700 px-4 py-2 text-center text-sm font-black text-white">Tambah Wahana</a>
        </div>
    </div>
    <div class="grid gap-4 lg:grid-cols-2">
        @forelse($domains as $domain)
            <article class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <div class="flex items-start justify-between gap-3">
                    <div><h2 class="text-lg font-black text-slate-950">{{ $domain->name }}</h2><p class="text-sm text-slate-500">{{ $domain->code }} · {{ $domain->short_name ?: '-' }}</p></div>
                    <div class="flex flex-wrap justify-end gap-2">
                        @if($domain->is_system)<span class="rounded-full bg-cyan-50 px-2 py-1 text-xs font-black text-cyan-700 ring-1 ring-cyan-100">Wahana Sistem</span>@endif
                        @if($domain->isLegacyStandalonePuskesmas())<span class="rounded-full bg-amber-50 px-2 py-1 text-xs font-black text-amber-700 ring-1 ring-amber-100">Legacy Puskesmas</span>@endif
                        <span class="rounded-full px-2 py-1 text-xs font-black {{ $domain->is_active ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-100' : 'bg-slate-100 text-slate-700 ring-1 ring-slate-200' }}">{{ $domain->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                    </div>
                </div>
                <div class="mt-4 grid grid-cols-2 gap-3 text-sm"><div class="rounded-xl bg-slate-50 p-3"><p class="text-xs font-black uppercase text-slate-500">Pilihan</p><p class="font-black">{{ $domain->options_count }}</p></div><div class="rounded-xl bg-slate-50 p-3"><p class="text-xs font-black uppercase text-slate-500">Tempat</p><p class="font-black">{{ $domain->display_practice_sites_count ?? $domain->practice_sites_count }}</p></div></div>
                @if($domain->isGovernment() && ($domain->display_practice_sites_count ?? $domain->practice_sites_count) !== $domain->practice_sites_count)
                    <p class="mt-2 text-xs text-slate-500">Hitungan tempat sudah termasuk data Puskesmas legacy yang belum dibersihkan.</p>
                @endif
                <div class="mt-4 flex flex-wrap gap-2">
                    <a href="{{ route('management.pkpa-practice-domains.show', $domain) }}" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-bold">Detail</a>
                    <a href="{{ route('management.pkpa-practice-domains.edit', $domain) }}" class="rounded-lg border border-cyan-200 px-3 py-1.5 text-xs font-bold text-cyan-700">Edit</a>
                    @if($domain->canBeDeleted())
                        <form method="POST" action="{{ route('management.pkpa-practice-domains.destroy', $domain) }}" onsubmit="return confirm('Hapus wahana ini? Data legacy Puskesmas akan dipindahkan ke Pemerintahan jika diperlukan.');">
                            @csrf
                            @method('DELETE')
                            <button class="rounded-lg border border-rose-200 px-3 py-1.5 text-xs font-bold text-rose-700">Hapus</button>
                        </form>
                    @endif
                </div>
            </article>
        @empty
            <div class="rounded-2xl bg-white p-10 text-center text-slate-500 shadow-sm ring-1 ring-slate-200 lg:col-span-2">Belum ada wahana PKPA.</div>
        @endforelse
    </div>
    {{ $domains->links() }}
</div>
@endsection
