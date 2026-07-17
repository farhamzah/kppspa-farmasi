@extends('layouts.app')
@section('title', 'Program PKPA - '.config('app.name'))
@section('page_title', 'Program PKPA')
@section('content')
<div class="space-y-5">
    @if($errors->any())<div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $errors->first() }}</div>@endif
    <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <form method="GET" class="grid flex-1 gap-3 md:grid-cols-[1fr_160px_150px_120px_auto]">
                <div><label class="text-xs font-black uppercase tracking-widest text-slate-500">Cari</label><input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Kode, nama, angkatan" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"></div>
                <div><label class="text-xs font-black uppercase tracking-widest text-slate-500">Tahun</label><input name="academic_year" value="{{ $filters['academic_year'] ?? '' }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"></div>
                <div><label class="text-xs font-black uppercase tracking-widest text-slate-500">Status</label><select name="status" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"><option value="">Semua</option>@foreach(\App\Models\PkpaProgram::STATUSES as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst($status) }}</option>@endforeach</select></div>
                <div><label class="text-xs font-black uppercase tracking-widest text-slate-500">Aktif</label><select name="active" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"><option value="">Semua</option><option value="1" @selected(($filters['active'] ?? '') === '1')>Ya</option><option value="0" @selected(($filters['active'] ?? '') === '0')>Tidak</option></select></div>
                <button class="self-end rounded-xl bg-slate-900 px-4 py-2 text-sm font-black text-white">Filter</button>
            </form>
            <a href="{{ route('management.pkpa-programs.create') }}" class="rounded-xl bg-cyan-700 px-4 py-2 text-center text-sm font-black text-white">Tambah Program</a>
        </div>
    </div>
    <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-black uppercase tracking-widest text-slate-500"><tr><th class="px-4 py-3">Program</th><th class="px-4 py-3">Tahun</th><th class="px-4 py-3">Periode</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Kelengkapan</th><th class="px-4 py-3">Wahana</th><th class="px-4 py-3 text-right">Aksi</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @forelse($programs as $program)
                    <tr>
                        <td class="px-4 py-4"><div class="font-black text-slate-950">{{ $program->name }}</div><div class="text-xs text-slate-500">{{ $program->code }} {{ $program->cohort_name ? '· '.$program->cohort_name : '' }}</div></td>
                        <td class="px-4 py-4">{{ $program->academic_year ?: '-' }}</td>
                        <td class="px-4 py-4">{{ $program->start_date?->format('d M Y') ?: '-' }}<br><span class="text-xs text-slate-500">{{ $program->end_date?->format('d M Y') ?: '-' }}</span></td>
                        <td class="px-4 py-4"><span class="rounded-full px-2 py-1 text-xs font-black {{ $program->statusBadgeClass() }}">{{ $program->statusLabel() }}</span></td>
                        <td class="px-4 py-4">{{ $program->completionLabel() }}</td>
                        <td class="px-4 py-4">{{ $program->active_domains_count }}</td>
                        <td class="px-4 py-4 text-right"><div class="flex flex-wrap justify-end gap-2"><a href="{{ route('management.pkpa-programs.show', $program) }}" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-bold">Detail</a><a href="{{ route('management.pkpa-programs.configure', $program) }}" class="rounded-lg border border-cyan-200 px-3 py-1.5 text-xs font-bold text-cyan-700">Durasi</a></div></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-10 text-center text-slate-500">Belum ada Program PKPA.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-200 px-4 py-3">{{ $programs->links() }}</div>
    </div>
</div>
@endsection
