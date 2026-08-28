@extends('layouts.app')
@section('title', 'Detail Periode PKPA - '.config('app.name'))
@section('page_title', 'Detail Periode PKPA')
@section('content')
@if($errors->any())<div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>@endif
<div class="grid gap-5 xl:grid-cols-[0.8fr_1.2fr]">
    <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <span class="rounded-full {{ $period->statusBadgeClass() }} px-3 py-1 text-xs font-semibold">{{ $period->statusLabel() }}</span>
        <h2 class="mt-4 text-2xl font-bold text-slate-950">{{ $period->name }}</h2>
        <p class="mt-1 text-sm text-slate-500">{{ $period->academic_year ?: '-' }} {{ $period->semester ? '('.$period->semester.')' : '' }}</p>
        <p class="mt-4 text-sm leading-6 text-slate-600">{{ $period->description ?: 'Tidak ada deskripsi.' }}</p>
        <div class="mt-6 flex gap-2"><a href="{{ route('management.kp-periods.edit', $period) }}" class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white">Edit</a><form method="POST" action="{{ route('management.kp-periods.destroy', $period) }}" onsubmit="return confirm('Hapus periode ini?')">@csrf @method('DELETE')<button class="rounded-lg border border-rose-200 px-4 py-2 text-sm font-semibold text-rose-700">Hapus</button></form></div>
    </section>
    <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <h3 class="text-lg font-bold text-slate-950">Kapasitas Terkait</h3>
        <div class="mt-4 space-y-2">@forelse($period->quotas as $quota)<div class="rounded-lg border border-slate-200 p-3 text-sm"><strong>{{ $quota->place->name }}</strong><span class="float-right">{{ $quota->quota }} kapasitas</span></div>@empty<p class="rounded-lg bg-slate-50 px-4 py-3 text-sm text-slate-500">Belum ada kapasitas pada periode ini.</p>@endforelse</div>
    </section>
</div>
@endsection
