@extends('layouts.app')
@section('title', 'Pemeriksaan Final Publikasi PKPA - '.config('app.name'))
@section('page_title', 'Pemeriksaan Final Publikasi PKPA')

@section('content')
<div class="space-y-5">
    <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <p class="text-xs font-black uppercase tracking-widest text-cyan-700">Checklist publikasi</p>
        <h2 class="mt-1 text-2xl font-black text-slate-950">{{ $plan->program->code }} - {{ $plan->name }}</h2>
        <p class="mt-1 text-sm text-slate-500">Validasi ulang selesai. Jadwal dapat dipublikasikan hanya jika semua checklist utama lulus.</p>
        <div class="mt-5 grid gap-3 md:grid-cols-5">
            <div class="rounded-xl bg-slate-50 p-4"><p class="text-xs font-black uppercase text-slate-500">Peserta</p><p class="text-xl font-black">{{ $review['participants'] }}</p></div>
            <div class="rounded-xl bg-slate-50 p-4"><p class="text-xs font-black uppercase text-slate-500">Terisi</p><p class="text-xl font-black">{{ $review['filled_assignments'] }} / {{ $review['required_assignments'] }}</p></div>
            <div class="rounded-xl bg-rose-50 p-4 text-rose-800"><p class="text-xs font-black uppercase">Error</p><p class="text-xl font-black">{{ $review['errors'] }}</p></div>
            <div class="rounded-xl bg-amber-50 p-4 text-amber-800"><p class="text-xs font-black uppercase">Warning</p><p class="text-xl font-black">{{ $review['warnings'] }}</p></div>
            <div class="rounded-xl {{ $review['ready'] ? 'bg-emerald-50 text-emerald-800' : 'bg-amber-50 text-amber-800' }} p-4"><p class="text-xs font-black uppercase">Hasil</p><p class="text-xl font-black">{{ $review['ready'] ? 'Siap' : 'Belum' }}</p></div>
        </div>
    </section>

    <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <div class="grid gap-3 md:grid-cols-2">
            @foreach($review['items'] as $item)
                <div class="rounded-xl border {{ $item['passed'] ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-rose-200 bg-rose-50 text-rose-800' }} px-4 py-3">
                    <p class="font-black">{{ $item['passed'] ? 'Lulus' : 'Belum Lulus' }}</p>
                    <p class="mt-1 text-sm font-semibold">{{ $item['label'] }}</p>
                </div>
            @endforeach
        </div>
        <div class="mt-5 flex flex-wrap gap-2">
            <a href="{{ route('management.pkpa-publications.index', ['program_id' => $plan->pkpa_program_id]) }}" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-black text-slate-700">Kembali</a>
            @if(auth()->user()->hasRole('koordinator_kp'))
                <form method="POST" action="{{ route('management.pkpa-placement-plans.publication-lock', $plan) }}">@csrf<button class="rounded-xl border border-amber-200 px-4 py-2 text-sm font-black text-amber-700">Kunci untuk Publikasi</button></form>
            @endif
        </div>
    </section>
</div>
@endsection
