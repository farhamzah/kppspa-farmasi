@extends('layouts.app')

@section('title', 'Portofolio PKPA')

@section('content')
<div class="space-y-6">
    <section class="rounded-3xl border border-slate-100 bg-white p-6 shadow-sm">
        <p class="text-sm font-bold uppercase tracking-wide text-cyan-700">Portofolio PKPA</p>
        <h1 class="mt-2 text-3xl font-black text-slate-950">Portofolio Digital Rotasi</h1>
        <p class="mt-2 max-w-3xl text-sm text-slate-600">Setiap rotasi memiliki portofolio digital yang menggabungkan data penempatan, presensi, Logbook, kompetensi, tugas, laporan, nilai, dan bagian isian mahasiswa.</p>
    </section>
    <div class="grid gap-4">
        @forelse($portfolios as $portfolio)
            <article class="rounded-3xl border border-slate-100 bg-white p-5 shadow-sm">
                <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <p class="text-sm font-bold text-cyan-700">{{ data_get($portfolio->placement_snapshot, 'practice_domain') }} - {{ data_get($portfolio->placement_snapshot, 'practice_site') }}</p>
                        <h2 class="mt-1 text-xl font-black text-slate-950">{{ data_get($portfolio->identity_snapshot, 'student_name') }}</h2>
                        <p class="mt-1 text-sm text-slate-500">{{ $portfolio->statusLabel() }} - {{ count(data_get($portfolio->progress_snapshot, 'blocking', [])) }} catatan kemajuan</p>
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
