@extends('layouts.app')
@section('title', 'Tempat PKPA - '.config('app.name'))
@section('page_title', 'Tempat PKPA')
@section('content')
<div class="space-y-5">
    @if(session('status'))<div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>@endif

    @php($activeSelection = $registration->activePlaceSelection)
    @php($waiting = $registration->waitingList)
    <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-teal-700">Waktu Server: {{ $serverNow->format('d M Y H:i:s') }}</p>
                <h2 class="mt-2 text-2xl font-bold text-slate-950">{{ $period->name }}</h2>
                <p class="mt-2 text-sm text-slate-500">Jadwal pemilihan: {{ $period->selection_start_at?->format('d M Y H:i') ?? '-' }} - {{ $period->selection_end_at?->format('d M Y H:i') ?? '-' }}</p>
            </div>
            <span class="rounded-full {{ $registration->isEligibleForPlaceSelection() ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }} px-3 py-1 text-xs font-semibold">{{ $registration->selectionStatusLabel() }}</span>
        </div>
        @if(! $period->isSelectionOpen())
            <div class="mt-4 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800">
                {{ $period->selection_start_at && now()->lt($period->selection_start_at) ? 'Jadwal penetapan tempat belum dibuka.' : 'Jadwal penetapan tempat sudah ditutup.' }}
            </div>
        @endif
    </section>

    @if($activeSelection)
        <section class="rounded-2xl border border-emerald-200 bg-emerald-50 p-6">
            <p class="text-xs font-semibold uppercase tracking-widest text-emerald-700">Tempat PKPA Aktif</p>
            <h3 class="mt-2 text-xl font-bold text-emerald-950">{{ $activeSelection->place->name }}</h3>
            <p class="mt-1 text-sm text-emerald-800">{{ $activeSelection->place->typeLabel() }} · {{ $activeSelection->place->city ?: '-' }}</p>
            <p class="mt-2 text-sm text-emerald-700">Ditetapkan pada {{ $activeSelection->selected_at?->format('d M Y H:i') }}. Perubahan tempat dilakukan oleh admin atau koordinator.</p>
        </section>
    @elseif($waiting?->status === 'menunggu')
        <section class="rounded-2xl border border-amber-200 bg-amber-50 p-6">
            <h3 class="text-lg font-bold text-amber-900">Anda berada di daftar tunggu</h3>
            <p class="mt-2 text-sm text-amber-800">Silakan cek kembali jika admin atau koordinator memperbarui kapasitas atau penetapan tempat.</p>
        </section>
    @endif

    <section class="grid gap-4 lg:grid-cols-2">
        @forelse($quotas as $quota)
            @php($canSelect = $registration->isEligibleForPlaceSelection() && $period->isSelectionOpen() && $quota->is_open && ! $quota->isFull() && ! $activeSelection)
            <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-bold text-slate-950">{{ $quota->place->name }}</h3>
                        <p class="mt-1 text-sm text-slate-500">{{ $quota->place->typeLabel() }} · {{ $quota->place->city ?: '-' }}</p>
                    </div>
                    <span class="rounded-full {{ $quota->statusBadgeClass() }} px-3 py-1 text-xs font-semibold">{{ $quota->statusLabel() }}</span>
                </div>
                <p class="mt-3 text-sm text-slate-600">{{ $quota->place->address ?: 'Alamat belum diisi.' }}</p>
                <div class="mt-4 grid grid-cols-3 gap-3 text-center">
                    <div class="rounded-lg bg-slate-50 p-3"><p class="text-xs text-slate-500">Kapasitas</p><p class="font-bold">{{ $quota->quota }}</p></div>
                    <div class="rounded-lg bg-slate-50 p-3"><p class="text-xs text-slate-500">Terisi</p><p class="font-bold">{{ $quota->filledCount() }}</p></div>
                    <div class="rounded-lg bg-slate-50 p-3"><p class="text-xs text-slate-500">Sisa</p><p class="font-bold">{{ $quota->remainingQuota() }}</p></div>
                </div>
                <form method="POST" action="{{ route('student.place-selections.select', $quota) }}" class="mt-4" onsubmit="return confirm('Setelah dikonfirmasi, perubahan tempat hanya dapat dilakukan oleh admin atau koordinator.')">
                    @csrf
                    <button @disabled(! $canSelect) class="w-full rounded-lg px-4 py-2 text-sm font-semibold {{ $canSelect ? 'bg-teal-600 text-white' : 'cursor-not-allowed bg-slate-100 text-slate-400' }}">Pilih / Konfirmasi Tempat</button>
                </form>
            </div>
        @empty
            <div class="rounded-2xl bg-white p-10 text-center shadow-sm ring-1 ring-slate-200 lg:col-span-2">
                <p class="font-semibold text-slate-950">Belum ada kapasitas tempat PKPA untuk periode ini.</p>
            </div>
        @endforelse
    </section>

    @if(! $activeSelection && $quotas->isNotEmpty() && $quotas->every(fn($quota) => $quota->isFull() || ! $quota->is_open))
        <form method="POST" action="{{ route('student.place-selections.waiting-list') }}" class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            @csrf
            <p class="text-sm text-slate-600">Semua kapasitas sedang penuh atau ditutup. Anda dapat masuk daftar tunggu.</p>
            <button class="mt-3 rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white">Masuk Daftar Tunggu</button>
        </form>
    @endif
</div>
@endsection
