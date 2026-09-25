@extends('layouts.app')
@section('title', 'Peserta PKPA - '.config('app.name'))
@section('page_title', 'Peserta PKPA')
@section('content')
<div class="space-y-5">
    @if(session('status'))<div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-700">{{ session('status') }}</div>@endif
    @if(session('warning'))
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            <p class="font-black">{{ session('warning') }}</p>
            @if(session('warning_details'))
                <ul class="mt-2 list-disc space-y-1 pl-5 text-xs text-amber-900">
                    @foreach(session('warning_details') as $detail)<li>{{ $detail }}</li>@endforeach
                </ul>
            @endif
        </div>
    @endif
    @if($errors->any())<div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $errors->first() }}</div>@endif

    <section class="rounded-lg bg-white shadow-sm ring-1 ring-slate-200">
        <div class="flex flex-col gap-4 border-b border-slate-200 px-5 py-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="text-lg font-black text-slate-950">Daftar Peserta</h2>
                    <span class="rounded-full bg-cyan-50 px-2.5 py-1 text-xs font-black text-cyan-700">{{ $enrollments->total() }} peserta</span>
                </div>
                <p class="mt-1 text-sm text-slate-600">Kemajuan menampilkan wahana yang sudah dijadwalkan dan yang telah selesai dari lima wahana PKPA.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('management.pkpa-enrollments.import') }}" class="rounded-lg border border-cyan-200 px-4 py-2 text-sm font-black text-cyan-700">Impor CSV</a>
                <a href="{{ route('management.pkpa-enrollments.create') }}" class="rounded-lg bg-cyan-700 px-4 py-2 text-sm font-black text-white">Tambah Peserta</a>
            </div>
        </div>

        <form method="GET" class="grid gap-3 px-5 py-4 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-[minmax(260px,1.6fr)_minmax(150px,0.8fr)_minmax(150px,0.8fr)_minmax(150px,0.8fr)_minmax(170px,0.9fr)_auto]">
            <div><label class="text-xs font-black uppercase tracking-widest text-slate-500">Cari Mahasiswa</label><input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Nama, NPM, atau Core ID" class="mt-1 h-11 w-full rounded-lg border border-slate-300 px-3 text-sm focus:border-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-500/20"></div>
            <div><label class="text-xs font-black uppercase tracking-widest text-slate-500">Program</label><select name="program_id" class="mt-1 h-11 w-full rounded-lg border border-slate-300 px-3 text-sm"><option value="">Semua program</option>@foreach($programs as $program)<option value="{{ $program->id }}" @selected(($filters['program_id'] ?? '') == $program->id)>{{ $program->code }}</option>@endforeach</select></div>
            <div><label class="text-xs font-black uppercase tracking-widest text-slate-500">Status Peserta</label><select name="status" class="mt-1 h-11 w-full rounded-lg border border-slate-300 px-3 text-sm"><option value="">Semua status</option>@foreach(\App\Models\PkpaEnrollment::STATUSES as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ (new \App\Models\PkpaEnrollment(['status' => $status]))->statusLabel() }}</option>@endforeach</select></div>
            <div><label class="text-xs font-black uppercase tracking-widest text-slate-500">Status Akun</label><select name="core_account_status" class="mt-1 h-11 w-full rounded-lg border border-slate-300 px-3 text-sm"><option value="">Semua akun</option><option value="active" @selected(($filters['core_account_status'] ?? '') === 'active')>Aktif</option><option value="inactive" @selected(($filters['core_account_status'] ?? '') === 'inactive')>Nonaktif</option></select></div>
            <div><label class="text-xs font-black uppercase tracking-widest text-slate-500">Data Core</label><select name="sync" class="mt-1 h-11 w-full rounded-lg border border-slate-300 px-3 text-sm"><option value="">Semua kondisi</option><option value="problem" @selected(($filters['sync'] ?? '') === 'problem')>Perlu diperiksa</option></select></div>
            <div class="flex items-end gap-2"><button class="h-11 rounded-lg bg-slate-900 px-4 text-sm font-black text-white">Terapkan</button>@if(collect($filters)->filter(fn($value) => filled($value))->isNotEmpty())<a href="{{ route('management.pkpa-enrollments.index') }}" class="flex h-11 items-center rounded-lg border border-slate-300 px-3 text-sm font-bold text-slate-600">Reset</a>@endif</div>
        </form>
    </section>

    <section class="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-slate-200">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-black uppercase tracking-widest text-slate-500"><tr><th class="px-5 py-3">Mahasiswa</th><th class="px-4 py-3">Program</th><th class="px-4 py-3">Akun Core</th><th class="px-4 py-3">Status Peserta</th><th class="min-w-56 px-4 py-3">Kemajuan PKPA</th><th class="px-5 py-3 text-right">Aksi</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @forelse($enrollments as $enrollment)
                    @php
                        $totalRequirements = $enrollment->requirements->count();
                        $completedRequirements = $enrollment->requirements->where('status', 'completed')->count();
                        $scheduledRequirements = min($totalRequirements, max($completedRequirements, (int) $enrollment->current_published_assignments_count));
                        $completionPercentage = $totalRequirements > 0 ? (int) round(($completedRequirements / $totalRequirements) * 100) : 0;
                    @endphp
                    <tr class="align-top hover:bg-slate-50/70">
                        <td class="px-5 py-4"><div class="font-black text-slate-950">{{ $enrollment->student_name_snapshot ?: '-' }}</div><div class="mt-1 text-xs text-slate-500">NPM {{ $enrollment->student_number ?: '-' }} <span class="text-slate-300">/</span> Core {{ $enrollment->core_user_id }}</div></td>
                        <td class="px-4 py-4"><span class="font-bold text-slate-900">{{ $enrollment->program?->code }}</span><div class="mt-1 max-w-64 text-xs text-slate-500">{{ $enrollment->program?->name }}</div></td>
                        <td class="px-4 py-4"><span class="rounded-full px-2 py-1 text-xs font-black {{ $enrollment->core_account_status_snapshot === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">{{ $enrollment->core_account_status_snapshot === 'active' ? 'Aktif' : 'Perlu diperiksa' }}</span><div class="mt-2 text-xs text-slate-500">{{ $enrollment->last_core_synced_at ? 'Diperbarui '.$enrollment->last_core_synced_at->diffForHumans() : 'Belum disinkronkan' }}</div></td>
                        <td class="px-4 py-4"><span class="font-bold text-slate-800">{{ $enrollment->statusLabel() }}</span></td>
                        <td class="px-4 py-4"><div class="flex items-center justify-between gap-4 text-xs"><span class="font-bold text-slate-700">{{ $scheduledRequirements }} dari {{ $totalRequirements }} dijadwalkan</span><span class="text-slate-500">{{ $completedRequirements }} selesai</span></div><div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-emerald-500" style="width: {{ $completionPercentage }}%"></div></div></td>
                        <td class="px-5 py-4 text-right"><div class="flex flex-wrap justify-end gap-2"><a href="{{ route('management.pkpa-enrollments.show', $enrollment) }}" class="rounded-lg bg-cyan-700 px-3 py-2 text-xs font-black text-white">Lihat Detail</a><form method="POST" action="{{ route('management.pkpa-enrollments.sync', $enrollment) }}">@csrf<button class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-bold text-slate-700">Perbarui Core</button></form></div></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-12 text-center"><p class="font-bold text-slate-700">Peserta tidak ditemukan</p><p class="mt-1 text-sm text-slate-500">Ubah kata pencarian atau reset filter yang digunakan.</p></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-200 px-5 py-3">{{ $enrollments->links() }}</div>
    </section>
</div>
@endsection
