@extends('layouts.app')
@section('title', 'Preseptor - '.config('app.name'))
@section('page_title', 'Preseptor')
@section('content')
<div class="space-y-5">
    @if(session('status'))<div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-700">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $errors->first() }}</div>@endif

    <div class="rounded-2xl border border-cyan-100 bg-cyan-50/70 px-4 py-3 text-sm text-cyan-900">
        Daftar ini menampilkan siapa saja Preseptor yang sudah terhubung ke tempat PKPA, lengkap dengan tempat, program, wahana, dan status aktifnya.
    </div>

    <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <div class="flex flex-col gap-3 xl:flex-row xl:items-end xl:justify-between">
            <form method="GET" class="grid flex-1 gap-3 md:grid-cols-2 xl:grid-cols-[1fr_170px_190px_150px_auto]">
                <div><label class="text-xs font-black uppercase tracking-widest text-slate-500">Cari</label><input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Nama, email, Core ID, tempat" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"></div>
                <div><label class="text-xs font-black uppercase tracking-widest text-slate-500">Program</label><select name="program_id" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"><option value="">Semua</option>@foreach($programs as $program)<option value="{{ $program->id }}" @selected(($filters['program_id'] ?? '') == $program->id)>{{ $program->code }}</option>@endforeach</select></div>
                <div><label class="text-xs font-black uppercase tracking-widest text-slate-500">Wahana</label><select name="practice_domain_id" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"><option value="">Semua</option>@foreach($domains as $domain)<option value="{{ $domain->id }}" @selected(($filters['practice_domain_id'] ?? '') == $domain->id)>{{ $domain->name }}</option>@endforeach</select></div>
                <div><label class="text-xs font-black uppercase tracking-widest text-slate-500">Status</label><select name="status" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"><option value="">Semua</option>@foreach(\App\Models\PkpaSiteFieldSupervisor::STATUSES as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ str($status)->replace('_', ' ')->headline() }}</option>@endforeach</select></div>
                <button class="self-end rounded-xl bg-slate-900 px-4 py-2 text-sm font-black text-white">Filter</button>
            </form>
            <a href="{{ route('management.pkpa-program-sites.create') }}" class="rounded-xl bg-cyan-700 px-4 py-2 text-center text-sm font-black text-white">Tambah Tempat PKPA</a>
        </div>
    </div>

    <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-black uppercase tracking-widest text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Preseptor</th>
                        <th class="px-4 py-3">Tempat</th>
                        <th class="px-4 py-3">Program / Wahana</th>
                        <th class="px-4 py-3">Efektif</th>
                        <th class="px-4 py-3">Beban</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                @forelse($supervisors as $supervisor)
                    @php
                        $programSites = $supervisor->practiceSite?->programSites
                            ?->filter(function ($programSite) use ($filters) {
                                if (filled($filters['program_id'] ?? null) && (int) $programSite->pkpa_program_id !== (int) $filters['program_id']) {
                                    return false;
                                }

                                if (filled($filters['practice_domain_id'] ?? null) && (int) $programSite->practice_domain_id !== (int) $filters['practice_domain_id']) {
                                    return false;
                                }

                                return true;
                            })
                            ->values() ?? collect();
                        $firstProgramSite = $programSites->first();
                    @endphp
                    <tr>
                        <td class="px-4 py-4 align-top">
                            <div class="font-black text-slate-950">{{ $supervisor->display_name }}</div>
                            <div class="text-xs text-slate-500">{{ $supervisor->core_user_id }} / {{ $supervisor->email_snapshot ?: '-' }}</div>
                            <div class="mt-1 text-xs text-slate-500">{{ $supervisor->position_title ?: 'Jabatan belum diisi' }}</div>
                        </td>
                        <td class="px-4 py-4 align-top">
                            <div class="font-black text-slate-950">{{ $supervisor->practiceSite?->name ?: '-' }}</div>
                            <div class="text-xs text-slate-500">{{ $supervisor->practiceSite?->code ?: '-' }} / {{ $supervisor->practiceSite?->city ?: '-' }}</div>
                        </td>
                        <td class="px-4 py-4 align-top">
                            @forelse($programSites as $programSite)
                                <div class="@if(! $loop->first) mt-2 @endif">
                                    <div class="font-semibold text-slate-900">{{ $programSite->program?->code ?: '-' }}</div>
                                    <div class="text-xs text-slate-500">{{ $programSite->practiceDomain?->name ?: '-' }}{{ $programSite->practiceDomainOption ? ' / '.$programSite->practiceDomainOption->name : '' }}</div>
                                </div>
                            @empty
                                <span class="text-slate-500">Belum terhubung ke program aktif.</span>
                            @endforelse
                        </td>
                        <td class="px-4 py-4 align-top">
                            <div class="font-semibold text-slate-900">{{ $supervisor->effective_start_date?->format('d M Y') ?: '-' }}</div>
                            <div class="text-xs text-slate-500">{{ $supervisor->effective_end_date?->format('d M Y') ?: 'Tanpa batas akhir' }}</div>
                        </td>
                        <td class="px-4 py-4 align-top">
                            <div class="font-semibold text-slate-900">{{ $supervisor->maximum_active_students ?: 'Tidak dibatasi' }}</div>
                            <div class="text-xs text-slate-500">{{ $supervisor->is_primary_contact ? 'Kontak utama' : 'Kontak pendamping' }}</div>
                        </td>
                        <td class="px-4 py-4 align-top">
                            <span class="rounded-full px-2 py-1 text-xs font-black {{ $supervisor->status === 'active' ? 'bg-emerald-50 text-emerald-700' : ($supervisor->status === 'inactive' ? 'bg-slate-100 text-slate-600' : 'bg-amber-50 text-amber-700') }}">{{ str($supervisor->status)->replace('_', ' ')->headline() }}</span>
                            <div class="mt-1 text-xs text-slate-500">Core: {{ str($supervisor->core_account_status_snapshot ?: 'unknown')->headline() }}</div>
                        </td>
                        <td class="px-4 py-4 text-right align-top">
                            @if($firstProgramSite)
                                <a href="{{ route('management.pkpa-preceptors.show', $firstProgramSite) }}" class="rounded-lg border border-cyan-200 px-3 py-1.5 text-xs font-bold text-cyan-700">Kelola</a>
                            @else
                                <span class="text-xs text-slate-400">-</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-10 text-center text-slate-500">Belum ada preseptor yang terhubung ke tempat PKPA.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-200 px-4 py-3">{{ $supervisors->links() }}</div>
    </div>
</div>
@endsection
