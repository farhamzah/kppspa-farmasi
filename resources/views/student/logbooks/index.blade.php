@extends('layouts.app')
@section('title','Logbook KP - '.config('app.name'))
@section('page_title','Logbook KP')
@section('content')
<div class="space-y-5">
    @if($errors->any())<div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>@endif
    @if(! $assignment)
        <section class="rounded-2xl bg-white p-10 text-center shadow-sm ring-1 ring-slate-200">
            <h2 class="text-xl font-bold text-slate-950">Anda belum memiliki penempatan KP aktif.</h2>
            <p class="mt-2 text-sm text-slate-500">Logbook dapat dibuat setelah penempatan KP aktif atau berjalan.</p>
        </section>
    @else
        <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-widest text-teal-700">Penempatan Aktif</p>
                    <h2 class="mt-1 text-xl font-bold text-slate-950">{{ $assignment->place->name }}</h2>
                    <p class="mt-1 text-sm text-slate-500">Pembimbing Dalam: {{ $assignment->internalSupervisor ? lecturer_display_name($assignment->internalSupervisor) : '-' }} | Pembimbing Lapangan: {{ $assignment->fieldSupervisor ? field_supervisor_display_name($assignment->fieldSupervisor) : '-' }}</p>
                </div>
                <a href="{{ route('student.logbooks.create') }}" class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white">Tambah Logbook</a>
            </div>
        </section>
        <section class="grid gap-3 md:grid-cols-5">
            @foreach(['total'=>'Total','pending'=>'Menunggu','approved'=>'Disetujui','revision'=>'Revisi','rejected'=>'Ditolak'] as $key=>$label)
                <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
                    <p class="text-xs font-semibold uppercase text-slate-500">{{ $label }}</p>
                    <p class="mt-2 text-2xl font-bold text-slate-950">{{ $stats[$key] ?? 0 }}</p>
                </div>
            @endforeach
        </section>
        <section class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500"><tr><th class="px-4 py-3">Tanggal</th><th class="px-4 py-3">Kegiatan</th><th class="px-4 py-3">Durasi</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Bukti</th><th class="px-4 py-3 text-right">Aksi</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($logbooks as $logbook)
                            <tr>
                                <td class="px-4 py-4">{{ $logbook->activity_date->format('d M Y') }}</td>
                                <td class="px-4 py-4 font-semibold text-slate-900">{{ $logbook->activity_title }}</td>
                                <td class="px-4 py-4">{{ $logbook->activityDurationLabel() }}</td>
                                <td class="px-4 py-4"><span class="rounded-full px-2 py-1 text-xs font-semibold ring-1 {{ $logbook->statusBadgeClass() }}">{{ $logbook->statusLabel() }}</span></td>
                                <td class="px-4 py-4">{{ $logbook->hasEvidence() ? 'Ada' : '-' }}</td>
                                <td class="px-4 py-4 text-right"><a href="{{ route('student.logbooks.show',$logbook) }}" class="rounded-lg border border-teal-200 px-3 py-1.5 text-xs font-semibold text-teal-700">Detail</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-10 text-center text-slate-500">Belum ada logbook kegiatan.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t px-4 py-3">{{ $logbooks->links() }}</div>
        </section>
    @endif
</div>
@endsection
