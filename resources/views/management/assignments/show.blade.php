@extends('layouts.app')
@section('title','Detail Penempatan KP - '.config('app.name'))
@section('page_title','Detail Penempatan KP')
@section('content')
@php($studentDisplay = app(\App\Services\KpMasterDataReadService::class)->getStudentDisplayData($assignment->student))
@php($internalSupervisorDisplay = $assignment->internalSupervisor ? app(\App\Services\KpMasterDataReadService::class)->getLecturerDisplayData($assignment->internalSupervisor) : null)
<div class="space-y-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <a href="{{ $backUrl }}" class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:border-cyan-300 hover:text-cyan-700">
            Kembali ke Penempatan KP
        </a>
        <p class="text-sm text-slate-500">Kelola pembimbing, status, dan riwayat penempatan mahasiswa.</p>
    </div>

    <div class="grid gap-5 xl:grid-cols-[0.9fr_1.1fr]">
    @if($errors->any())
        <div class="xl:col-span-2 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif
    @if(session('status'))
        <div class="xl:col-span-2 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif
    @if($assignment->status === 'dibatalkan')
        <div class="xl:col-span-2 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
            Penempatan ini sudah dibatalkan. Pembimbing dilepas dari penempatan, dan pilihan tempat terkait ikut dibatalkan bila masih aktif.
        </div>
    @endif

    <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <span class="rounded-full {{ $assignment->statusBadgeClass() }} px-3 py-1 text-xs font-semibold">{{ $assignment->statusLabel() }}</span>
        <h2 class="mt-4 text-2xl font-bold">{{ $studentDisplay->name }}</h2>
        <p class="mt-1 text-sm text-slate-500">{{ $studentDisplay->studentNumber ?: '-' }} - {{ $assignment->period->name }}</p>
        <div class="mt-6 space-y-3 text-sm">
            <div class="rounded-lg bg-slate-50 p-3">
                <p class="text-xs text-slate-500">Tempat KP</p>
                <p class="font-bold">{{ $assignment->place->name }}</p>
            </div>
            <div class="rounded-lg bg-slate-50 p-3">
                <p class="text-xs text-slate-500">Pembimbing Dalam</p>
                <p class="font-bold">{{ $internalSupervisorDisplay?->name ?? 'Belum ada' }}</p>
            </div>
            <div class="rounded-lg bg-slate-50 p-3">
                <p class="text-xs text-slate-500">Pembimbing Lapangan</p>
                <p class="font-bold">{{ $assignment->fieldSupervisor ? field_supervisor_display_name($assignment->fieldSupervisor) : 'Belum ada' }}</p>
            </div>
        </div>
        <div class="mt-5 space-y-3">
            @if($assignment->status !== 'dibatalkan')
                <a href="{{ route('management.kp-assignments.edit', ['kp_assignment' => $assignment, 'return_url' => $backUrl]) }}" class="inline-flex items-center justify-center rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white">Edit Pembimbing</a>
                <form method="POST" action="{{ route('management.kp-assignments.cancel',$assignment) }}" class="rounded-xl border border-rose-200 bg-rose-50 p-3" onsubmit="return confirm('Batalkan penempatan ini? Pembimbing akan dilepas dan pilihan tempat ikut dibatalkan bila masih aktif.')">
                    @csrf
                    <label class="text-xs font-semibold uppercase tracking-wide text-rose-700" for="reason">Alasan pembatalan</label>
                    <textarea id="reason" name="reason" rows="2" required class="mt-2 w-full rounded-lg border border-rose-200 bg-white px-3 py-2 text-sm" placeholder="Contoh: mahasiswa batal KP atau penempatan perlu diulang."></textarea>
                    <button class="mt-2 w-full rounded-lg border border-rose-300 px-4 py-2 text-sm font-semibold text-rose-700 sm:w-auto">Batalkan Penempatan & Lepas Pembimbing</button>
                </form>
            @endif
        </div>
    </section>

    <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <h3 class="text-lg font-bold">Riwayat Log</h3>
        <div class="mt-4 space-y-2">
            @forelse($assignment->logs->sortByDesc('created_at') as $log)
                <div class="rounded-lg border border-slate-200 p-3 text-sm">
                    <strong>{{ str_replace('_',' ',ucfirst($log->action)) }}</strong>
                    <div class="text-xs text-slate-500">{{ $log->user?->name ?? 'Sistem' }} - {{ $log->created_at->format('d M Y H:i') }}</div>
                    @if($log->note)
                        <p class="mt-1 text-slate-600">{{ $log->note }}</p>
                    @endif
                </div>
            @empty
                <p class="rounded-lg bg-slate-50 px-4 py-3 text-sm text-slate-500">Belum ada log.</p>
            @endforelse
        </div>
    </section>
    </div>
</div>
@endsection
