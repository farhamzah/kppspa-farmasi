@extends('layouts.app')
@section('title', 'Rotasi PKPA')
@section('page_title', 'Rotasi PKPA')
@section('content')
@php
    $totalRuns = $runs->count();
    $totalAttendance = $runs->sum('attendance_records_count');
    $totalLogbooks = $runs->sum('logbook_entries_count');
@endphp
<div class="space-y-5">
    <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <p class="text-xs font-black uppercase tracking-widest text-cyan-700">Rotasi PKPA</p>
        <h2 class="mt-1 text-2xl font-black text-slate-950">Kelola Presensi dan Logbook per Rotasi</h2>
        <p class="mt-2 text-sm text-slate-500">Buka salah satu rotasi untuk mengisi presensi harian dan logbook kegiatan. Halaman detail rotasi adalah halaman kerja utama selama periode PKPA berjalan.</p>
    </section>

    <section class="grid gap-4 md:grid-cols-3">
        <article class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <p class="text-xs font-black uppercase tracking-widest text-slate-500">Rotasi Aktif</p>
            <p class="mt-3 text-3xl font-black text-slate-950">{{ $totalRuns }}</p>
            <p class="mt-1 text-sm text-slate-500">Jumlah wahana yang sedang berjalan di akun Anda.</p>
        </article>
        <article class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <p class="text-xs font-black uppercase tracking-widest text-cyan-700">Presensi Tersimpan</p>
            <p class="mt-3 text-3xl font-black text-cyan-700">{{ $totalAttendance }}</p>
            <p class="mt-1 text-sm text-slate-500">Total presensi dari seluruh rotasi aktif.</p>
        </article>
        <article class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <p class="text-xs font-black uppercase tracking-widest text-emerald-700">Logbook Tersimpan</p>
            <p class="mt-3 text-3xl font-black text-emerald-700">{{ $totalLogbooks }}</p>
            <p class="mt-1 text-sm text-slate-500">Termasuk draf, terkirim, dan yang sudah direview.</p>
        </article>
    </section>

    <div class="grid gap-4 lg:grid-cols-2">
    @forelse($runs as $run)
        <a href="{{ route('student.pkpa-operations.show', $run) }}" class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-sky-100 transition hover:ring-cyan-200">
            <p class="text-xs font-black uppercase tracking-widest text-cyan-700">{{ $run->practiceDomain?->name }}</p>
            <h2 class="mt-1 text-xl font-black text-slate-950">{{ $run->practiceSite?->name }}</h2>
            <p class="mt-2 text-sm text-slate-500">{{ $run->scheduled_start_date?->format('d M Y') }} - {{ $run->scheduled_end_date?->format('d M Y') }}</p>
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <div class="rounded-xl bg-slate-50 px-4 py-3 text-sm">
                    <p class="text-xs font-black uppercase tracking-widest text-slate-500">Presensi</p>
                    <p class="mt-1 font-black text-slate-950">{{ $run->attendance_records_count }} entri</p>
                </div>
                <div class="rounded-xl bg-slate-50 px-4 py-3 text-sm">
                    <p class="text-xs font-black uppercase tracking-widest text-slate-500">Logbook</p>
                    <p class="mt-1 font-black text-slate-950">{{ $run->logbook_entries_count }} entri</p>
                </div>
            </div>
            <div class="mt-4 h-2 rounded-full bg-slate-100"><div class="h-2 rounded-full bg-cyan-600" style="width: {{ optional($run->progressSnapshots->first())->progress_percentage ?? 0 }}%"></div></div>
            <p class="mt-2 text-xs font-bold text-slate-500">Kemajuan {{ optional($run->progressSnapshots->first())->progress_percentage ?? 0 }}%</p>
            <span class="mt-4 inline-flex rounded-xl bg-cyan-700 px-4 py-2 text-sm font-black text-white">Buka Presensi dan Logbook</span>
        </a>
    @empty
        <div class="rounded-2xl bg-white p-6 text-sm text-slate-500 shadow-sm ring-1 ring-sky-100">Belum ada rotasi operasional aktif dari publikasi resmi.</div>
    @endforelse
</div>
</div>
@endsection
