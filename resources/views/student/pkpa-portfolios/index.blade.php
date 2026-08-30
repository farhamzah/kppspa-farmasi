@extends('layouts.app')

@section('title', 'Portofolio PKPA')
@section('page_title', 'Portofolio PKPA')

@section('content')
<div class="space-y-6">
    <section class="rounded-3xl border border-slate-100 bg-white p-6 shadow-sm">
        <p class="text-sm font-bold uppercase tracking-wide text-cyan-700">Portofolio PKPA</p>
        <h1 class="mt-2 text-3xl font-black text-slate-950">Portofolio Digital Rotasi</h1>
        <p class="mt-2 max-w-3xl text-sm text-slate-600">Setiap rotasi memiliki portofolio digital yang menggabungkan data penempatan, presensi, logbook, kompetensi, tugas, laporan, nilai, dan isian refleksi mahasiswa.</p>
        <div class="mt-4 rounded-2xl border border-sky-100 bg-sky-50 px-4 py-3 text-sm text-sky-900">
            Saat ini pengisian portofolio mahasiswa difokuskan ke wahana Apotek lebih dulu. Wahana lain disiapkan bertahap agar formatnya mengikuti panduan resmi PKPA 2026.
            @if(($hidden_runs ?? 0) > 0)
                <span class="mt-1 block font-semibold">{{ $hidden_runs }} rotasi lain sementara belum ditampilkan di menu ini.</span>
            @endif
        </div>
        <div class="mt-4 flex flex-wrap gap-3 text-sm">
            <span class="inline-flex rounded-full bg-white px-4 py-2 font-bold text-slate-700 ring-1 ring-slate-200">{{ $supported_runs ?? 0 }} portofolio tampil</span>
            @if(($hidden_runs ?? 0) > 0)
                <span class="inline-flex rounded-full bg-amber-50 px-4 py-2 font-bold text-amber-700 ring-1 ring-amber-200">{{ $hidden_runs }} wahana menunggu template</span>
            @endif
        </div>
    </section>
    <div class="grid gap-4">
        @forelse($portfolios as $portfolio)
            <article class="rounded-3xl border border-slate-100 bg-white p-5 shadow-sm">
                <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <p class="text-sm font-bold uppercase tracking-wide text-cyan-700">{{ data_get($portfolio->placement_snapshot, 'practice_domain') }}</p>
                        <h2 class="mt-1 text-2xl font-black text-slate-950">{{ data_get($portfolio->placement_snapshot, 'practice_site') }}</h2>
                        <div class="mt-2 flex flex-wrap items-center gap-2 text-sm">
                            <span class="inline-flex rounded-full bg-slate-50 px-3 py-1 font-bold text-slate-600 ring-1 ring-slate-200">{{ $portfolio->statusLabel() }}</span>
                            <span class="inline-flex rounded-full bg-slate-50 px-3 py-1 font-bold text-slate-600 ring-1 ring-slate-200">{{ count(data_get($portfolio->progress_snapshot, 'blocking', [])) }} catatan kemajuan</span>
                        </div>
                        <p class="mt-3 text-sm text-slate-500">Mahasiswa: {{ data_get($portfolio->identity_snapshot, 'student_name') }}</p>
                    </div>
                    <a href="{{ route('student.pkpa-portfolios.show', $portfolio) }}" class="rounded-2xl bg-cyan-700 px-4 py-3 text-center text-sm font-bold text-white">Buka Portofolio</a>
                </div>
            </article>
        @empty
            <div class="rounded-3xl border border-slate-100 bg-white p-6 text-sm text-slate-500">Belum ada rotasi PKPA yang dapat dibuatkan portofolio.</div>
        @endforelse
    </div>
</div>
@endsection
