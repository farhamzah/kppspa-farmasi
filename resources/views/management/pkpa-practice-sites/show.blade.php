@extends('layouts.app')
@section('title', 'Detail Tempat Praktik - '.config('app.name'))
@section('page_title', 'Detail Tempat Praktik')
@section('content')
<div class="space-y-5">
    <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <span class="rounded-full px-2 py-1 text-xs font-black {{ $site->statusBadgeClass() }}">{{ $site->statusLabel() }}</span>
                <h2 class="mt-3 text-2xl font-black text-slate-950">{{ $site->name }}</h2>
                <p class="text-sm text-slate-500">{{ $site->code }} · {{ $site->practiceDomain?->name ?: '-' }}{{ $site->practiceDomainOption ? ' · '.$site->practiceDomainOption->name : '' }}</p>
            </div>
            <a href="{{ route('management.pkpa-practice-sites.edit', $site) }}" class="rounded-xl border border-cyan-200 px-4 py-2 text-sm font-black text-cyan-700">Edit Tempat</a>
        </div>
        @if($site->cooperationStatusLabel() === 'Berakhir')
            <div class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-bold text-rose-700">Kerja sama tempat ini sudah berakhir. Data tetap ditampilkan untuk histori.</div>
        @endif
        <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-xl bg-slate-50 p-4"><p class="text-xs font-black uppercase text-slate-500">Kota</p><p class="font-bold">{{ $site->city ?: '-' }}</p></div>
            <div class="rounded-xl bg-slate-50 p-4"><p class="text-xs font-black uppercase text-slate-500">Provinsi</p><p class="font-bold">{{ $site->province ?: '-' }}</p></div>
            <div class="rounded-xl bg-slate-50 p-4"><p class="text-xs font-black uppercase text-slate-500">Kerja Sama</p><p class="font-bold">{{ $site->cooperation_start_date?->format('d M Y') ?: '-' }} - {{ $site->cooperation_end_date?->format('d M Y') ?: '-' }}</p></div>
            <div class="rounded-xl bg-slate-50 p-4"><p class="text-xs font-black uppercase text-slate-500">Kontak</p><p class="font-bold">{{ $site->contact_person_name ?: '-' }}</p></div>
        </div>
        <div class="mt-6 grid gap-4 lg:grid-cols-2">
            <div><h3 class="font-black text-slate-950">Alamat</h3><p class="mt-2 text-sm text-slate-600">{{ $site->address ?: '-' }}</p></div>
            <div><h3 class="font-black text-slate-950">Kontak</h3><p class="mt-2 text-sm text-slate-600">{{ $site->phone ?: '-' }}<br>{{ $site->email ?: '-' }}<br>{{ $site->website ?: '-' }}</p></div>
        </div>
    </div>
</div>
@endsection
