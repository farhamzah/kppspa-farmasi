@extends('layouts.app')
@section('title', 'Pemantauan Mahasiswa')
@section('page_title', 'Pemantauan Mahasiswa')
@section('content')
@php
    $totalRuns = $runs->count();
    $runGroups = $runs->groupBy(fn ($run) => $run->practice_domain_id ?: 'lainnya');
    $tabs = [
        'overview' => ['label' => 'Ringkasan', 'count' => null],
        'validation' => ['label' => 'Perlu Validasi', 'count' => $readyLogbookCount],
        'history' => ['label' => 'Riwayat Logbook', 'count' => $historyLogbookCount],
    ];
@endphp
<div class="space-y-5">
    <section class="grid gap-4 md:grid-cols-3">
        <article class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <p class="text-xs font-black uppercase tracking-widest text-slate-500">Mahasiswa Bimbingan</p>
            <p class="mt-3 text-3xl font-black text-slate-950">{{ $totalRuns }}</p>
            <p class="mt-1 text-sm text-slate-500">Penempatan aktif yang menjadi tanggung jawab Anda.</p>
        </article>
        <article class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-amber-100">
            <p class="text-xs font-black uppercase tracking-widest text-amber-700">Siap Validasi Akhir</p>
            <p class="mt-3 text-3xl font-black text-amber-700">{{ $readyLogbookCount }}</p>
            <p class="mt-1 text-sm text-slate-500">Sudah disetujui Preseptor dan menunggu keputusan Anda.</p>
        </article>
        <article class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-emerald-100">
            <p class="text-xs font-black uppercase tracking-widest text-emerald-700">Validasi Selesai</p>
            <p class="mt-3 text-3xl font-black text-emerald-700">{{ $completedLogbookCount }}</p>
            <p class="mt-1 text-sm text-slate-500">Logbook yang telah Anda setujui.</p>
        </article>
    </section>

    <nav class="flex gap-1 overflow-x-auto border-b border-sky-200" aria-label="Tampilan pemantauan mahasiswa">
        @foreach($tabs as $key => $tabItem)
            <a href="{{ route('internal-supervisor.pkpa-operations.index', ['tab' => $key]) }}" @if($tab === $key) aria-current="page" @endif class="inline-flex min-h-11 shrink-0 items-center gap-2 border-b-2 px-4 py-2 text-sm font-bold {{ $tab === $key ? 'border-cyan-700 text-cyan-800' : 'border-transparent text-slate-500 hover:text-slate-800' }}">
                {{ $tabItem['label'] }}
                @if($tabItem['count'] !== null)<span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs">{{ $tabItem['count'] }}</span>@endif
            </a>
        @endforeach
    </nav>

    @if($tab === 'overview')
        @forelse($runGroups as $domainId => $domainRuns)
            @php
                $domain = $domainRuns->first()?->practiceDomain;
            @endphp
            <x-pkpa.domain-group :name="$domain?->name ?? 'Wahana lainnya'" :code="$domain?->code" :count="$domainRuns->count()" :anchor="'wahana-'.$domainId">
                <div class="grid gap-4 lg:grid-cols-2">
                    @foreach($domainRuns as $run)
                        <article class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-sky-100">
                            <h2 class="text-xl font-black text-slate-950">{{ $run->studentDisplayName() }}</h2>
                            <p class="mt-1 text-sm text-slate-500">{{ $run->studentDisplaySecondary() }}</p>
                            <p class="mt-3 text-sm font-semibold text-slate-700">{{ $run->practiceSite?->name }}</p>
                            <p class="mt-2 text-sm text-slate-500">{{ $run->logbookEntries->whereIn('status', ['field_approved', 'approved'])->count() }} siap divalidasi · {{ $run->logbookEntries->where('status', 'internal_approved')->count() }} selesai</p>
                            <a href="{{ route('internal-supervisor.pkpa-operations.show', $run) }}" class="mt-4 inline-flex min-h-10 items-center justify-center rounded-lg bg-cyan-700 px-4 py-2 text-sm font-bold text-white">Buka Detail</a>
                        </article>
                    @endforeach
                </div>
            </x-pkpa.domain-group>
        @empty
            <div class="rounded-lg bg-white p-6 text-sm text-slate-500 shadow-sm ring-1 ring-sky-100">Belum ada mahasiswa yang dapat dipantau.</div>
        @endforelse
    @elseif($tab === 'validation')
        @include('shared.pkpa-logbook-workspace-list', [
            'logbookEntries' => $logbookEntries,
            'routePrefix' => 'internal-supervisor',
            'actionLabel' => 'Lihat & Validasi',
            'emptyTitle' => 'Belum ada logbook siap validasi akhir.',
            'emptyDescription' => 'Logbook akan muncul setelah disetujui oleh Preseptor.',
        ])
    @else
        @include('shared.pkpa-logbook-workspace-list', [
            'logbookEntries' => $logbookEntries,
            'routePrefix' => 'internal-supervisor',
            'actionLabel' => 'Lihat Detail',
            'emptyTitle' => 'Belum ada riwayat logbook.',
            'emptyDescription' => 'Logbook mahasiswa yang telah dikirim akan tampil di sini.',
        ])
    @endif
</div>
@endsection
