@extends('layouts.app')

@section('title', 'Hasil Akhir PKPA')

@section('content')
<div class="space-y-6">
    <div class="rounded-3xl border border-sky-100 bg-white p-6 shadow-sm">
        <p class="text-sm font-bold uppercase tracking-wide text-cyan-700">Hasil Akhir Program PKPA</p>
        <h1 class="mt-2 text-3xl font-black text-slate-950">Hasil Akademik PKPA</h1>
        <p class="mt-2 max-w-3xl text-sm text-slate-600">Hasil pada halaman ini merupakan hasil akademik Program PKPA dalam MY PSPA. Dokumen resmi universitas dapat mengikuti proses administrasi terpisah.</p>
    </div>
    <div class="grid gap-4">
        @forelse($releases as $release)
            <article class="rounded-3xl border border-slate-100 bg-white p-5 shadow-sm">
                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div><p class="text-sm font-bold uppercase text-cyan-700">Dirilis {{ $release->released_at?->translatedFormat('d M Y') }}</p><h2 class="mt-1 text-2xl font-black text-slate-950">{{ $release->student_visible_snapshot['final_score'] ?? '-' }} / {{ $release->student_visible_snapshot['maximum_score'] ?? '-' }}</h2></div>
                    <span class="rounded-full bg-emerald-50 px-4 py-2 text-sm font-bold text-emerald-700">{{ $release->student_visible_snapshot['decision'] ?? 'decision pending' }}</span>
                </div>
                <p class="mt-4 text-sm text-slate-600">{{ $release->student_visible_snapshot['label'] ?? '' }}</p>
            </article>
        @empty
            <div class="rounded-3xl border border-slate-100 bg-white p-6 text-sm text-slate-500">Hasil akhir PKPA belum dirilis.</div>
        @endforelse
    </div>
</div>
@endsection
