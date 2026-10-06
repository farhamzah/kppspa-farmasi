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
    <section class="grid grid-cols-3 gap-2 sm:gap-4" aria-label="Ringkasan pemantauan">
        <article class="min-w-0 border-l-4 border-slate-300 bg-white p-3 sm:p-4">
            <p class="min-h-8 text-xs font-bold text-slate-600">Penempatan Bimbingan</p>
            <p class="mt-1 text-2xl font-black tabular-nums text-slate-950">{{ $totalRuns }}</p>
        </article>
        <article class="min-w-0 border-l-4 border-amber-300 bg-white p-3 sm:p-4">
            <p class="min-h-8 text-xs font-bold text-amber-800">Siap Validasi Akhir</p>
            <p class="mt-1 text-2xl font-black tabular-nums text-amber-800">{{ $readyLogbookCount }}</p>
        </article>
        <article class="min-w-0 border-l-4 border-emerald-300 bg-white p-3 sm:p-4">
            <p class="min-h-8 text-xs font-bold text-emerald-700">Validasi Selesai</p>
            <p class="mt-1 text-2xl font-black tabular-nums text-emerald-700">{{ $completedLogbookCount }}</p>
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

    @if($tab !== 'overview')
        <form method="GET" class="grid gap-3 border-y border-slate-200 bg-white p-4 sm:grid-cols-2 xl:grid-cols-[minmax(180px,1.5fr)_minmax(160px,1fr)_minmax(150px,1fr)_minmax(150px,1fr)_auto]">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <label class="grid min-w-0 gap-2 text-sm font-semibold">Mahasiswa atau tempat<input name="q" value="{{ request('q') }}" placeholder="Cari nama atau tempat praktik" class="h-11 w-full min-w-0 border border-slate-300 bg-white px-3 text-sm"></label>
            <label class="grid min-w-0 gap-2 text-sm font-semibold">Wahana<select name="domain" class="h-11 w-full min-w-0 border border-slate-300 bg-white px-3 text-sm"><option value="">Semua wahana</option>@foreach($runs->pluck('practiceDomain')->filter()->unique('id') as $domain)<option value="{{ $domain->id }}" @selected(request('domain') == $domain->id)>{{ $domain->name }}</option>@endforeach</select></label>
            <label class="grid min-w-0 gap-2 text-sm font-semibold">Dari tanggal<input type="date" name="date_from" value="{{ request('date_from') }}" class="h-11 w-full min-w-0 border border-slate-300 bg-white px-3 text-sm"></label>
            <label class="grid min-w-0 gap-2 text-sm font-semibold">Sampai tanggal<input type="date" name="date_to" value="{{ request('date_to') }}" class="h-11 w-full min-w-0 border border-slate-300 bg-white px-3 text-sm"></label>
            <div class="flex items-end gap-2"><button class="h-11 rounded-lg bg-cyan-700 px-4 text-sm font-bold text-white hover:bg-cyan-800">Terapkan</button><a href="{{ route('internal-supervisor.pkpa-operations.index', ['tab' => $tab]) }}" class="inline-flex h-11 items-center rounded-lg border border-slate-300 px-3 text-sm font-semibold hover:bg-slate-50">Reset</a></div>
        </form>
    @endif

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
                            <p class="mt-2 text-sm text-slate-500">{{ $run->logbookEntries->whereIn('status', \App\Models\PkpaLogbookEntry::internalReviewStatuses())->count() }} siap divalidasi · {{ $run->logbookEntries->where('status', 'internal_approved')->count() }} selesai</p>
                            <a href="{{ route('internal-supervisor.pkpa-operations.show', $run) }}" class="mt-4 inline-flex min-h-10 items-center justify-center rounded-lg bg-cyan-700 px-4 py-2 text-sm font-bold text-white">Buka Detail</a>
                        </article>
                    @endforeach
                </div>
            </x-pkpa.domain-group>
        @empty
            <div class="rounded-lg bg-white p-6 text-sm text-slate-500 shadow-sm ring-1 ring-sky-100">Belum ada mahasiswa yang dapat dipantau.</div>
        @endforelse
    @elseif($tab === 'validation')
        @if($errors->any())<p class="rounded-lg bg-rose-50 p-4 text-sm text-rose-800">{{ $errors->first() }}</p>@endif
        <x-pkpa.bulk-validation :action="route('internal-supervisor.pkpa-logbooks.bulk-approve')" />
        @include('shared.pkpa-logbook-workspace-list', [
            'logbookEntries' => $logbookEntries,
            'routePrefix' => 'internal-supervisor',
            'actionLabel' => 'Lihat & Validasi',
            'bulkSelection' => true,
            'emptyTitle' => 'Belum ada logbook siap validasi akhir.',
            'emptyDescription' => 'Logbook yang dikirim mahasiswa akan tampil di sini.',
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
