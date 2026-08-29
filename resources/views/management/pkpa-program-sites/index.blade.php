@extends('layouts.app')
@section('title', ($pageTitle ?? 'Tempat Tersedia').' - '.config('app.name'))
@section('page_title', $pageTitle ?? 'Tempat Tersedia')
@section('content')
<div class="space-y-5">
    @if(session('status'))<div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-700">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $errors->first() }}</div>@endif
    <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <div class="flex flex-col gap-3 xl:flex-row xl:items-end xl:justify-between">
            <form method="GET" class="grid flex-1 gap-3 md:grid-cols-2 xl:grid-cols-[1fr_170px_190px_150px_auto]">
                <div><label class="text-xs font-black uppercase tracking-widest text-slate-500">Cari</label><input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Program, kode, tempat" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"></div>
                <div><label class="text-xs font-black uppercase tracking-widest text-slate-500">Program</label><select name="program_id" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"><option value="">Semua</option>@foreach($programs as $program)<option value="{{ $program->id }}" @selected(($filters['program_id'] ?? '') == $program->id)>{{ $program->code }}</option>@endforeach</select></div>
                <div><label class="text-xs font-black uppercase tracking-widest text-slate-500">Wahana</label><select name="practice_domain_id" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"><option value="">Semua</option>@foreach($domains as $domain)<option value="{{ $domain->id }}" @selected(($filters['practice_domain_id'] ?? '') == $domain->id)>{{ $domain->name }}</option>@endforeach</select></div>
                <div><label class="text-xs font-black uppercase tracking-widest text-slate-500">Status</label><select name="status" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"><option value="">Semua</option>@foreach(\App\Models\PkpaProgramSite::STATUSES as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ str($status)->replace('_', ' ')->headline() }}</option>@endforeach</select></div>
                <button class="self-end rounded-xl bg-slate-900 px-4 py-2 text-sm font-black text-white">Filter</button>
            </form>
            <a href="{{ route('management.pkpa-program-sites.create') }}" class="rounded-xl bg-cyan-700 px-4 py-2 text-center text-sm font-black text-white">Tambah Tempat</a>
        </div>
    </div>
    <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-black uppercase tracking-widest text-slate-500"><tr><th class="px-4 py-3">Program</th><th class="px-4 py-3">Tempat</th><th class="px-4 py-3">Wahana</th><th class="px-4 py-3">Availability</th><th class="px-4 py-3">Status</th><th class="px-4 py-3 text-right">Aksi</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @forelse($programSites as $programSite)
                    <tr>
                        <td class="px-4 py-4"><div class="font-black text-slate-950">{{ $programSite->program?->code }}</div><div class="text-xs text-slate-500">{{ $programSite->program?->name }}</div></td>
                        <td class="px-4 py-4"><div class="font-black text-slate-950">{{ $programSite->practiceSite?->name }}</div><div class="text-xs text-slate-500">{{ $programSite->practiceSite?->code }} / {{ $programSite->practiceSite?->city ?: '-' }}</div></td>
                        <td class="px-4 py-4">{{ $programSite->practiceDomain?->name ?: '-' }}<br><span class="text-xs text-slate-500">{{ $programSite->practiceDomainOption?->name ?: 'Tanpa subjenis' }}</span></td>
                        <td class="px-4 py-4"><span class="font-bold">{{ $programSite->availability_periods_count }}</span><div class="text-xs text-slate-500">Periode tersedia</div></td>
                        <td class="px-4 py-4"><span class="rounded-full px-2 py-1 text-xs font-black {{ $programSite->status === 'active' ? 'bg-emerald-50 text-emerald-700' : ($programSite->status === 'ready' ? 'bg-cyan-50 text-cyan-700' : 'bg-slate-100 text-slate-600') }}">{{ str($programSite->status)->replace('_', ' ')->headline() }}</span><br><span class="mt-1 inline-block text-xs text-slate-500">{{ $programSite->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                        <td class="px-4 py-4 text-right"><a href="{{ route('management.pkpa-program-sites.show', $programSite) }}" class="rounded-lg border border-cyan-200 px-3 py-1.5 text-xs font-bold text-cyan-700">Kelola</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-10 text-center text-slate-500">Belum ada tempat tersedia untuk program PKPA.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-200 px-4 py-3">{{ $programSites->links() }}</div>
    </div>
</div>
@endsection
