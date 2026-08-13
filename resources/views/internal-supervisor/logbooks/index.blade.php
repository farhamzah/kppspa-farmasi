@extends('layouts.app')

@section('title','Logbook Mahasiswa - '.config('app.name'))
@section('page_title','Logbook Mahasiswa')

@section('content')
@php
    $statusOptions = ['draft'=>'Draf','menunggu_validasi'=>'Menunggu Validasi','disetujui'=>'Disetujui','revisi'=>'Revisi','ditolak'=>'Ditolak'];
    $totalLogbooks = $assignments->getCollection()->sum('logbooks_count');
    $pendingLogbooks = $assignments->getCollection()->sum('pending_logbooks_count');
    $revisionLogbooks = $assignments->getCollection()->sum('revision_logbooks_count');
@endphp

<div class="space-y-5">
    <section class="grid gap-3 md:grid-cols-3">
        <div class="rounded-2xl border border-cyan-100 bg-white p-5 shadow-sm shadow-cyan-900/5">
            <p class="text-xs font-bold uppercase tracking-widest text-slate-500">Mahasiswa tampil</p>
            <p class="mt-2 text-3xl font-black text-slate-950">{{ $assignments->total() }}</p>
            <p class="mt-1 text-sm text-slate-500">Berdasarkan filter saat ini.</p>
        </div>
        <div class="rounded-2xl border border-amber-100 bg-white p-5 shadow-sm shadow-amber-900/5">
            <p class="text-xs font-bold uppercase tracking-widest text-slate-500">Menunggu validasi</p>
            <p class="mt-2 text-3xl font-black text-amber-700">{{ $pendingLogbooks }}</p>
            <p class="mt-1 text-sm text-slate-500">Diproses pembimbing lapangan.</p>
        </div>
        <div class="rounded-2xl border border-blue-100 bg-white p-5 shadow-sm shadow-blue-900/5">
            <p class="text-xs font-bold uppercase tracking-widest text-slate-500">Revisi / ditolak</p>
            <p class="mt-2 text-3xl font-black text-blue-700">{{ $revisionLogbooks }}</p>
            <p class="mt-1 text-sm text-slate-500">Dari {{ $totalLogbooks }} total logbook.</p>
        </div>
    </section>

    <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <form method="GET" class="grid gap-3 lg:grid-cols-[1fr_240px_auto]">
            <input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Cari nama, NIM, atau email mahasiswa" class="h-12 rounded-xl border border-slate-300 px-4 text-sm shadow-sm focus:border-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-500/20">
            <select name="status" class="h-12 rounded-xl border border-slate-300 px-4 text-sm shadow-sm focus:border-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-500/20">
                <option value="">Semua Status</option>
                @foreach($statusOptions as $value=>$label)
                    <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <button class="h-12 rounded-xl bg-slate-950 px-5 text-sm font-bold text-white shadow-sm hover:bg-slate-800">Filter</button>
        </form>
    </section>

    <section class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        <div class="border-b border-slate-100 px-5 py-4">
            <h2 class="text-lg font-black text-slate-950">Ringkasan per Mahasiswa</h2>
            <p class="mt-1 text-sm text-slate-500">Pembimbing dalam dapat memantau progres logbook dan memberi komentar pada rincian mahasiswa.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-bold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-5 py-3">Mahasiswa</th>
                        <th class="px-5 py-3">Tempat / Pembimbing Lapangan</th>
                        <th class="px-5 py-3">Logbook</th>
                        <th class="px-5 py-3">Terakhir</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($assignments as $assignment)
                        @php
                            $lastActivityDate = $assignment->logbooks_max_activity_date ? \Illuminate\Support\Carbon::parse($assignment->logbooks_max_activity_date)->format('d M Y') : '-';
                            $lastSubmittedAt = $assignment->logbooks_max_submitted_at ? \Illuminate\Support\Carbon::parse($assignment->logbooks_max_submitted_at)->format('d M Y H:i') : '-';
                            $isSelected = (int) ($filters['assignment'] ?? 0) === $assignment->id;
                        @endphp
                        <tr class="{{ $isSelected ? 'bg-cyan-50/60' : 'bg-white' }}">
                            <td class="px-5 py-4">
                                <div class="font-bold text-slate-950">{{ $assignment->student->user->name }}</div>
                                <div class="mt-1 text-xs text-slate-500">{{ $assignment->student->nim ?: '-' }} · {{ $assignment->student->user->email }}</div>
                            </td>
                            <td class="px-5 py-4">
                                <div class="font-semibold text-slate-800">{{ $assignment->place->name }}</div>
                                <div class="mt-1 text-xs text-slate-500">{{ $assignment->fieldSupervisor ? field_supervisor_display_name($assignment->fieldSupervisor) : 'Pembimbing lapangan belum ada' }}</div>
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex flex-wrap gap-2">
                                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-700">{{ $assignment->logbooks_count }} total</span>
                                    <span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-bold text-amber-700">{{ $assignment->pending_logbooks_count }} menunggu</span>
                                    <span class="rounded-full bg-blue-50 px-2.5 py-1 text-xs font-bold text-blue-700">{{ $assignment->revision_logbooks_count }} revisi/ditolak</span>
                                </div>
                            </td>
                            <td class="px-5 py-4">
                                <div class="font-semibold text-slate-800">{{ $lastActivityDate }}</div>
                                <div class="mt-1 text-xs text-slate-500">Dikirim: {{ $lastSubmittedAt }}</div>
                            </td>
                            <td class="px-5 py-4">
                                @if($assignment->pending_logbooks_count > 0)
                                    <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-bold text-amber-800 ring-1 ring-amber-200">Menunggu Lapangan</span>
                                @elseif($assignment->revision_logbooks_count > 0)
                                    <span class="rounded-full bg-blue-100 px-3 py-1 text-xs font-bold text-blue-800 ring-1 ring-blue-200">Ada Revisi</span>
                                @elseif($assignment->logbooks_count > 0)
                                    <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-800 ring-1 ring-emerald-200">Terkendali</span>
                                @else
                                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600 ring-1 ring-slate-200">Belum Lapor</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-right">
                                <a href="{{ request()->fullUrlWithQuery(['assignment' => $assignment->id]) }}#rincian-logbook" class="inline-flex rounded-xl border border-cyan-200 px-3 py-2 text-xs font-bold text-cyan-700 hover:bg-cyan-50">
                                    Lihat Logbook
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-12 text-center text-slate-500">Belum ada mahasiswa bimbingan dalam yang sesuai filter.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t px-5 py-3">{{ $assignments->links() }}</div>
    </section>

    @if($selectedAssignment)
        <section id="rincian-logbook" class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-cyan-100">
            <div class="flex flex-col gap-3 border-b border-slate-100 pb-4 md:flex-row md:items-start md:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-widest text-cyan-700">Rincian Logbook Mahasiswa</p>
                    <h2 class="mt-1 text-2xl font-black text-slate-950">{{ $selectedAssignment->student->user->name }}</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ $selectedAssignment->student->nim ?: '-' }} · {{ $selectedAssignment->place->name }} · {{ $selectedAssignment->period->name }}</p>
                </div>
                <a href="{{ route('internal-supervisor.logbooks.index', request()->except('assignment')) }}" class="inline-flex rounded-xl border border-slate-200 px-4 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50">Tutup Rincian</a>
            </div>

            <div class="mt-4 space-y-3">
                @forelse($selectedLogbooks as $logbook)
                    <div class="rounded-2xl border border-slate-200 p-4">
                        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="rounded-full px-2.5 py-1 text-xs font-bold ring-1 {{ $logbook->statusBadgeClass() }}">{{ $logbook->statusLabel() }}</span>
                                    <span class="text-xs font-semibold text-slate-500">{{ $logbook->activity_date->format('d M Y') }} · {{ $logbook->activityDurationLabel() }}</span>
                                </div>
                                <h3 class="mt-2 text-base font-black text-slate-950">{{ $logbook->activity_title }}</h3>
                                <p class="mt-1 line-clamp-2 text-sm text-slate-600">{{ $logbook->activity_description }}</p>
                                <p class="mt-2 text-xs text-slate-500">Diajukan: {{ $logbook->submitted_at?->format('d M Y H:i') ?? '-' }} · Divalidasi lapangan: {{ $logbook->validated_at?->format('d M Y H:i') ?? '-' }}</p>
                            </div>
                            <a href="{{ route('internal-supervisor.logbooks.show', $logbook) }}" class="inline-flex justify-center rounded-xl bg-cyan-700 px-4 py-2 text-sm font-bold text-white shadow-sm shadow-cyan-700/15 hover:bg-cyan-800">
                                Lihat & Komentar
                            </a>
                        </div>
                    </div>
                @empty
                    <div class="rounded-2xl border border-dashed border-slate-200 px-4 py-8 text-center text-sm text-slate-500">Mahasiswa ini belum memiliki logbook sesuai filter.</div>
                @endforelse
            </div>
        </section>
    @endif
</div>
@endsection
