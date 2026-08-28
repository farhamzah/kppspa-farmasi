@extends('layouts.app')

@section('title','Validasi Logbook - '.config('app.name'))
@section('page_title','Validasi Logbook')

@section('content')
@php
    $statusOptions = ['draft'=>'Draf','menunggu_validasi'=>'Menunggu Validasi','disetujui'=>'Disetujui','revisi'=>'Revisi','ditolak'=>'Ditolak'];
    $totalLogbooks = $assignments->getCollection()->sum('logbooks_count');
    $pendingLogbooks = $assignments->getCollection()->sum('pending_logbooks_count');
    $approvedLogbooks = $assignments->getCollection()->sum('approved_logbooks_count');
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
            <p class="mt-1 text-sm text-slate-500">Perlu dicek pembimbing lapangan.</p>
        </div>
        <div class="rounded-2xl border border-emerald-100 bg-white p-5 shadow-sm shadow-emerald-900/5">
            <p class="text-xs font-bold uppercase tracking-widest text-slate-500">Total logbook</p>
            <p class="mt-2 text-3xl font-black text-emerald-700">{{ $totalLogbooks }}</p>
            <p class="mt-1 text-sm text-slate-500">{{ $approvedLogbooks }} sudah disetujui.</p>
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
            <p class="mt-1 text-sm text-slate-500">Klik mahasiswa untuk melihat tanggal, judul kegiatan, status, dan aksi validasi logbook.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-bold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-5 py-3">Mahasiswa</th>
                        <th class="px-5 py-3">Tempat / Periode</th>
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
                                <div class="mt-1 text-xs text-slate-500">{{ $assignment->period->name }}</div>
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex flex-wrap gap-2">
                                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-700">{{ $assignment->logbooks_count }} total</span>
                                    <span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-bold text-amber-700">{{ $assignment->pending_logbooks_count }} menunggu</span>
                                    <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-700">{{ $assignment->approved_logbooks_count }} disetujui</span>
                                </div>
                            </td>
                            <td class="px-5 py-4">
                                <div class="font-semibold text-slate-800">{{ $lastActivityDate }}</div>
                                <div class="mt-1 text-xs text-slate-500">Dikirim: {{ $lastSubmittedAt }}</div>
                            </td>
                            <td class="px-5 py-4">
                                @if($assignment->pending_logbooks_count > 0)
                                    <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-bold text-amber-800 ring-1 ring-amber-200">Perlu Validasi</span>
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
                            <td colspan="6" class="px-5 py-12 text-center text-slate-500">Belum ada mahasiswa bimbingan lapangan yang sesuai filter.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t px-5 py-3">{{ $assignments->links() }}</div>
    </section>

    @if($selectedAssignment)
        @php
            $pendingSelectedLogbooks = $selectedLogbooks->where('status', 'menunggu_validasi');
        @endphp
        <section id="rincian-logbook" class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-cyan-100">
            <div class="flex flex-col gap-3 border-b border-slate-100 pb-4 md:flex-row md:items-start md:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-widest text-cyan-700">Rincian Logbook Mahasiswa</p>
                    <h2 class="mt-1 text-2xl font-black text-slate-950">{{ $selectedAssignment->student->user->name }}</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ $selectedAssignment->student->nim ?: '-' }} · {{ $selectedAssignment->place->name }} · {{ $selectedAssignment->period->name }}</p>
                </div>
                <a href="{{ route('field-supervisor.pkpa-journals.index', request()->except('assignment')) }}" class="inline-flex rounded-xl border border-slate-200 px-4 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50">Tutup Rincian</a>
            </div>

            @if($pendingSelectedLogbooks->isNotEmpty())
                <form method="POST" action="{{ route('field-supervisor.pkpa-journals.bulk-approve') }}" class="mt-4 rounded-2xl border border-emerald-200 bg-emerald-50/60 p-4">
                    @csrf
                    <input type="hidden" name="assignment_id" value="{{ $selectedAssignment->id }}">

                    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-widest text-emerald-700">Validasi massal</p>
                            <h3 class="mt-1 text-lg font-black text-slate-950">Setujui beberapa logbook sekaligus</h3>
                            <p class="mt-1 text-sm text-slate-600">Semua logbook yang menunggu validasi sudah dicentang. Hapus centang bila ingin diperiksa satu per satu.</p>
                        </div>
                        <button type="submit" style="background:#047857;color:#ffffff;" class="inline-flex min-h-11 items-center justify-center rounded-xl px-5 py-2 text-sm font-bold shadow-sm shadow-emerald-700/15 hover:bg-emerald-800">
                            Setujui logbook terpilih
                        </button>
                    </div>

                    <div class="mt-4 grid gap-2 md:grid-cols-2">
                        @foreach($pendingSelectedLogbooks as $pendingLogbook)
                            <label class="flex gap-3 rounded-xl border border-emerald-100 bg-white p-3 text-sm shadow-sm">
                                <input type="checkbox" name="logbook_ids[]" value="{{ $pendingLogbook->id }}" checked class="mt-1 h-4 w-4 rounded border-slate-300 text-emerald-700 focus:ring-emerald-500">
                                <span>
                                    <span class="block font-bold text-slate-950">{{ $pendingLogbook->activity_date->format('d M Y') }}</span>
                                    <span class="mt-0.5 block text-slate-600">{{ $pendingLogbook->activity_title }}</span>
                                    <span class="mt-1 block text-xs text-slate-500">Diajukan: {{ $pendingLogbook->submitted_at?->format('d M Y H:i') ?? '-' }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>

                    <div class="mt-4">
                        <label for="bulk_validation_note" class="text-sm font-bold text-slate-700">Catatan validasi opsional</label>
                        <textarea id="bulk_validation_note" name="validation_note" rows="2" placeholder="Contoh: Aktivitas sudah sesuai dan bukti kegiatan sudah dicek." class="mt-2 w-full rounded-xl border border-emerald-100 bg-white px-4 py-3 text-sm shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20">{{ old('validation_note') }}</textarea>
                    </div>

                    @error('logbook_ids')
                        <p class="mt-3 text-sm font-semibold text-red-600">{{ $message }}</p>
                    @enderror
                </form>
            @endif

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
                                <p class="mt-2 text-xs text-slate-500">Diajukan: {{ $logbook->submitted_at?->format('d M Y H:i') ?? '-' }}</p>
                            </div>
                            <a href="{{ route('field-supervisor.pkpa-journals.show', $logbook) }}" class="inline-flex justify-center rounded-xl bg-cyan-700 px-4 py-2 text-sm font-bold text-white shadow-sm shadow-cyan-700/15 hover:bg-cyan-800">
                                Validasi
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
