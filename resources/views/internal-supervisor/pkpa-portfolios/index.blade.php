@extends('layouts.app')
@section('title', 'Pemeriksaan Portofolio Pembimbing Dalam')
@section('page_title', 'Pemeriksaan Portofolio')
@section('content')
<div class="space-y-6">
    <section class="rounded-3xl border border-slate-100 bg-white p-6 shadow-sm"><p class="text-sm font-bold uppercase tracking-wide text-cyan-700">Pemeriksaan Portofolio</p><h1 class="mt-2 text-3xl font-black text-slate-950">Antrian Pembimbing Dalam</h1><p class="mt-2 text-sm text-slate-600">Periksa refleksi, penilaian diri, dan kelengkapan portofolio mahasiswa sebelum disetujui.</p></section>
    <div class="grid gap-4">
        @forelse($portfolios as $portfolio)
            <article class="rounded-3xl border border-slate-100 bg-white p-5 shadow-sm"><div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between"><div><p class="font-black text-slate-950">{{ data_get($portfolio->identity_snapshot, 'student_name') }}</p><p class="text-sm text-slate-500">{{ $portfolio->practiceDomain?->name }} - {{ $portfolio->statusLabel() }}</p></div><a href="{{ route('internal-supervisor.pkpa-portfolios.show', $portfolio) }}" class="rounded-2xl bg-cyan-700 px-4 py-3 text-center text-sm font-bold text-white">Periksa</a></div></article>
        @empty
            <div class="rounded-3xl border border-slate-100 bg-white p-6 text-sm text-slate-500">Belum ada antrian portofolio.</div>
        @endforelse
    </div>
    {{ $portfolios->links() }}
</div>
@endsection
