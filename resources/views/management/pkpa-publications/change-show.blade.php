@extends('layouts.app')
@section('title', 'Detail Permintaan Perubahan PKPA - '.config('app.name'))
@section('page_title', 'Detail Permintaan Perubahan PKPA')

@section('content')
@php
    $changeStatusLabels = ['draft' => 'Draf', 'submitted' => 'Diajukan', 'under_review' => 'Diperiksa', 'approved' => 'Disetujui', 'rejected' => 'Ditolak', 'applied' => 'Diterapkan', 'failed' => 'Gagal'];
    $changeTypeLabels = ['date_change' => 'Ubah tanggal', 'site_change' => 'Ubah tempat', 'supervisor_change' => 'Ubah preseptor', 'field_supervisor_change' => 'Ubah preseptor', 'internal_supervisor_change' => 'Ganti Pembimbing Dalam', 'internal_supervisor_replacement' => 'Ganti Pembimbing Dalam', 'administrative_correction' => 'Koreksi administrasi', 'student_assignment_change' => 'Ubah penempatan mahasiswa'];
    $isInternalReplacement = $change->request_type === 'internal_supervisor_replacement';
@endphp
<div class="space-y-5">
    @if(session('status'))<div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700">{{ $errors->first() }}</div>@endif

    <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <p class="text-xs font-black uppercase tracking-widest text-cyan-700">Permintaan perubahan</p>
        <h2 class="mt-1 text-2xl font-black text-slate-950">{{ $change->request_number }}</h2>
        <p class="mt-1 text-sm text-slate-500">Publikasi sumber {{ $change->publication->code }} / status {{ $changeStatusLabels[$change->status] ?? $change->status }} / tipe {{ $changeTypeLabels[$change->request_type] ?? $change->request_type }}</p>
        <div class="mt-4 rounded-2xl border {{ in_array($change->status, ['approved', 'applied'], true) ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : (in_array($change->status, ['rejected', 'failed'], true) ? 'border-rose-200 bg-rose-50 text-rose-900' : 'border-amber-200 bg-amber-50 text-amber-900') }} px-4 py-4 text-sm">
            <p class="font-black">
                {{ $change->status === 'draft' ? 'Masih draf' : ($change->status === 'submitted' ? 'Menunggu keputusan' : ($change->status === 'approved' ? 'Siap diterapkan' : ($change->status === 'applied' ? 'Sudah diterapkan' : 'Perlu tindak lanjut'))) }}
            </p>
            <p class="mt-1">
                @if($change->status === 'draft')
                    Periksa lagi isi usulan, lalu ajukan untuk pemeriksaan.
                @elseif($change->status === 'submitted')
                    Permintaan ini sudah diajukan dan tinggal menunggu persetujuan Koordinator PKPA.
                @elseif($change->status === 'approved')
                    Perubahan sudah disetujui. Langkah berikutnya adalah menerapkan revisi agar publikasi baru terbentuk.
                @elseif($change->status === 'applied')
                    Revisi sudah diterapkan ke publikasi resmi. Riwayat permintaan ini tetap disimpan untuk audit.
                @elseif($change->status === 'rejected')
                    Permintaan ini ditolak. Lihat alasan penolakan sebelum membuat revisi baru.
                @else
                    Permintaan ini belum selesai diproses. Periksa kembali detail item perubahan.
                @endif
            </p>
        </div>
        <div class="mt-5 flex flex-wrap gap-2">
            @if($isInternalReplacement && in_array($change->status, ['draft', 'submitted', 'approved'], true) && auth()->user()->hasRole('koordinator_kp'))
                <form method="POST" action="{{ route('management.pkpa-internal-supervisor-replacements.confirm', $change) }}" onsubmit="return confirm('Terapkan penggantian Pembimbing Dalam untuk seluruh mahasiswa yang tercantum?')">
                    @csrf
                    <button class="rounded-xl bg-cyan-700 px-5 py-2 text-sm font-black text-white">Konfirmasi dan Terapkan</button>
                </form>
            @endif
            @if($change->status === 'draft' && ! $isInternalReplacement)
                <form method="POST" action="{{ route('management.pkpa-change-requests.submit', $change) }}">@csrf<button class="rounded-xl bg-cyan-700 px-4 py-2 text-sm font-black text-white">Ajukan Pemeriksaan</button></form>
            @endif
            @if($change->status === 'submitted' && ! $isInternalReplacement && auth()->user()->hasRole('koordinator_kp'))
                <form method="POST" action="{{ route('management.pkpa-change-requests.approve', $change) }}">@csrf<button class="rounded-xl bg-emerald-700 px-4 py-2 text-sm font-black text-white">Setujui</button></form>
                <form method="POST" action="{{ route('management.pkpa-change-requests.reject', $change) }}" class="flex gap-2">@csrf<input name="rejection_reason" class="rounded-xl border border-slate-300 px-3 py-2 text-sm" placeholder="Alasan tolak"><button class="rounded-xl border border-rose-200 px-4 py-2 text-sm font-black text-rose-700">Tolak</button></form>
            @endif
            @if($change->status === 'approved' && ! $isInternalReplacement && auth()->user()->hasRole('koordinator_kp'))
                <form method="POST" action="{{ route('management.pkpa-change-requests.apply', $change) }}">@csrf<button class="rounded-xl bg-slate-950 px-4 py-2 text-sm font-black text-white">Terapkan Revisi</button></form>
            @endif
            <a href="{{ route('management.pkpa-publications.show', $change->publication) }}" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-black text-slate-700">Kembali</a>
        </div>
    </section>

    @if($isInternalReplacement)
        <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200"><p class="text-xs font-black uppercase text-slate-500">Mahasiswa</p><p class="mt-2 text-2xl font-black">{{ data_get($change->impact_summary, 'affected_students', $change->items->count()) }}</p></div>
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200"><p class="text-xs font-black uppercase text-slate-500">Wahana</p><p class="mt-2 font-black">{{ implode(', ', data_get($change->impact_summary, 'domains', [])) ?: '-' }}</p></div>
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200"><p class="text-xs font-black uppercase text-slate-500">Dosen pengganti</p><p class="mt-2 font-black">{{ data_get($change->impact_summary, 'replacement_name', '-') }}</p></div>
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200"><p class="text-xs font-black uppercase text-slate-500">Serah terima</p><p class="mt-2 font-black">{{ \Illuminate\Support\Carbon::parse(data_get($change->impact_summary, 'requested_effective_date'))->format('d M Y') }}</p></div>
        </section>
    @endif

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
                    <div class="mt-3 rounded-xl bg-slate-50 px-3 py-3 text-sm text-slate-700">
                        <p><span class="font-black text-slate-900">Alasan:</span> {{ $change->reason ?: '-' }}</p>
                        @if(filled($item->notes))
                            <p class="mt-1"><span class="font-black text-slate-900">Catatan:</span> {{ $item->notes }}</p>
                        @endif
                    </div>
                    @if($item->change_type === 'internal_supervisor_change')
                        @php($oldInternal = collect(data_get($item->before_snapshot, 'supervisors', []))->firstWhere('supervisor_type', 'internal'))
                        <div class="mt-3 grid gap-3 md:grid-cols-3">
                            <div class="rounded-xl bg-slate-50 p-3 text-sm"><p class="text-xs font-black uppercase text-slate-500">Pembimbing lama</p><p class="mt-2 font-bold text-slate-900">{{ data_get($oldInternal, 'name_snapshot', '-') }}</p></div>
                            <div class="rounded-xl bg-cyan-50 p-3 text-sm"><p class="text-xs font-black uppercase text-cyan-700">Pembimbing baru</p><p class="mt-2 font-bold text-cyan-950">{{ data_get($item->proposed_snapshot, 'internal_supervisor_name', '-') }}</p></div>
                            <div class="rounded-xl bg-amber-50 p-3 text-sm"><p class="text-xs font-black uppercase text-amber-700">Efektif</p><p class="mt-2 font-bold text-amber-950">{{ \Illuminate\Support\Carbon::parse(data_get($item->proposed_snapshot, 'effective_date'))->format('d M Y') }}</p></div>
                        </div>
                        @if(data_get($item->proposed_snapshot, 'transfer_partial_assessment'))
                            <div class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                                <p class="font-black">Nilai draf ikut dialihkan</p>
                                <p class="mt-1">Isi draf dari pembimbing lama tetap tersimpan beserta riwayat pembuatnya. Pembimbing baru harus meninjau sebelum mengirim dan mengunci nilai.</p>
                            </div>
                        @endif
                    @else
                        <div class="mt-3 grid gap-3 md:grid-cols-2">
                            <div class="rounded-xl bg-slate-50 p-3 text-sm"><p class="font-black text-slate-700">Sebelum</p><pre class="mt-2 whitespace-pre-wrap text-xs text-slate-600">{{ json_encode($item->before_snapshot, JSON_PRETTY_PRINT) }}</pre></div>
                            <div class="rounded-xl bg-cyan-50 p-3 text-sm"><p class="font-black text-cyan-800">Usulan</p><pre class="mt-2 whitespace-pre-wrap text-xs text-cyan-900">{{ json_encode($item->proposed_snapshot, JSON_PRETTY_PRINT) }}</pre></div>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </section>
</div>
@endsection
