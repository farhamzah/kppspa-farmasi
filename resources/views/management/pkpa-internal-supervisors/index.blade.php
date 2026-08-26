@extends('layouts.app')
@section('title', 'Pembimbing Dalam PKPA - '.config('app.name'))
@section('page_title', 'Pembimbing Dalam PKPA')
@section('content')
@php
    $statusLabels = [
        'draft' => 'Draf',
        'active' => 'Aktif',
        'inactive' => 'Nonaktif',
        'suspended' => 'Ditangguhkan',
        'expired' => 'Kedaluwarsa',
    ];
@endphp
<div class="space-y-5">
    @if(session('status'))<div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-700">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $errors->first() }}</div>@endif
    <div class="rounded-2xl border border-cyan-100 bg-cyan-50/70 p-4 text-sm leading-6 text-cyan-950">
        <p class="font-black">Fungsi halaman ini</p>
        <p class="mt-1">Halaman ini menyiapkan pool Pembimbing Dalam per program. Dosen yang sudah aktif di MY PKPA dengan role Pembimbing Dalam akan otomatis disiapkan untuk seluruh wahana aktif pada program, sehingga koordinator tidak perlu mendaftarkan dosen satu per satu per wahana.</p>
    </div>
    <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <div class="flex flex-col gap-3 xl:flex-row xl:items-end xl:justify-between">
            <form method="GET" class="grid flex-1 gap-3 md:grid-cols-2 xl:grid-cols-[1fr_170px_150px_auto]">
                <div><label class="text-xs font-black uppercase tracking-widest text-slate-500">Cari</label><input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Nama atau ID Core" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"></div>
                <div><label class="text-xs font-black uppercase tracking-widest text-slate-500">Program</label><select name="program_id" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"><option value="">Semua</option>@foreach($programs as $program)<option value="{{ $program->id }}" @selected(($filters['program_id'] ?? '') == $program->id)>{{ $program->code }}</option>@endforeach</select></div>
                <div><label class="text-xs font-black uppercase tracking-widest text-slate-500">Status</label><select name="status" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"><option value="">Semua</option>@foreach(\App\Models\PkpaInternalSupervisorEligibility::STATUSES as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $statusLabels[$status] ?? str($status)->replace('_', ' ')->headline() }}</option>@endforeach</select></div>
                <button class="self-end rounded-xl bg-slate-900 px-4 py-2 text-sm font-black text-white">Filter</button>
            </form>
            <a href="{{ route('management.pkpa-internal-supervisors.create') }}" class="rounded-xl bg-cyan-700 px-4 py-2 text-center text-sm font-black text-white">Sinkronkan Program</a>
        </div>
    </div>

    <div class="grid gap-4">
        @forelse($cards as $card)
            @php($eligibility = $card['lead'])
            <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-full bg-cyan-50 px-2 py-1 text-xs font-black text-cyan-700">{{ $statusLabels[$eligibility->status] ?? str($eligibility->status)->replace('_', ' ')->headline() }}</span>
                            <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-black text-slate-600">{{ $eligibility->core_account_status_snapshot === 'active' ? 'Akun Core aktif' : ($eligibility->core_account_status_snapshot === 'inactive' ? 'Akun Core nonaktif' : 'Core belum disinkronkan') }}</span>
                        </div>
                        <h2 class="mt-3 text-xl font-black text-slate-950">{{ $eligibility->name_snapshot ?: $eligibility->core_user_id }}</h2>
                        <p class="text-sm text-slate-500">{{ $eligibility->core_user_id }} / {{ $eligibility->email_snapshot ?: '-' }}</p>
                        <p class="mt-2 text-sm text-slate-600">{{ $eligibility->program?->code }} / berlaku untuk {{ $card['domain_count'] }} wahana aktif: {{ $card['domains']->implode(', ') ?: 'Belum ada wahana aktif' }}</p>
                        <p class="mt-1 text-sm text-slate-600">Maks. mahasiswa aktif {{ $eligibility->maximum_active_students ?: 'tidak dibatasi' }} / maks. mahasiswa per program {{ $eligibility->maximum_students_per_program ?: 'tidak dibatasi' }}</p>
                        <p class="mt-1 text-xs text-slate-500">Sinkronisasi terakhir: {{ $eligibility->last_core_synced_at?->diffForHumans() ?: '-' }} {{ $eligibility->last_core_sync_message ? '/ '.$eligibility->last_core_sync_message : '' }}</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <form method="POST" action="{{ route('management.pkpa-internal-supervisors.sync', $eligibility) }}">@csrf<button class="rounded-xl border border-cyan-200 px-4 py-2 text-sm font-bold text-cyan-700">Sinkronkan dari Core</button></form>
                        @if($eligibility->status !== 'inactive')<form method="POST" action="{{ route('management.pkpa-internal-supervisors.deactivate', $eligibility) }}">@csrf<button class="rounded-xl border border-rose-200 px-4 py-2 text-sm font-bold text-rose-700">Nonaktifkan</button></form>@endif
                    </div>
                </div>
                <form method="POST" action="{{ route('management.pkpa-internal-supervisors.unavailability.store', $eligibility) }}" class="mt-4 grid gap-3 md:grid-cols-[160px_160px_1fr_auto]">
                    @csrf
                    <input type="date" name="start_date" required class="rounded-xl border border-slate-300 px-3 py-2 text-sm">
                    <input type="date" name="end_date" required class="rounded-xl border border-slate-300 px-3 py-2 text-sm">
                    <input name="reason" required placeholder="Alasan tidak tersedia" class="rounded-xl border border-slate-300 px-3 py-2 text-sm">
                    <button class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-black">Blokir</button>
                </form>
                @if($card['unavailability_periods']->count())
                    <div class="mt-3 flex flex-wrap gap-2 text-xs">
                        @foreach($card['unavailability_periods'] as $period)
                            <span class="rounded-full bg-slate-100 px-3 py-2 text-slate-700">{{ $period->start_date?->format('d M Y') }} - {{ $period->end_date?->format('d M Y') }} / {{ $statusLabels[$period->status] ?? str($period->status)->replace('_', ' ')->headline() }}</span>
                        @endforeach
                    </div>
                @endif
            </div>
        @empty
            <div class="rounded-2xl bg-white p-8 text-center text-sm text-slate-500 shadow-sm ring-1 ring-slate-200">Belum ada Pembimbing Dalam yang disiapkan untuk filter ini.</div>
        @endforelse
    </div>
    {{ $eligibilities->links() }}
</div>
@endsection
