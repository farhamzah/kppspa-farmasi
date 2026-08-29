@extends('layouts.app')

@section('title', 'Logbook PKPA - '.config('app.name'))
@section('page_title', 'Logbook PKPA')

@section('content')
<div class="space-y-5">
    <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <p class="text-xs font-black uppercase tracking-widest text-cyan-700">Logbook PKPA</p>
        <h2 class="mt-1 text-2xl font-black text-slate-950">Logbook per Rotasi</h2>
        <p class="mt-2 text-sm text-slate-500">Pilih rotasi yang ingin Anda isi. Entri logbook dibuat dari halaman detail rotasi agar langsung terkait dengan wahana, tempat, dan periode yang benar.</p>
    </section>

    @if($runs->isEmpty())
        <section class="rounded-2xl bg-white p-10 text-center shadow-sm ring-1 ring-slate-200">
            <h2 class="text-xl font-bold text-slate-950">Anda belum memiliki rotasi PKPA</h2>
            <p class="mt-2 text-sm text-slate-500">Logbook akan muncul setelah ada penempatan PKPA yang sudah tersinkron menjadi rotasi.</p>
        </section>
    @else
        <section class="grid gap-3 md:grid-cols-5">
            <article class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
                <p class="text-xs font-semibold uppercase text-slate-500">Rotasi</p>
                <p class="mt-2 text-2xl font-bold text-slate-950">{{ $summary['rotations'] }}</p>
            </article>
            <article class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
                <p class="text-xs font-semibold uppercase text-slate-500">Total Entri</p>
                <p class="mt-2 text-2xl font-bold text-slate-950">{{ $summary['total'] }}</p>
            </article>
            <article class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
                <p class="text-xs font-semibold uppercase text-amber-700">Draft</p>
                <p class="mt-2 text-2xl font-bold text-amber-700">{{ $summary['draft'] }}</p>
            </article>
            <article class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
                <p class="text-xs font-semibold uppercase text-cyan-700">Terkirim</p>
                <p class="mt-2 text-2xl font-bold text-cyan-700">{{ $summary['submitted'] }}</p>
            </article>
            <article class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
                <p class="text-xs font-semibold uppercase text-emerald-700">Disetujui</p>
                <p class="mt-2 text-2xl font-bold text-emerald-700">{{ $summary['approved'] }}</p>
            </article>
        </section>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach($runs as $run)
                <article class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                    <p class="text-xs font-black uppercase tracking-widest text-cyan-700">{{ $run->practiceDomain?->name }}</p>
                    <h3 class="mt-1 text-lg font-black text-slate-950">{{ $run->practiceSite?->name }}</h3>
                    <p class="mt-2 text-sm text-slate-500">{{ optional($run->scheduled_start_date)->format('d M Y') }} - {{ optional($run->scheduled_end_date)->format('d M Y') }}</p>
                    <div class="mt-4 grid gap-2 sm:grid-cols-2">
                        <div class="rounded-xl bg-slate-50 px-3 py-2 text-sm">
                            <p class="text-xs font-black uppercase tracking-widest text-slate-500">Total</p>
                            <p class="mt-1 font-black text-slate-950">{{ $run->logbook_entries_count }}</p>
                        </div>
                        <div class="rounded-xl bg-slate-50 px-3 py-2 text-sm">
                            <p class="text-xs font-black uppercase tracking-widest text-slate-500">Perlu Aksi</p>
                            <p class="mt-1 font-black text-slate-950">{{ $run->draft_logbook_entries_count }}</p>
                        </div>
                    </div>
                    <a href="{{ route('student.pkpa-operations.show', $run) }}" class="mt-4 inline-flex rounded-xl bg-cyan-700 px-4 py-2 text-sm font-black text-white">Buka Detail Rotasi</a>
                </article>
            @endforeach
        </section>
    @endif
</div>
@endsection
