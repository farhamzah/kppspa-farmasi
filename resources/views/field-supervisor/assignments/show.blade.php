@extends('layouts.app')
@section('title','Detail Mahasiswa PKPA - '.config('app.name'))
@section('page_title','Detail Mahasiswa PKPA')
@section('content')
@php($internal = $assignment->supervisors->firstWhere('supervisor_type', 'internal'))
<section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
    <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">Aktif di portal</span>
    <h2 class="mt-4 text-2xl font-bold">{{ $assignment->student_name_snapshot }}</h2>
    <p class="mt-1 text-sm text-slate-500">{{ $assignment->student_number_snapshot ?: '-' }} · {{ $assignment->practice_domain_name_snapshot }} · {{ $assignment->practice_site_name_snapshot }}</p>

    <div class="mt-6 grid gap-4 md:grid-cols-2">
        <div class="rounded-xl bg-slate-50 p-4">
            <p class="text-xs text-slate-500">Periode PKPA</p>
            <p class="mt-2 font-bold">{{ $assignment->start_date?->format('d M Y') }} - {{ $assignment->end_date?->format('d M Y') }}</p>
        </div>
        <div class="rounded-xl bg-slate-50 p-4">
            <p class="text-xs text-slate-500">Pembimbing Dalam</p>
            <p class="mt-2 font-bold">{{ $internal?->display_name ?: '-' }}</p>
        </div>
    </div>

    <div class="mt-5 rounded-lg bg-slate-50 px-4 py-3 text-sm text-slate-600">Gunakan menu Validasi Logbook untuk meninjau aktivitas harian mahasiswa PKPA pada tempat ini.</div>
</section>
@endsection
