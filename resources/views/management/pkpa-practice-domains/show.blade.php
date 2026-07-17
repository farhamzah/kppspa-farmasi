@extends('layouts.app')
@section('title', 'Detail Wahana PKPA - '.config('app.name'))
@section('page_title', 'Detail Wahana PKPA')
@section('content')
<div class="space-y-5">
    @if($errors->any())<div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $errors->first() }}</div>@endif
    <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <div class="flex flex-wrap gap-2">@if($domain->is_system)<span class="rounded-full bg-cyan-50 px-2 py-1 text-xs font-black text-cyan-700 ring-1 ring-cyan-100">Wahana Sistem</span>@endif<span class="rounded-full px-2 py-1 text-xs font-black {{ $domain->is_active ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-100' : 'bg-slate-100 text-slate-700 ring-1 ring-slate-200' }}">{{ $domain->is_active ? 'Aktif' : 'Nonaktif' }}</span></div>
                <h2 class="mt-3 text-2xl font-black text-slate-950">{{ $domain->name }}</h2>
                <p class="text-sm text-slate-500">{{ $domain->code }} · {{ $domain->short_name ?: '-' }}</p>
                <p class="mt-3 max-w-3xl text-sm text-slate-600">{{ $domain->description ?: 'Belum ada deskripsi.' }}</p>
            </div>
            <a href="{{ route('management.pkpa-practice-domains.edit', $domain) }}" class="rounded-xl border border-cyan-200 px-4 py-2 text-sm font-black text-cyan-700">Edit Wahana</a>
        </div>
    </div>
    <div class="grid gap-5 xl:grid-cols-[1fr_360px]">
        <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <h3 class="text-lg font-black text-slate-950">{{ $domain->isGovernment() ? 'Pilihan Pemerintahan' : 'Pilihan/Subjenis' }}</h3>
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-black uppercase text-slate-500"><tr><th class="px-4 py-3">Pilihan</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Sistem</th><th class="px-4 py-3 text-right">Aksi</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                    @forelse($domain->options as $option)
                        <tr>
                            <td class="px-4 py-3">
                                <form id="option-update-{{ $option->id }}" method="POST" action="{{ route('management.pkpa-practice-domains.options.update', [$domain, $option]) }}" class="grid gap-2 md:grid-cols-[120px_1fr]">
                                    @csrf
                                    @method('PUT')
                                    <input name="code" value="{{ $option->code }}" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-xs font-bold">
                                    <input name="name" value="{{ $option->name }}" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-xs">
                                    <input type="hidden" name="description" value="{{ $option->description }}">
                                    <input type="hidden" name="sort_order" value="{{ $option->sort_order }}">
                                </form>
                            </td>
                            <td class="px-4 py-3"><label class="flex items-center gap-2 text-xs font-bold"><input form="option-update-{{ $option->id }}" type="checkbox" name="is_active" value="1" @checked($option->is_active) class="rounded border-slate-300"> Aktif</label></td>
                            <td class="px-4 py-3">{{ $option->is_system ? 'Ya' : 'Tidak' }}</td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex flex-wrap justify-end gap-2">
                                    <button form="option-update-{{ $option->id }}" class="rounded-lg border border-cyan-200 px-3 py-1.5 text-xs font-bold text-cyan-700">Simpan</button>
                                    @unless($option->is_system)
                                        <form method="POST" action="{{ route('management.pkpa-practice-domains.options.destroy', [$domain, $option]) }}">@csrf @method('DELETE')<button class="rounded-lg border border-rose-200 px-3 py-1.5 text-xs font-bold text-rose-700">Hapus</button></form>
                                    @else
                                        <span class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-500">Terlindungi</span>
                                    @endunless
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-8 text-center text-slate-500">Belum ada pilihan.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <h3 class="text-lg font-black text-slate-950">Tambah Pilihan</h3>
            <form method="POST" action="{{ route('management.pkpa-practice-domains.options.store', $domain) }}" class="mt-4 space-y-3">
                @csrf
                <div><label class="text-xs font-black uppercase text-slate-500">Kode</label><input name="code" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" required></div>
                <div><label class="text-xs font-black uppercase text-slate-500">Nama</label><input name="name" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" required></div>
                <div><label class="text-xs font-black uppercase text-slate-500">Deskripsi</label><textarea name="description" rows="3" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"></textarea></div>
                <label class="flex items-center gap-2 text-sm font-bold"><input type="checkbox" name="is_active" value="1" checked class="rounded border-slate-300"> Aktif</label>
                <button class="w-full rounded-xl bg-cyan-700 px-4 py-2 text-sm font-black text-white">Tambah</button>
            </form>
        </div>
    </div>
</div>
@endsection
