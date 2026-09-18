@extends('layouts.app')
@section('title', 'Antrean Validasi Logbook')
@section('page_title', 'Antrean Validasi Logbook')
@section('content')
@php
    $studentCount = $assignments->count();
    $forwardedCount = $assignments->sum(fn ($assignment) => $assignment->rotationRuns->sum(fn ($run) => $run->logbookEntries->whereIn('status', ['field_approved', 'approved'])->count()));
    $runGroups = $readyRuns->getCollection()->groupBy(fn ($run) => $run->practice_domain_id ?: 'lainnya');
@endphp
<div class="space-y-5">
    <section class="grid gap-3 md:grid-cols-3">
        <article class="rounded-2xl border border-cyan-100 bg-white p-5 shadow-sm"><p class="text-xs font-black uppercase tracking-widest text-slate-500">Mahasiswa PKPA</p><p class="mt-2 text-3xl font-black text-slate-950">{{ $studentCount }}</p><p class="mt-1 text-sm text-slate-500">Lihat semua aktivitas dari menu Operasional PKPA.</p></article>
        <article class="rounded-2xl border border-amber-100 bg-white p-5 shadow-sm"><p class="text-xs font-black uppercase tracking-widest text-amber-700">Perlu Validasi Preseptor</p><p class="mt-2 text-3xl font-black text-amber-700">{{ $pendingLogbookCount }}</p><p class="mt-1 text-sm text-slate-500">Logbook yang sudah dikirim mahasiswa.</p></article>
        <article class="rounded-2xl border border-emerald-100 bg-white p-5 shadow-sm"><p class="text-xs font-black uppercase tracking-widest text-emerald-700">Diteruskan</p><p class="mt-2 text-3xl font-black text-emerald-700">{{ $forwardedCount }}</p><p class="mt-1 text-sm text-slate-500">Sudah disetujui dan diteruskan ke Pembimbing Dalam.</p></article>
    </section>

    <section class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        <div class="flex flex-col gap-4 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div><h2 class="text-lg font-black text-slate-950">Logbook Perlu Validasi</h2><p class="mt-1 text-sm text-slate-500">Draf mahasiswa tidak pernah muncul di sini. Kiriman dikelompokkan per mahasiswa agar mudah diperiksa berurutan.</p></div>
            <a href="{{ route('field-supervisor.pkpa-operations.index') }}" class="inline-flex min-h-10 items-center justify-center rounded-xl border border-cyan-200 px-4 py-2 text-sm font-bold text-cyan-800">Buka Operasional PKPA</a>
        </div>
        <div class="space-y-4 bg-slate-50/70 p-4 sm:p-5">
            @forelse($runGroups as $domainId => $domainRuns)
            @php
                $domain = $domainRuns->first()?->practiceDomain;
            @endphp
            <x-pkpa.domain-group :name="$domain?->name ?? 'Wahana lainnya'" :code="$domain?->code" :count="$domainRuns->count()" :anchor="'wahana-'.$domainId">
            <div class="space-y-4">
            @foreach($domainRuns as $run)
                <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <header class="flex flex-col gap-3 border-b border-slate-100 bg-slate-50 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                        <div class="min-w-0">
                            <p class="truncate text-lg font-black text-slate-950">{{ $run->studentDisplayName() }}</p>
                            <p class="mt-1 text-sm text-slate-500">{{ $run->studentDisplaySecondary() }} · {{ $run->practiceDomain?->name }} · {{ $run->practiceSite?->name }}</p>
                        </div>
                        <span class="w-fit shrink-0 rounded-full bg-amber-50 px-3 py-1.5 text-xs font-bold text-amber-700">{{ $run->pending_logbooks_count }} menunggu validasi</span>
                    </header>
                    <div class="divide-y divide-slate-100">
                        @foreach($run->logbookEntries as $entry)
                            <div class="flex flex-col gap-3 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2"><p class="font-bold text-slate-950">{{ $entry->title }}</p><span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-bold text-amber-700">Perlu validasi</span></div>
                                    <p class="mt-1 text-sm text-slate-500">{{ optional($entry->entry_date)->translatedFormat('d M Y') }}</p>
                                </div>
                                <a href="{{ route('field-supervisor.pkpa-operations.show', ['run' => $run, 'logbook' => $entry->id]) }}" class="inline-flex min-h-10 shrink-0 items-center justify-center rounded-xl bg-cyan-700 px-4 py-2 text-sm font-bold text-white">Periksa & Validasi</a>
                            </div>
                        @endforeach
                    </div>
                </article>
            @endforeach
            </div>
            </x-pkpa.domain-group>
            @empty
                <div class="px-5 py-12 text-center"><p class="text-base font-bold text-slate-700">Belum ada logbook yang perlu divalidasi.</p><p class="mt-1 text-sm text-slate-500">Anda tetap dapat memantau presensi dan status seluruh mahasiswa dari Operasional PKPA.</p><a href="{{ route('field-supervisor.pkpa-operations.index') }}" class="mt-4 inline-flex min-h-10 items-center justify-center rounded-xl border border-cyan-200 px-4 py-2 text-sm font-bold text-cyan-800">Buka Operasional PKPA</a></div>
            @endforelse
        </div>
        @if($readyRuns->hasPages())<div class="border-t border-slate-100 px-5 py-4">{{ $readyRuns->links() }}</div>@endif
    </section>
</div>
@endsection
