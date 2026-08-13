@extends('layouts.app')
@section('title', 'Peserta PKPA - '.config('app.name'))
@section('page_title', 'Peserta PKPA')
@section('content')
<div class="space-y-5">
    @if(session('status'))<div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-700">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $errors->first() }}</div>@endif
    <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <div class="flex flex-col gap-3 xl:flex-row xl:items-end xl:justify-between">
            <form method="GET" class="grid flex-1 gap-3 md:grid-cols-3 xl:grid-cols-[1fr_180px_150px_150px_150px_150px_auto]">
                <div><label class="text-xs font-black uppercase tracking-widest text-slate-500">Cari</label><input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="NPM, nama, Core ID" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"></div>
                <div><label class="text-xs font-black uppercase tracking-widest text-slate-500">Program</label><select name="program_id" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"><option value="">Semua</option>@foreach($programs as $program)<option value="{{ $program->id }}" @selected(($filters['program_id'] ?? '') == $program->id)>{{ $program->code }}</option>@endforeach</select></div>
                <div><label class="text-xs font-black uppercase tracking-widest text-slate-500">Status</label><select name="status" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"><option value="">Semua</option>@foreach(\App\Models\PkpaEnrollment::STATUSES as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ str($status)->replace('_', ' ')->headline() }}</option>@endforeach</select></div>
                <div><label class="text-xs font-black uppercase tracking-widest text-slate-500">Core</label><select name="core_account_status" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"><option value="">Semua</option><option value="active" @selected(($filters['core_account_status'] ?? '') === 'active')>Aktif</option><option value="inactive" @selected(($filters['core_account_status'] ?? '') === 'inactive')>Nonaktif</option></select></div>
                <div><label class="text-xs font-black uppercase tracking-widest text-slate-500">Kelompok</label><select name="grouped" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"><option value="">Semua</option><option value="yes" @selected(($filters['grouped'] ?? '') === 'yes')>Sudah</option><option value="no" @selected(($filters['grouped'] ?? '') === 'no')>Belum</option></select></div>
                <div><label class="text-xs font-black uppercase tracking-widest text-slate-500">Sinkronisasi</label><select name="sync" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"><option value="">Semua</option><option value="problem" @selected(($filters['sync'] ?? '') === 'problem')>Bermasalah</option></select></div>
                <button class="self-end rounded-xl bg-slate-900 px-4 py-2 text-sm font-black text-white">Filter</button>
            </form>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('management.pkpa-enrollments.import') }}" class="rounded-xl border border-cyan-200 px-4 py-2 text-sm font-black text-cyan-700">Import</a>
                <a href="{{ route('management.pkpa-enrollments.create') }}" class="rounded-xl bg-cyan-700 px-4 py-2 text-sm font-black text-white">Tambah Peserta</a>
            </div>
        </div>
    </div>
    <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-black uppercase tracking-widest text-slate-500"><tr><th class="px-4 py-3">Mahasiswa</th><th class="px-4 py-3">Program</th><th class="px-4 py-3">Core</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Kelompok</th><th class="px-4 py-3">Kewajiban</th><th class="px-4 py-3 text-right">Aksi</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @forelse($enrollments as $enrollment)
                    <tr>
                        <td class="px-4 py-4"><div class="font-black text-slate-950">{{ $enrollment->student_name_snapshot ?: '-' }}</div><div class="text-xs text-slate-500">{{ $enrollment->student_number ?: '-' }} / {{ $enrollment->core_user_id }}</div></td>
                        <td class="px-4 py-4"><span class="font-bold">{{ $enrollment->program?->code }}</span><div class="text-xs text-slate-500">{{ $enrollment->program?->name }}</div></td>
                        <td class="px-4 py-4"><span class="rounded-full px-2 py-1 text-xs font-black {{ $enrollment->core_account_status_snapshot === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">{{ $enrollment->core_account_status_snapshot ?: 'Belum sync' }}</span><div class="mt-1 text-xs text-slate-500">{{ $enrollment->last_core_synced_at?->diffForHumans() ?: '-' }}</div></td>
                        <td class="px-4 py-4">{{ $enrollment->statusLabel() }}</td>
                        <td class="px-4 py-4">{{ $enrollment->activeGroupMembership?->group?->code ?: 'Belum' }}</td>
                        <td class="px-4 py-4">{{ $enrollment->requirementSummary() }}</td>
                        <td class="px-4 py-4 text-right"><div class="flex flex-wrap justify-end gap-2"><a href="{{ route('management.pkpa-enrollments.show', $enrollment) }}" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-bold">Detail</a><form method="POST" action="{{ route('management.pkpa-enrollments.sync', $enrollment) }}">@csrf<button class="rounded-lg border border-cyan-200 px-3 py-1.5 text-xs font-bold text-cyan-700">Sinkronkan</button></form></div></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-10 text-center text-slate-500">Belum ada peserta PKPA.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-200 px-4 py-3">{{ $enrollments->links() }}</div>
    </div>
</div>
@endsection
