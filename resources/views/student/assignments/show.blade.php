@extends('layouts.app')

@section('title', 'Penempatan PKPA - '.config('app.name'))
@section('page_title', 'Penempatan PKPA')

@section('content')
<div class="space-y-5">
    @if($assignments->isEmpty())
        <section class="rounded-2xl bg-white p-10 text-center shadow-sm ring-1 ring-slate-200">
            <h2 class="text-xl font-bold text-slate-950">Anda belum memiliki penempatan PKPA</h2>
            <p class="mt-2 text-sm text-slate-500">Silakan tunggu penetapan tempat dan pembimbing dari koordinator PKPA.</p>
        </section>
    @else
        <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <p class="text-xs font-black uppercase tracking-widest text-cyan-700">Penempatan aktif / terdekat</p>
            <h2 class="mt-1 text-2xl font-black text-slate-950">{{ $currentAssignment->practice_domain_name_snapshot }}</h2>
            <p class="mt-1 text-sm text-slate-500">{{ $currentAssignment->practice_site_name_snapshot }} / {{ optional($currentAssignment->start_date)->format('d M Y') }} - {{ optional($currentAssignment->end_date)->format('d M Y') }}</p>
            <div class="mt-4 grid gap-3 md:grid-cols-3">
                <div class="rounded-xl bg-slate-50 px-4 py-3">
                    <p class="text-xs font-black uppercase tracking-widest text-slate-500">Publikasi</p>
                    <p class="mt-1 font-black text-slate-950">{{ $publication?->title ?: '-' }}</p>
                </div>
                <div class="rounded-xl bg-slate-50 px-4 py-3">
                    <p class="text-xs font-black uppercase tracking-widest text-slate-500">Hari Efektif</p>
                    <p class="mt-1 font-black text-slate-950">{{ $currentAssignment->effective_days_snapshot ?: 0 }} hari</p>
                </div>
                <div class="rounded-xl bg-slate-50 px-4 py-3">
                    <p class="text-xs font-black uppercase tracking-widest text-slate-500">Jam Praktik</p>
                    <p class="mt-1 font-black text-slate-950">{{ $currentAssignment->practice_hours_snapshot ?: '-' }} jam</p>
                </div>
            </div>
            <div class="mt-4 grid gap-3 md:grid-cols-2">
                @foreach($currentAssignment->supervisors as $supervisor)
                    <div class="rounded-xl bg-slate-50 p-4">
                        <p class="text-xs font-black uppercase tracking-widest text-slate-500">{{ $supervisor->supervisor_type === 'internal' ? 'Pembimbing Dalam' : 'Preseptor' }}</p>
                        <p class="mt-1 font-black text-slate-950">{{ $supervisor->display_name }}</p>
                        <p class="text-sm text-slate-500">{{ $supervisor->email_snapshot ?: '-' }}</p>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach($assignments as $assignment)
                <article class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                    <p class="text-xs font-black uppercase tracking-widest text-cyan-700">{{ $assignment->practice_domain_name_snapshot }}</p>
                    <h3 class="mt-1 text-lg font-black text-slate-950">{{ $assignment->practice_site_name_snapshot }}</h3>
                    <p class="mt-2 text-sm text-slate-500">{{ optional($assignment->start_date)->format('d M Y') }} - {{ optional($assignment->end_date)->format('d M Y') }}</p>
                    <p class="mt-1 text-sm text-slate-500">{{ $assignment->practice_site_address_snapshot ?: 'Alamat belum tersedia' }}</p>
                    @if($assignment->supervisors->isNotEmpty())
                        <div class="mt-4 space-y-2">
                            @foreach($assignment->supervisors as $supervisor)
                                <div class="rounded-xl bg-slate-50 px-3 py-2 text-sm">
                                    <p class="font-black text-slate-950">{{ $supervisor->display_name }}</p>
                                    <p class="text-xs text-slate-500">{{ $supervisor->supervisor_type === 'internal' ? 'Pembimbing Dalam' : 'Preseptor' }}</p>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </article>
            @endforeach
        </section>
    @endif
</div>
@endsection
