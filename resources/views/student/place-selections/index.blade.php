@extends('layouts.app')
@section('title', 'Penetapan Tempat PKPA - '.config('app.name'))
@section('page_title', 'Penetapan Tempat PKPA')
@section('content')
<div class="si-page">
    @if(session('status'))<div class="rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-medium text-emerald-800 shadow-sm">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="rounded-xl border border-red-200 bg-red-50 px-5 py-4 text-sm font-medium text-red-700 shadow-sm">{{ $errors->first() }}</div>@endif

    <section class="relative overflow-hidden rounded-3xl border border-cyan-100 bg-white p-7 shadow-xl shadow-sky-900/6 md:p-8">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(14,165,233,0.16),transparent_34%),linear-gradient(135deg,rgba(236,254,255,0.9),rgba(255,255,255,0.72)_48%,rgba(240,249,255,0.92))]"></div>
        <div class="relative grid gap-6 lg:grid-cols-[minmax(0,1fr)_280px] lg:items-center">
            <div>
                <p class="text-xs font-black uppercase tracking-widest text-cyan-700">Waktu Server: {{ $serverNow->format('d M Y H:i:s') }}</p>
                <h2 class="mt-3 text-3xl font-bold tracking-tight text-slate-950 md:text-4xl">Tempat PKPA</h2>
                <p class="mt-4 max-w-3xl text-sm leading-7 text-slate-600">Halaman ini menampilkan periode penetapan tempat PKPA. Jika program membuka pilihan mandiri, Anda dapat memilih tempat yang tersedia. Jika penempatan diatur koordinator, hasilnya akan muncul otomatis di sini.</p>
            </div>
            <div class="rounded-2xl border border-cyan-100 bg-white/80 p-4 shadow-sm backdrop-blur">
                <p class="text-xs font-bold uppercase tracking-widest text-cyan-700">Alur pemilihan</p>
                <div class="mt-4 space-y-3">
                    <div class="flex items-center gap-3 text-sm font-semibold text-slate-700"><span class="flex h-7 w-7 items-center justify-center rounded-lg bg-cyan-700 text-white">1</span>Profil lengkap</div>
                    <div class="flex items-center gap-3 text-sm font-semibold text-slate-700"><span class="flex h-7 w-7 items-center justify-center rounded-lg bg-cyan-700 text-white">2</span>Pendaftaran terverifikasi</div>
                    <div class="flex items-center gap-3 text-sm font-semibold text-slate-700"><span class="flex h-7 w-7 items-center justify-center rounded-lg bg-cyan-700 text-white">3</span>Tunggu penetapan atau pilih tempat</div>
                </div>
            </div>
        </div>
    </section>

    @if(! auth()->user()->isProfileComplete())
        <div class="rounded-2xl border border-amber-300 bg-amber-50 px-5 py-4 text-sm font-semibold text-amber-900 shadow-sm">
            Lengkapi profil terlebih dahulu sebelum melanjutkan proses tempat PKPA.
        </div>
    @endif

    @forelse($periods as $period)
        @php($periodRegistration = auth()->user()->student?->kpRegistrations()->where('kp_period_id', $period->id)->first())
        <section class="si-card p-6">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <h3 class="text-lg font-bold text-slate-950">{{ $period->name }}</h3>
                    <p class="mt-1 text-sm text-slate-500">Pemilihan: {{ $period->selection_start_at?->format('d M Y H:i') ?? '-' }} - {{ $period->selection_end_at?->format('d M Y H:i') ?? '-' }}</p>
                    <p class="mt-1 text-sm text-slate-500">Status: {{ $periodRegistration?->selectionStatusLabel() ?? 'Belum eligible' }}</p>
                </div>
                <a href="{{ route('student.place-selections.show', $period) }}" class="si-btn si-btn-primary">Lihat Detail</a>
            </div>
        </section>
    @empty
        <section class="rounded-3xl border border-sky-100 bg-white p-10 text-center shadow-xl shadow-sky-900/6">
            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-sky-50 text-cyan-700 ring-1 ring-sky-100">
                <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3M5 11h14M6 21h12a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2Z"/>
                </svg>
            </div>
            <h3 class="mt-5 text-xl font-black text-slate-950">Belum ada periode yang bisa dipilih</h3>
            <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-500">Pastikan profil dan pendaftaran PKPA Anda sudah terverifikasi. Periode penetapan tempat akan tampil otomatis saat dibuka oleh program.</p>
        </section>
    @endforelse
</div>
@endsection
