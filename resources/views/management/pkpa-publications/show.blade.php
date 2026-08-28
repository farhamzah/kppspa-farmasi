@extends('layouts.app')
@section('title', 'Detail Publikasi PKPA - '.config('app.name'))
@section('page_title', 'Detail Publikasi PKPA')

@section('content')
@php
    $publicationStatusLabels = ['publishing' => 'Diproses', 'published' => 'Diterbitkan', 'superseded' => 'Digantikan', 'withdrawn' => 'Ditarik'];
    $assignmentStatusLabels = ['scheduled' => 'Terjadwal', 'revised' => 'Direvisi', 'cancelled' => 'Dibatalkan'];
    $notificationStatusLabels = ['sent' => 'Terkirim', 'pending' => 'Menunggu', 'failed' => 'Gagal', 'skipped' => 'Dilewati'];
@endphp
<div class="space-y-5">
    @if(session('status'))<div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700">{{ $errors->first() }}</div>@endif

    <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="text-xs font-black uppercase tracking-widest text-cyan-700">Salinan resmi</p>
                <h2 class="mt-1 text-2xl font-black text-slate-950">{{ $publication->code }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ $publication->title }} / {{ $publication->program->code }} / status {{ $publicationStatusLabels[$publication->status] ?? $publication->status }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('management.pkpa-publications.export', $publication) }}" class="rounded-xl border border-emerald-200 px-4 py-2 text-sm font-black text-emerald-700">Ekspor Excel Resmi</a>
                @if($publication->status === 'published')
                    <a href="{{ route('management.pkpa-change-requests.create', $publication) }}" class="rounded-xl border border-cyan-200 px-4 py-2 text-sm font-black text-cyan-700">Ajukan Revisi</a>
                @endif
            </div>
        </div>
        <div class="mt-5 grid gap-3 md:grid-cols-5">
            <div class="rounded-xl bg-slate-50 p-4"><p class="text-xs font-black uppercase text-slate-500">Penempatan</p><p class="text-xl font-black">{{ $publication->assignments->count() }}</p></div>
            <div class="rounded-xl bg-slate-50 p-4"><p class="text-xs font-black uppercase text-slate-500">Mahasiswa</p><p class="text-xl font-black">{{ $publication->assignments->pluck('student_core_user_id')->unique()->count() }}</p></div>
            <div class="rounded-xl bg-slate-50 p-4"><p class="text-xs font-black uppercase text-slate-500">Sudah Konfirmasi</p><p class="text-xl font-black">{{ $acks->where('acknowledgement_type', 'acknowledged')->count() }}</p></div>
            <div class="rounded-xl bg-slate-50 p-4"><p class="text-xs font-black uppercase text-slate-500">Terkini</p><p class="text-xl font-black">{{ $publication->is_current ? 'Ya' : 'Tidak' }}</p></div>
            <div class="rounded-xl bg-slate-50 p-4"><p class="text-xs font-black uppercase text-slate-500">Terbit</p><p class="text-xl font-black">{{ optional($publication->published_at)->format('d M Y') ?: '-' }}</p></div>
        </div>
        <div class="mt-5 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 text-sm text-slate-700">
            <p class="font-black text-slate-950">Ringkasan publikasi</p>
            <p class="mt-1">Halaman ini adalah salinan resmi yang dibaca portal mahasiswa dan pembimbing. Jika ada perubahan setelah terbit, gunakan menu revisi agar riwayat lama tetap aman untuk audit.</p>
        </div>
    </section>

    <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <h3 class="text-lg font-black text-slate-950">Jadwal Resmi</h3>
        <div class="mt-4 overflow-x-auto">
            <table class="min-w-[1100px] w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs font-black uppercase tracking-widest text-slate-500"><tr><th class="px-3 py-3">Mahasiswa</th><th>Wahana</th><th>Tempat</th><th>Tanggal</th><th>PD</th><th>PL</th><th>Status</th></tr></thead>
                <tbody>
                    @foreach($publication->assignments as $assignment)
                        <tr class="border-t border-slate-200">
                            <td class="px-3 py-3"><span class="font-black text-slate-950">{{ $assignment->student_name_snapshot }}</span><br><span class="text-xs text-slate-500">{{ $assignment->student_number_snapshot }}</span></td>
                            <td class="px-3 py-3">{{ $assignment->practice_domain_name_snapshot }}{{ $assignment->practice_domain_option_name_snapshot ? ' / '.$assignment->practice_domain_option_name_snapshot : '' }}</td>
                            <td class="px-3 py-3">{{ $assignment->practice_site_name_snapshot }}</td>
                            <td class="px-3 py-3">{{ optional($assignment->start_date)->format('d M Y') }} - {{ optional($assignment->end_date)->format('d M Y') }}</td>
                            <td class="px-3 py-3">{{ $assignment->supervisors->firstWhere('supervisor_type', 'internal')?->display_name ?: '-' }}</td>
                            <td class="px-3 py-3">{{ $assignment->supervisors->firstWhere('supervisor_type', 'field')?->display_name ?: '-' }}</td>
                            <td class="px-3 py-3">{{ $assignmentStatusLabels[$assignment->status] ?? $assignment->status }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="grid gap-4 lg:grid-cols-2">
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <h3 class="text-lg font-black text-slate-950">Notifikasi</h3>
            <div class="mt-3 space-y-2">
                @forelse($notifications as $delivery)
                    <div class="rounded-xl border border-slate-200 px-4 py-3 text-sm">{{ ['placement_published' => 'Jadwal diterbitkan', 'placement_revised' => 'Jadwal direvisi', 'placement_withdrawn' => 'Jadwal ditarik'][$delivery->event_type] ?? $delivery->event_type }} / {{ $delivery->channel === 'mail' ? 'Email' : 'Dalam Aplikasi' }} / {{ $notificationStatusLabels[$delivery->status] ?? $delivery->status }} ke {{ $delivery->recipient_name_snapshot ?: $delivery->recipient_core_user_id }}</div>
                @empty
                    <p class="text-sm text-slate-500">Belum ada notifikasi untuk publikasi ini.</p>
                @endforelse
            </div>
        </div>
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <h3 class="text-lg font-black text-slate-950">Tarik Publikasi</h3>
            <p class="mt-1 text-sm text-slate-500">Penarikan mencabut publikasi terkini dan mencatat alasan. Salinan lama tetap tersimpan untuk audit.</p>
            @if($publication->status === 'published' && auth()->user()->hasRole('koordinator_kp'))
                <form method="POST" action="{{ route('management.pkpa-publications.withdraw', $publication) }}" class="mt-4 space-y-3">
                    @csrf
                    <textarea name="withdrawal_reason" rows="3" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" placeholder="Alasan penarikan"></textarea>
                    <button class="rounded-xl bg-rose-700 px-4 py-2 text-sm font-black text-white">Tarik Publikasi</button>
                </form>
            @else
                <p class="mt-4 text-sm text-slate-500">Publikasi hanya bisa ditarik jika statusnya masih diterbitkan dan Anda sedang masuk sebagai Koordinator PKPA.</p>
            @endif
        </div>
    </section>
</div>
@endsection
