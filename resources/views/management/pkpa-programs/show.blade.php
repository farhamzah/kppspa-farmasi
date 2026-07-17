@extends('layouts.app')
@section('title', 'Detail Program PKPA - '.config('app.name'))
@section('page_title', 'Detail Program PKPA')
@section('content')
<div class="space-y-5">
    @if($errors->any())<div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $errors->first() }}</div>@endif
    <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-2"><span class="rounded-full px-2 py-1 text-xs font-black {{ $program->statusBadgeClass() }}">{{ $program->statusLabel() }}</span><span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-black text-slate-600">{{ $program->completionLabel() }}</span></div>
                <h2 class="mt-3 text-2xl font-black text-slate-950">{{ $program->name }}</h2>
                <p class="text-sm text-slate-500">{{ $program->code }} · {{ $program->academic_year ?: 'Tahun belum diisi' }} · {{ $program->cohort_name ?: 'Angkatan belum diisi' }}</p>
                <p class="mt-3 max-w-3xl text-sm text-slate-600">{{ $program->description ?: 'Belum ada deskripsi.' }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('management.pkpa-programs.edit', $program) }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-bold">Edit</a>
                <a href="{{ route('management.pkpa-programs.configure', $program) }}" class="rounded-xl bg-cyan-700 px-4 py-2 text-sm font-black text-white">Konfigurasi Durasi</a>
                <a href="{{ route('management.pkpa-program-sites.index', ['program_id' => $program->id]) }}" class="rounded-xl border border-cyan-200 px-4 py-2 text-sm font-bold text-cyan-700">Tempat Tersedia</a>
                <a href="{{ route('management.pkpa-internal-supervisors.index', ['program_id' => $program->id]) }}" class="rounded-xl border border-cyan-200 px-4 py-2 text-sm font-bold text-cyan-700">Pembimbing Dalam</a>
                <a href="{{ route('management.pkpa-placement-readiness.index', ['program_id' => $program->id]) }}" class="rounded-xl border border-cyan-200 px-4 py-2 text-sm font-bold text-cyan-700">Readiness Penempatan</a>
                <a href="{{ route('management.pkpa-programs.readiness', $program) }}" class="rounded-xl border border-cyan-200 px-4 py-2 text-sm font-bold text-cyan-700">Periksa Kesiapan</a>
            </div>
        </div>
        <div class="mt-6 grid gap-3 md:grid-cols-4">
            <div class="rounded-xl bg-slate-50 p-4"><p class="text-xs font-black uppercase text-slate-500">Mulai</p><p class="mt-1 font-bold">{{ $program->start_date?->format('d M Y') ?: '-' }}</p></div>
            <div class="rounded-xl bg-slate-50 p-4"><p class="text-xs font-black uppercase text-slate-500">Selesai</p><p class="mt-1 font-bold">{{ $program->end_date?->format('d M Y') ?: '-' }}</p></div>
            <div class="rounded-xl bg-slate-50 p-4"><p class="text-xs font-black uppercase text-slate-500">Wahana Aktif</p><p class="mt-1 font-bold">{{ $program->domains->where('is_active', true)->count() }}</p></div>
            <div class="rounded-xl bg-slate-50 p-4"><p class="text-xs font-black uppercase text-slate-500">Total Bobot</p><p class="mt-1 font-bold">{{ number_format($program->domains->sum('weight_percentage'), 2) }}%</p></div>
        </div>
    </div>
    <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <h3 class="text-lg font-black text-slate-950">Status Kesiapan</h3>
        <div class="mt-4 grid gap-3 md:grid-cols-2">
            @foreach($readiness['checks'] as $key => $passed)
                <div class="rounded-xl border {{ $passed ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-amber-200 bg-amber-50 text-amber-800' }} px-4 py-3 text-sm font-bold">{{ $passed ? 'OK' : 'Perlu dilengkapi' }} · {{ $readiness['labels'][$key] }}</div>
            @endforeach
        </div>
        <form method="POST" action="{{ route('management.pkpa-programs.status', $program) }}" class="mt-5 flex flex-wrap gap-2">
            @csrf
            <button name="status" value="ready" class="rounded-xl border border-cyan-200 px-4 py-2 text-sm font-black text-cyan-700">Set Ready</button>
            <button name="status" value="active" class="rounded-xl bg-emerald-700 px-4 py-2 text-sm font-black text-white">Aktifkan</button>
            <button name="status" value="archived" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-bold text-slate-700">Arsipkan</button>
        </form>
    </div>
</div>
@endsection
