@extends('layouts.app')
@section('title', 'Detail Permintaan Perubahan PKPA - '.config('app.name'))
@section('page_title', 'Detail Permintaan Perubahan PKPA')

@section('content')
@php
    $changeStatusLabels = ['draft' => 'Draf', 'submitted' => 'Diajukan', 'under_review' => 'Diperiksa', 'approved' => 'Disetujui', 'rejected' => 'Ditolak', 'applied' => 'Diterapkan', 'failed' => 'Gagal'];
    $changeTypeLabels = ['date_change' => 'Ubah tanggal', 'site_change' => 'Ubah tempat', 'supervisor_change' => 'Ubah pembimbing', 'administrative_correction' => 'Koreksi administrasi', 'student_assignment_change' => 'Ubah penempatan mahasiswa'];
@endphp
<div class="space-y-5">
    @if(session('status'))<div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700">{{ $errors->first() }}</div>@endif

    <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <p class="text-xs font-black uppercase tracking-widest text-cyan-700">Permintaan perubahan</p>
        <h2 class="mt-1 text-2xl font-black text-slate-950">{{ $change->request_number }}</h2>
        <p class="mt-1 text-sm text-slate-500">Publikasi sumber {{ $change->publication->code }} / status {{ $changeStatusLabels[$change->status] ?? $change->status }} / tipe {{ $changeTypeLabels[$change->request_type] ?? $change->request_type }}</p>
        <div class="mt-5 flex flex-wrap gap-2">
            @if($change->status === 'draft')
                <form method="POST" action="{{ route('management.pkpa-change-requests.submit', $change) }}">@csrf<button class="rounded-xl bg-cyan-700 px-4 py-2 text-sm font-black text-white">Ajukan Pemeriksaan</button></form>
            @endif
            @if($change->status === 'submitted' && auth()->user()->hasRole('koordinator_kp'))
                <form method="POST" action="{{ route('management.pkpa-change-requests.approve', $change) }}">@csrf<button class="rounded-xl bg-emerald-700 px-4 py-2 text-sm font-black text-white">Setujui</button></form>
                <form method="POST" action="{{ route('management.pkpa-change-requests.reject', $change) }}" class="flex gap-2">@csrf<input name="rejection_reason" class="rounded-xl border border-slate-300 px-3 py-2 text-sm" placeholder="Alasan tolak"><button class="rounded-xl border border-rose-200 px-4 py-2 text-sm font-black text-rose-700">Tolak</button></form>
            @endif
            @if($change->status === 'approved' && auth()->user()->hasRole('koordinator_kp'))
                <form method="POST" action="{{ route('management.pkpa-change-requests.apply', $change) }}">@csrf<button class="rounded-xl bg-slate-950 px-4 py-2 text-sm font-black text-white">Terapkan Revisi</button></form>
            @endif
            <a href="{{ route('management.pkpa-publications.show', $change->publication) }}" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-black text-slate-700">Kembali</a>
        </div>
    </section>

    <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <h3 class="text-lg font-black text-slate-950">Item Perubahan</h3>
        <div class="mt-4 space-y-3">
            @foreach($change->items as $item)
                <div class="rounded-xl border border-slate-200 p-4">
                    <div class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                        <div>
                            <p class="font-black text-slate-950">{{ $item->oldAssignment?->student_name_snapshot }}</p>
                            <p class="text-sm text-slate-500">{{ $item->oldAssignment?->practice_domain_name_snapshot }} / {{ $item->oldAssignment?->practice_site_name_snapshot }}</p>
                        </div>
                        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-black text-slate-700">{{ $changeTypeLabels[$item->change_type] ?? $item->change_type }}</span>
                    </div>
                    <div class="mt-3 grid gap-3 md:grid-cols-2">
                        <div class="rounded-xl bg-slate-50 p-3 text-sm"><p class="font-black text-slate-700">Sebelum</p><pre class="mt-2 whitespace-pre-wrap text-xs text-slate-600">{{ json_encode($item->before_snapshot, JSON_PRETTY_PRINT) }}</pre></div>
                        <div class="rounded-xl bg-cyan-50 p-3 text-sm"><p class="font-black text-cyan-800">Usulan</p><pre class="mt-2 whitespace-pre-wrap text-xs text-cyan-900">{{ json_encode($item->proposed_snapshot, JSON_PRETTY_PRINT) }}</pre></div>
                    </div>
                </div>
            @endforeach
        </div>
    </section>
</div>
@endsection
