@extends('layouts.app')
@section('title','Logbook PKPA - '.config('app.name'))
@section('page_title','Logbook PKPA')
@section('content')
<div class="space-y-5">
    @if($errors->any())<div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>@endif
    @if(! $assignment)
        <section class="rounded-2xl bg-white p-10 text-center shadow-sm ring-1 ring-slate-200">
            <h2 class="text-xl font-bold text-slate-950">Anda belum memiliki penempatan PKPA aktif.</h2>
            <p class="mt-2 text-sm text-slate-500">Logbook dapat dibuat setelah penempatan PKPA aktif atau berjalan.</p>
        </section>
    @else
        <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-widest text-teal-700">Penempatan Aktif</p>
                    <h2 class="mt-1 text-xl font-bold text-slate-950">{{ $assignment->place->name }}</h2>
                    <p class="mt-1 text-sm text-slate-500">Pembimbing Dalam: {{ $assignment->internalSupervisor ? lecturer_display_name($assignment->internalSupervisor) : '-' }} | Pembimbing Lapangan: {{ $assignment->fieldSupervisor ? field_supervisor_display_name($assignment->fieldSupervisor) : '-' }}</p>
                </div>
                <a href="{{ route('student.pkpa-journals.create') }}" class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white">Tambah Logbook</a>
            </div>
        </section>
        <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <div class="grid gap-5 lg:grid-cols-[1fr_1.4fr] lg:items-start">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-widest text-teal-700">Periode Kerja PKPA</p>
                    <h2 class="mt-1 text-xl font-bold text-slate-950">Dasar Perhitungan Absen</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-600">
                        Tanggal mulai, tanggal selesai, dan pola hari kerja dipakai untuk menghitung jumlah hari kerja seharusnya.
                    </p>
                    <div class="mt-4 grid gap-3 sm:grid-cols-3">
                        <div class="rounded-xl bg-slate-50 px-4 py-3">
                            <p class="text-xs font-semibold uppercase text-slate-500">Mulai</p>
                            <p class="mt-1 text-sm font-bold text-slate-950">{{ $assignment->started_at?->format('d M Y') ?? '-' }}</p>
                        </div>
                        <div class="rounded-xl bg-slate-50 px-4 py-3">
                            <p class="text-xs font-semibold uppercase text-slate-500">Selesai</p>
                            <p class="mt-1 text-sm font-bold text-slate-950">{{ $assignment->ended_at?->format('d M Y') ?? '-' }}</p>
                        </div>
                        <div class="rounded-xl bg-teal-50 px-4 py-3 ring-1 ring-teal-100">
                            <p class="text-xs font-semibold uppercase text-teal-700">Hari Kerja</p>
                            <p class="mt-1 text-sm font-bold text-teal-900">{{ $assignment->expectedWorkdaysCount() }} hari</p>
                            <p class="mt-1 text-xs text-teal-700">{{ $assignment->workdayPatternLabel() }}</p>
                        </div>
                    </div>
                </div>
                <form method="POST" action="{{ route('student.pkpa-journals.work-period.update') }}" class="grid gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-4 md:grid-cols-2">
                    @csrf
                    <div>
                        <label class="text-sm font-semibold text-slate-700">Tanggal Mulai PKPA</label>
                        <input type="date" name="started_at" value="{{ old('started_at', $assignment->started_at?->format('Y-m-d')) }}" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                        @error('started_at')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-slate-700">Tanggal Selesai PKPA</label>
                        <input type="date" name="ended_at" value="{{ old('ended_at', $assignment->ended_at?->format('Y-m-d')) }}" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                        @error('ended_at')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="md:col-span-2">
                        <label class="text-sm font-semibold text-slate-700">Hari Kerja Dalam Seminggu</label>
                        <select name="workday_pattern" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                            <option value="senin_jumat" @selected(old('workday_pattern', $assignment->workday_pattern ?: 'senin_jumat') === 'senin_jumat')>Senin sampai Jumat</option>
                            <option value="senin_sabtu" @selected(old('workday_pattern', $assignment->workday_pattern ?: 'senin_jumat') === 'senin_sabtu')>Senin sampai Sabtu</option>
                        </select>
                        @error('workday_pattern')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="md:col-span-2">
                        <button class="w-full rounded-lg bg-teal-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm shadow-teal-600/20">Simpan Periode Kerja</button>
                    </div>
                </form>
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
                                <td class="px-4 py-4 text-right"><a href="{{ route('student.pkpa-journals.show',$logbook) }}" class="rounded-lg border border-teal-200 px-3 py-1.5 text-xs font-semibold text-teal-700">Detail</a></td>
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
