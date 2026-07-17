@extends('layouts.app')
@section('title', 'Kesiapan Program PKPA - '.config('app.name'))
@section('page_title', 'Kesiapan Program')
@section('content')
<div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
    <h2 class="text-xl font-black text-slate-950">{{ $program->name }}</h2>
    <p class="mt-1 text-sm text-slate-500">{{ $readiness['ready'] ? 'Program siap diubah menjadi ready/active.' : 'Program belum dapat diaktifkan.' }}</p>
    <div class="mt-5 grid gap-3 md:grid-cols-2">
        @foreach($readiness['checks'] as $key => $passed)
            <div class="rounded-xl border {{ $passed ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-amber-200 bg-amber-50 text-amber-800' }} px-4 py-3 text-sm font-bold">{{ $passed ? 'OK' : 'Perlu dilengkapi' }} · {{ $readiness['labels'][$key] }}</div>
        @endforeach
    </div>
    <div class="mt-5 flex flex-wrap gap-2"><a href="{{ route('management.pkpa-programs.configure', $program) }}" class="rounded-xl border border-cyan-200 px-4 py-2 text-sm font-black text-cyan-700">Kembali ke Konfigurasi</a><a href="{{ route('management.pkpa-programs.show', $program) }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-bold">Detail</a></div>
</div>
@endsection
