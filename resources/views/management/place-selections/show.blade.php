@extends('layouts.app')
@section('title', 'Detail Pemilihan Tempat - '.config('app.name'))
@section('page_title', 'Detail Pemilihan Tempat')
@section('content')
@php($activeAssignment = $selection->assignment && $selection->assignment->status !== 'dibatalkan')
<div class="grid gap-5 xl:grid-cols-[0.9fr_1.1fr]">
    @if(session('status'))
        <div class="xl:col-span-2 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="xl:col-span-2 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif

    <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <span class="rounded-full {{ $selection->statusBadgeClass() }} px-3 py-1 text-xs font-semibold">{{ $selection->statusLabel() }}</span>
        <h2 class="mt-4 text-2xl font-bold">{{ $selection->student->user->name }}</h2>
        <p class="mt-1 text-sm text-slate-500">{{ $selection->student->nim ?: '-' }} - {{ $selection->period->name }}</p>

        <div class="mt-6 space-y-3 text-sm">
            <div class="rounded-lg bg-slate-50 p-3">
                <p class="text-xs text-slate-500">Tempat PKPA</p>
                <p class="font-bold">{{ $selection->place->name }}</p>
            </div>
            <div class="rounded-lg bg-slate-50 p-3">
                <p class="text-xs text-slate-500">Waktu Memilih</p>
                <p class="font-bold">{{ $selection->selected_at?->format('d M Y H:i') }}</p>
            </div>
            <div class="rounded-lg bg-slate-50 p-3">
                <p class="text-xs text-slate-500">Dipilih Oleh</p>
                <p class="font-bold">{{ $selection->selectedBy?->name ?? '-' }}</p>
            </div>
            @if($selection->note)
                <div class="rounded-lg bg-amber-50 p-3 text-amber-900">
                    <p class="text-xs font-semibold">Catatan</p>
                    <p class="mt-1">{{ $selection->note }}</p>
                </div>
            @endif
        </div>
    </section>

    <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <h3 class="text-lg font-bold">Aksi Admin/Koordinator</h3>

        @if($selection->status === 'aktif')
            @if($activeAssignment)
                <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                    <p class="font-semibold">Pilihan ini sudah menjadi Penempatan PKPA.</p>
                    <p class="mt-1">Jika perlu dibatalkan, lakukan dari halaman Penempatan PKPA agar pembimbing dilepas dan kuota tempat kembali konsisten.</p>
                </div>
                <a href="{{ route('management.kp-assignments.show', ['kp_assignment' => $selection->assignment, 'return_url' => route('management.pkpa-assignments.index')]) }}" class="mt-4 inline-flex w-full items-center justify-center rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white sm:w-auto">Buka Penempatan PKPA</a>
            @else
                <form method="POST" action="{{ route('management.place-selections.create-assignment', $selection) }}" class="mt-4" onsubmit="return confirm('Buat penempatan PKPA dari pilihan ini?')">
                    @csrf
                    <button class="w-full rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white sm:w-auto">Buat Penempatan</button>
                </form>

                <form method="POST" action="{{ route('management.place-selections.cancel', $selection) }}" class="mt-4" onsubmit="return confirm('Batalkan pilihan ini? Kuota tempat akan kembali tersedia dan mahasiswa bisa memilih ulang.')">
                    @csrf
                    <textarea name="reason" rows="3" placeholder="Alasan pembatalan" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea>
                    <button class="mt-2 w-full rounded-lg border border-rose-200 px-4 py-2 text-sm font-semibold text-rose-700 sm:w-auto">Batalkan Pilihan</button>
                </form>

                <a href="{{ route('management.place-selections.move', $selection) }}" class="mt-4 inline-flex w-full items-center justify-center rounded-lg border border-teal-200 px-4 py-2 text-sm font-semibold text-teal-700 sm:w-auto">Pindahkan Pilihan</a>
            @endif
        @else
            <p class="mt-3 rounded-lg bg-slate-50 px-4 py-3 text-sm text-slate-500">Pilihan sudah tidak aktif.</p>
        @endif
    </section>
</div>
@endsection
