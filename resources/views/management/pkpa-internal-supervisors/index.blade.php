@extends('layouts.app')
@section('title', 'Pembimbing Dalam PKPA - '.config('app.name'))
@section('page_title', 'Pembimbing Dalam PKPA')
@section('content')
@php
    $statusLabels = ['draft' => 'Draf', 'active' => 'Aktif', 'inactive' => 'Nonaktif', 'suspended' => 'Ditangguhkan', 'expired' => 'Kedaluwarsa'];
@endphp
<div class="space-y-5">
    @if(session('status'))<div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-700">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $errors->first() }}</div>@endif

    <section class="rounded-lg bg-white shadow-sm ring-1 ring-slate-200">
        <div class="flex flex-col gap-4 border-b border-slate-200 px-5 py-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="text-lg font-black text-slate-950">Daftar Pembimbing Dalam</h2>
                <p class="mt-1 text-sm text-slate-600">Setiap Pembimbing Dalam aktif berlaku untuk seluruh lima wahana pada program yang dipilih.</p>
            </div>
            @if($selectedProgram)
                <form method="POST" action="{{ route('management.pkpa-internal-supervisors.store') }}">
                    @csrf
                    <input type="hidden" name="pkpa_program_id" value="{{ $selectedProgram->id }}">
                    <input type="hidden" name="status" value="active">
                    <button class="rounded-lg bg-cyan-700 px-4 py-2.5 text-sm font-black text-white">Sinkronkan Semua dari Core</button>
                </form>
            @endif
        </div>

        <form method="GET" class="grid gap-3 px-5 py-4 md:grid-cols-2 xl:grid-cols-[minmax(260px,1fr)_220px_180px_auto]">
            <div><label class="text-xs font-black uppercase tracking-widest text-slate-500">Cari Pembimbing</label><input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Nama, email, atau ID Core" class="mt-1 h-11 w-full rounded-lg border border-slate-300 px-3 text-sm"></div>
            <div><label class="text-xs font-black uppercase tracking-widest text-slate-500">Program</label><select name="program_id" class="mt-1 h-11 w-full rounded-lg border border-slate-300 px-3 text-sm">@forelse($programs as $program)<option value="{{ $program->id }}" @selected(($filters['program_id'] ?? '') == $program->id)>{{ $program->code }}</option>@empty<option value="">Belum ada program</option>@endforelse</select></div>
            <div><label class="text-xs font-black uppercase tracking-widest text-slate-500">Status</label><select name="status" class="mt-1 h-11 w-full rounded-lg border border-slate-300 px-3 text-sm"><option value="all" @selected(($filters['status'] ?? '') === 'all')>Semua status</option>@foreach(\App\Models\PkpaInternalSupervisorEligibility::STATUSES as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $statusLabels[$status] }}</option>@endforeach</select></div>
            <div class="flex items-end gap-2"><button class="h-11 rounded-lg bg-slate-900 px-4 text-sm font-black text-white">Terapkan</button>@if(filled($filters['q'] ?? null) || ($filters['status'] ?? 'active') !== 'active')<a href="{{ route('management.pkpa-internal-supervisors.index', ['program_id' => $selectedProgram?->id]) }}" class="flex h-11 items-center rounded-lg border border-slate-300 px-3 text-sm font-bold text-slate-600">Reset</a>@endif</div>
        </form>
    </section>

    <section class="grid gap-3 sm:grid-cols-3">
        <div class="border-l-4 border-cyan-500 bg-white px-4 py-3 shadow-sm ring-1 ring-slate-200"><p class="text-xs font-black uppercase tracking-widest text-slate-500">Pembimbing Aktif</p><p class="mt-1 text-2xl font-black text-slate-950">{{ $summary['total'] }}</p></div>
        <div class="border-l-4 border-emerald-500 bg-white px-4 py-3 shadow-sm ring-1 ring-slate-200"><p class="text-xs font-black uppercase tracking-widest text-slate-500">Cakupan Lengkap</p><p class="mt-1 text-2xl font-black text-emerald-700">{{ $summary['complete'] }}</p></div>
        <div class="border-l-4 {{ $summary['incomplete'] ? 'border-amber-500' : 'border-slate-300' }} bg-white px-4 py-3 shadow-sm ring-1 ring-slate-200"><p class="text-xs font-black uppercase tracking-widest text-slate-500">Perlu Dilengkapi</p><p class="mt-1 text-2xl font-black {{ $summary['incomplete'] ? 'text-amber-700' : 'text-slate-500' }}">{{ $summary['incomplete'] }}</p></div>
    </section>

    <section class="grid gap-4 xl:grid-cols-2">
        @forelse($cards as $card)
            @php($eligibility = $card['lead'])
            <article class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-full bg-cyan-50 px-2 py-1 text-xs font-black text-cyan-700">{{ $statusLabels[$eligibility->status] ?? 'Aktif' }}</span>
                            <span class="rounded-full px-2 py-1 text-xs font-black {{ $eligibility->core_account_status_snapshot === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">{{ $eligibility->core_account_status_snapshot === 'active' ? 'Core aktif' : 'Core perlu diperiksa' }}</span>
                        </div>
                        <h3 class="mt-3 text-lg font-black text-slate-950">{{ $card['display_name'] }}</h3>
                        <p class="mt-1 break-words text-sm text-slate-500">{{ $eligibility->email_snapshot ?: '-' }} <span class="text-slate-300">/</span> Core {{ $eligibility->core_user_id }}</p>
                    </div>
                    <div class="shrink-0 text-left sm:text-right"><p class="text-2xl font-black {{ $card['domain_complete'] ? 'text-emerald-700' : 'text-amber-700' }}">{{ $card['domain_count'] }}/{{ $activeDomains->count() }}</p><p class="text-xs font-bold text-slate-500">wahana aktif</p></div>
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    @foreach($activeDomains as $programDomain)
                        @php($covered = $card['domain_ids']->contains((int) $programDomain->practice_domain_id))
                        <span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $covered ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">{{ $programDomain->practiceDomain?->name }}</span>
                    @endforeach
                </div>

                <dl class="mt-4 grid gap-3 border-t border-slate-100 pt-4 text-sm sm:grid-cols-2">
                    <div><dt class="text-xs font-bold text-slate-500">Batas mahasiswa aktif</dt><dd class="mt-1 font-bold text-slate-800">{{ $eligibility->maximum_active_students ?: 'Tidak dibatasi' }}</dd></div>
                    <div><dt class="text-xs font-bold text-slate-500">Pembaruan Core</dt><dd class="mt-1 font-bold text-slate-800">{{ $eligibility->last_core_synced_at?->diffForHumans() ?: 'Belum pernah' }}</dd></div>
                </dl>

                @if($card['unavailability_periods']->count())
                    <div class="mt-4 flex flex-wrap gap-2 text-xs">@foreach($card['unavailability_periods'] as $period)<span class="rounded-full bg-slate-100 px-3 py-2 text-slate-700">Tidak tersedia: {{ $period->start_date?->format('d M Y') }} - {{ $period->end_date?->format('d M Y') }}</span>@endforeach</div>
                @endif

                <div class="mt-4 flex flex-wrap gap-2 border-t border-slate-100 pt-4">
                    <form method="POST" action="{{ route('management.pkpa-internal-supervisors.sync', $eligibility) }}">@csrf<button class="rounded-lg border border-cyan-200 px-3 py-2 text-xs font-bold text-cyan-700">Perbarui dari Core</button></form>
                    @if($eligibility->status !== 'inactive')<form method="POST" action="{{ route('management.pkpa-internal-supervisors.deactivate', $eligibility) }}" onsubmit="return confirm('Nonaktifkan pembimbing ini untuk seluruh wahana program?')">@csrf<button class="rounded-lg border border-rose-200 px-3 py-2 text-xs font-bold text-rose-700">Nonaktifkan</button></form>@endif
                </div>

                <details class="mt-4 border-t border-slate-100 pt-3">
                    <summary class="cursor-pointer text-sm font-black text-slate-700">Atur ketidaktersediaan</summary>
                    <form method="POST" action="{{ route('management.pkpa-internal-supervisors.unavailability.store', $eligibility) }}" class="mt-3 grid gap-3 sm:grid-cols-2">
                        @csrf
                        <div><label class="text-xs font-bold text-slate-500">Mulai</label><input type="date" name="start_date" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
                        <div><label class="text-xs font-bold text-slate-500">Selesai</label><input type="date" name="end_date" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
                        <div class="sm:col-span-2"><label class="text-xs font-bold text-slate-500">Alasan</label><input name="reason" required placeholder="Contoh: tugas luar atau cuti" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
                        <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-black text-white sm:col-span-2 sm:justify-self-start">Simpan Ketidaktersediaan</button>
                    </form>
                </details>
            </article>
        @empty
            <div class="rounded-lg bg-white p-10 text-center shadow-sm ring-1 ring-slate-200 xl:col-span-2"><p class="font-bold text-slate-700">Pembimbing tidak ditemukan</p><p class="mt-1 text-sm text-slate-500">Sinkronkan program dari Core atau ubah filter yang digunakan.</p></div>
        @endforelse
    </section>
    {{ $cards->links() }}
</div>
@endsection
