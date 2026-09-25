@extends('layouts.app')
@section('title', 'Preseptor - '.config('app.name'))
@section('page_title', 'Preseptor')
@section('content')
@php
    $statusLabels = ['active' => 'Aktif', 'inactive' => 'Nonaktif', 'suspended' => 'Ditangguhkan'];
@endphp
<div class="space-y-5">
    @if(session('status'))<div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-700">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $errors->first() }}</div>@endif

    <section class="rounded-lg bg-white shadow-sm ring-1 ring-slate-200">
        <div class="flex flex-col gap-4 border-b border-slate-200 px-5 py-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="text-lg font-black text-slate-950">Preseptor per Wahana</h2>
                <p class="mt-1 text-sm text-slate-600">Preseptor dikelompokkan menurut wahana dan tempat praktik agar penanggung jawab setiap lokasi mudah dikenali.</p>
            </div>
            <a href="{{ route('management.pkpa-program-sites.create') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-cyan-700 px-4 py-2 text-sm font-black text-white">Tambah Tempat PKPA</a>
        </div>

        <form method="GET" class="grid gap-3 px-5 py-4 md:grid-cols-2 xl:grid-cols-[minmax(210px,1fr)_160px_180px_150px_auto]">
            <div><label class="text-xs font-black uppercase tracking-widest text-slate-500">Cari Preseptor atau Tempat</label><input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Nama, email, Core ID, atau tempat" class="mt-1 h-11 w-full rounded-lg border border-slate-300 px-3 text-sm"></div>
            <div><label class="text-xs font-black uppercase tracking-widest text-slate-500">Program</label><select name="program_id" class="mt-1 h-11 w-full rounded-lg border border-slate-300 px-3 text-sm"><option value="">Semua program</option>@foreach($programs as $program)<option value="{{ $program->id }}" @selected(($filters['program_id'] ?? '') == $program->id)>{{ $program->code }}</option>@endforeach</select></div>
            <div><label class="text-xs font-black uppercase tracking-widest text-slate-500">Wahana</label><select name="practice_domain_id" class="mt-1 h-11 w-full rounded-lg border border-slate-300 px-3 text-sm"><option value="">Semua wahana</option>@foreach($domains as $domain)<option value="{{ $domain->id }}" @selected(($filters['practice_domain_id'] ?? '') == $domain->id)>{{ $domain->name }}</option>@endforeach</select></div>
            <div><label class="text-xs font-black uppercase tracking-widest text-slate-500">Status</label><select name="status" class="mt-1 h-11 w-full rounded-lg border border-slate-300 px-3 text-sm"><option value="all" @selected(($filters['status'] ?? '') === 'all')>Semua status</option>@foreach(\App\Models\PkpaSiteFieldSupervisor::STATUSES as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $statusLabels[$status] ?? str($status)->replace('_', ' ')->headline() }}</option>@endforeach</select></div>
            <div class="flex items-end gap-2"><button class="h-11 rounded-lg bg-slate-900 px-4 text-sm font-black text-white">Terapkan</button>@if(filled($filters['q'] ?? null) || filled($filters['program_id'] ?? null) || filled($filters['practice_domain_id'] ?? null) || ($filters['status'] ?? 'active') !== 'active')<a href="{{ route('management.pkpa-preceptors.index') }}" class="flex h-11 items-center rounded-lg border border-slate-300 px-3 text-sm font-bold text-slate-600">Reset</a>@endif</div>
        </form>
    </section>

    <section class="grid gap-3 sm:grid-cols-2">
        <div class="border-l-4 border-cyan-500 bg-white px-4 py-3 shadow-sm ring-1 ring-slate-200"><p class="text-xs font-black uppercase tracking-widest text-slate-500">Preseptor Ditampilkan</p><p class="mt-1 text-2xl font-black text-slate-950">{{ $supervisors->total() }}</p></div>
        <div class="border-l-4 border-emerald-500 bg-white px-4 py-3 shadow-sm ring-1 ring-slate-200"><p class="text-xs font-black uppercase tracking-widest text-slate-500">Kelompok Wahana</p><p class="mt-1 text-2xl font-black text-emerald-700">{{ $groupedSupervisors->count() }}</p></div>
    </section>

    @forelse($groupedSupervisors as $cards)
        @php($domain = $cards->first()['domain'])
        <section class="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-slate-200">
            <header class="flex flex-col gap-2 border-b border-cyan-100 bg-cyan-50/70 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-xs font-black uppercase tracking-widest text-cyan-700">Wahana PKPA</p>
                    <h2 class="mt-1 text-xl font-black text-slate-950">{{ $domain?->name ?? 'Belum Terhubung ke Wahana' }}</h2>
                </div>
                <span class="w-fit rounded-full bg-white px-3 py-1 text-xs font-black text-cyan-800 ring-1 ring-cyan-200">{{ $cards->count() }} preseptor</span>
            </header>

            <div class="grid divide-y divide-slate-100 lg:grid-cols-2 lg:divide-x lg:divide-y-0">
                @foreach($cards as $card)
                    @php($supervisor = $card['supervisor'])
                    @php($firstProgramSite = $card['program_sites']->first())
                    <article class="p-5 {{ $loop->index > 1 ? 'lg:border-t lg:border-slate-100' : '' }}">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0">
                                <p class="text-xs font-black uppercase tracking-widest text-slate-500">Tempat Praktik</p>
                                <h3 class="mt-1 text-lg font-black text-slate-950">{{ $supervisor->practiceSite?->name ?: '-' }}</h3>
                                <p class="mt-1 text-xs text-slate-500">{{ $supervisor->practiceSite?->code ?: '-' }}{{ $supervisor->practiceSite?->city ? ' / '.$supervisor->practiceSite->city : '' }}</p>
                            </div>
                            <span class="w-fit rounded-full px-2.5 py-1 text-xs font-black {{ $supervisor->status === 'active' ? 'bg-emerald-50 text-emerald-700' : ($supervisor->status === 'inactive' ? 'bg-slate-100 text-slate-600' : 'bg-amber-50 text-amber-700') }}">{{ $statusLabels[$supervisor->status] ?? str($supervisor->status)->replace('_', ' ')->headline() }}</span>
                        </div>

                        <div class="mt-4 rounded-lg bg-slate-50 p-4">
                            <p class="text-xs font-black uppercase tracking-widest text-slate-500">Preseptor</p>
                            <p class="mt-1 font-black text-slate-950">{{ $supervisor->display_name }}</p>
                            <p class="mt-1 break-words text-xs text-slate-500">{{ $supervisor->email_snapshot ?: '-' }} <span class="text-slate-300">/</span> Core {{ $supervisor->core_user_id }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ $supervisor->position_title ?: 'Jabatan belum diisi' }}</p>
                        </div>

                        <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-3">
                            <div><dt class="text-xs font-bold text-slate-500">Program</dt><dd class="mt-1 font-bold text-slate-800">{{ $card['program_sites']->pluck('program.code')->filter()->unique()->implode(', ') ?: '-' }}</dd></div>
                            <div><dt class="text-xs font-bold text-slate-500">Periode Efektif</dt><dd class="mt-1 font-bold text-slate-800">{{ $supervisor->effective_start_date?->format('d M Y') ?: 'Mulai bebas' }}</dd><dd class="text-xs text-slate-500">s.d. {{ $supervisor->effective_end_date?->format('d M Y') ?: 'tanpa batas' }}</dd></div>
                            <div><dt class="text-xs font-bold text-slate-500">Beban Aktif</dt><dd class="mt-1 font-bold text-slate-800">{{ $supervisor->maximum_active_students ?: 'Tidak dibatasi' }}</dd><dd class="text-xs text-slate-500">{{ $supervisor->is_primary_contact ? 'Kontak utama' : 'Kontak pendamping' }}</dd></div>
                        </dl>

                        <div class="mt-4 flex items-center justify-between gap-3 border-t border-slate-100 pt-4">
                            <span class="text-xs font-bold {{ $supervisor->core_account_status_snapshot === 'active' ? 'text-emerald-700' : 'text-amber-700' }}">Core {{ $supervisor->core_account_status_snapshot === 'active' ? 'aktif' : 'perlu diperiksa' }}</span>
                            @if($firstProgramSite)<a href="{{ route('management.pkpa-preceptors.show', $firstProgramSite) }}" class="inline-flex min-h-10 items-center justify-center rounded-lg border border-cyan-200 px-3 py-2 text-sm font-bold text-cyan-700">Kelola Preseptor</a>@endif
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    @empty
        <section class="rounded-lg bg-white p-10 text-center shadow-sm ring-1 ring-slate-200"><p class="font-bold text-slate-700">Preseptor tidak ditemukan</p><p class="mt-1 text-sm text-slate-500">Tambahkan preseptor pada tempat PKPA atau ubah filter yang digunakan.</p></section>
    @endforelse

    {{ $supervisors->links() }}
</div>
@endsection
