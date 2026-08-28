@extends('layouts.app')
@section('title','Pre/Post Test - '.config('app.name'))
@section('page_title','Pre/Post Test')
@section('content')
<div class="space-y-6">
    <section class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-100">
        <p class="text-xs font-black uppercase tracking-widest text-cyan-700">Pembekalan PKPA</p>
        <h2 class="mt-2 text-2xl font-black text-slate-950">Pre-Test dan Post-Test</h2>
        <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">Kerjakan evaluasi pembekalan PKPA. Setelah submit, nilai dan pembahasan jawaban akan tampil otomatis.</p>
    </section>

    @unless($student)
        <section class="rounded-3xl border border-amber-200 bg-amber-50 p-6 shadow-sm">
            <p class="text-xs font-black uppercase tracking-widest text-amber-700">Profil mahasiswa belum tersedia</p>
            <h3 class="mt-2 text-xl font-black text-slate-950">Akun ini belum terhubung ke data mahasiswa PKPA.</h3>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-amber-900">
                Pre/Post Test menyimpan nilai berdasarkan profil mahasiswa. Gunakan akun mahasiswa yang sudah tersinkron dari Core, atau sinkronkan dulu bridge user mahasiswa melalui admin/command provisioning.
            </p>
        </section>
    @endunless

    <div class="grid gap-5 md:grid-cols-2">
        @forelse($tests as $test)
            @php($attempt = $test->attempts->first())
            <section class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-100 {{ $student ? '' : 'opacity-75' }}">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <span class="rounded-full bg-cyan-50 px-3 py-1 text-xs font-black uppercase tracking-widest text-cyan-700">{{ $test->typeLabel() }}</span>
                        <h3 class="mt-4 text-xl font-black text-slate-950">{{ $test->title }}</h3>
                    </div>
                    @if($attempt)
                        <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-black text-emerald-700">Selesai</span>
                    @else
                        <span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-black text-amber-700">Belum</span>
                    @endif
                </div>
                <p class="mt-4 text-sm leading-6 text-slate-600">{{ $test->description }}</p>
                <div class="mt-5 rounded-2xl bg-slate-50 p-4">
                    <p class="text-xs font-bold uppercase tracking-widest text-slate-500">Skor</p>
                    <p class="mt-1 text-3xl font-black text-slate-950">{{ $attempt ? $attempt->score.'/'.$attempt->max_score : '-' }}</p>
                </div>
                @if($student)
                    <a href="{{ $attempt ? route('student.orientation-tests.result', $attempt) : route('student.orientation-tests.show', $test) }}" class="mt-5 inline-flex w-full justify-center rounded-2xl bg-cyan-700 px-4 py-3 text-sm font-black text-white shadow-lg shadow-cyan-700/20">
                        {{ $attempt ? 'Lihat Hasil' : 'Mulai Kerjakan' }}
                    </a>
                @else
                    <span class="mt-5 inline-flex w-full justify-center rounded-2xl bg-slate-100 px-4 py-3 text-sm font-black text-slate-500">
                        Menunggu profil mahasiswa
                    </span>
                @endif
            </section>
        @empty
            <section class="rounded-3xl bg-white p-8 text-center shadow-sm ring-1 ring-slate-100 md:col-span-2">
                <p class="font-bold text-slate-600">Belum ada pre/post test aktif.</p>
            </section>
        @endforelse
    </div>
</div>
@endsection
