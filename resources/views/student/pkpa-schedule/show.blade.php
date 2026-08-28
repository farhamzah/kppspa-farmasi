@extends('layouts.app')
@section('title', 'Detail Jadwal PKPA - '.config('app.name'))
@section('page_title', 'Detail Jadwal PKPA')

@section('content')
@php($acknowledged = $assignment->acknowledged_count > 0)
@php($acknowledgement = $assignment->acknowledgements->first())
<div class="space-y-5">
    @if(session('status'))<div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">{{ session('status') }}</div>@endif
    <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="text-xs font-black uppercase tracking-widest text-cyan-700">{{ $assignment->publication->code }}</p>
                <h2 class="mt-1 text-2xl font-black text-slate-950">{{ $assignment->practice_domain_name_snapshot }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ $assignment->practice_site_name_snapshot }} / {{ optional($assignment->start_date)->format('d M Y') }} - {{ optional($assignment->end_date)->format('d M Y') }}</p>
            </div>
            <span class="rounded-full {{ $acknowledged ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }} px-3 py-1 text-xs font-black">{{ $acknowledged ? 'Sudah Dibaca' : 'Belum Dibaca' }}</span>
        </div>
        <div class="mt-4 grid gap-3 md:grid-cols-3">
            <div class="rounded-xl bg-slate-50 px-4 py-3">
                <p class="text-xs font-black uppercase tracking-widest text-slate-500">Publikasi</p>
                <p class="mt-1 font-black text-slate-950">{{ $assignment->publication->title }}</p>
            </div>
            <div class="rounded-xl bg-slate-50 px-4 py-3">
                <p class="text-xs font-black uppercase tracking-widest text-slate-500">Hari Efektif</p>
                <p class="mt-1 font-black text-slate-950">{{ $assignment->effective_days_snapshot }} hari</p>
            </div>
            <div class="rounded-xl bg-slate-50 px-4 py-3">
                <p class="text-xs font-black uppercase tracking-widest text-slate-500">Jam Praktik</p>
                <p class="mt-1 font-black text-slate-950">{{ $assignment->practice_hours_snapshot ?: '-' }} jam</p>
            </div>
        </div>
    </section>

    <section class="grid gap-4 lg:grid-cols-2">
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <h3 class="text-lg font-black text-slate-950">Informasi Penempatan</h3>
            <dl class="mt-4 space-y-3 text-sm">
                <div><dt class="font-black text-slate-500">Mahasiswa</dt><dd class="mt-1 text-slate-950">{{ $assignment->student_name_snapshot }} / {{ $assignment->student_number_snapshot }}</dd></div>
                <div><dt class="font-black text-slate-500">Wahana</dt><dd class="mt-1 text-slate-950">{{ $assignment->practice_domain_name_snapshot }}{{ $assignment->practice_domain_option_name_snapshot ? ' / '.$assignment->practice_domain_option_name_snapshot : '' }}</dd></div>
                <div><dt class="font-black text-slate-500">Tempat Praktik</dt><dd class="mt-1 text-slate-950">{{ $assignment->practice_site_name_snapshot }}</dd></div>
                <div><dt class="font-black text-slate-500">Alamat</dt><dd class="mt-1 text-slate-950">{{ $assignment->practice_site_address_snapshot ?: '-' }}</dd></div>
                <div><dt class="font-black text-slate-500">Durasi</dt><dd class="mt-1 text-slate-950">{{ $assignment->duration_value_snapshot }} {{ $assignment->duration_unit_snapshot }} / {{ $assignment->effective_days_snapshot }} hari efektif</dd></div>
            </dl>
        </div>
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <h3 class="text-lg font-black text-slate-950">Pembimbing</h3>
            <div class="mt-4 grid gap-3">
                @foreach($assignment->supervisors as $supervisor)
                    <div class="rounded-xl bg-slate-50 p-4">
                        <p class="text-xs font-black uppercase text-slate-500">{{ $supervisor->supervisor_type === 'internal' ? 'Pembimbing Dalam' : 'Preseptor' }}</p>
                        <p class="mt-1 font-black text-slate-950">{{ $supervisor->display_name }}</p>
                        <p class="text-sm text-slate-500">{{ $supervisor->email_snapshot ?: '-' }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <h3 class="text-lg font-black text-slate-950">Konfirmasi Baca Jadwal</h3>
        <p class="mt-1 text-sm text-slate-500">Gunakan tombol ini setelah Anda memeriksa lokasi, rentang tanggal, dan pembimbing. Konfirmasi ini hanya menandai jadwal sudah dibaca.</p>
        @if($acknowledged)
            <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">
                Jadwal ini sudah Anda tandai dibaca{{ $acknowledgement?->acknowledged_at ? ' pada '. $acknowledgement->acknowledged_at->format('d M Y H:i') : '' }}.
            </div>
        @else
            <form method="POST" action="{{ route('student.pkpa-schedule.acknowledge', $assignment) }}" class="mt-4">
                @csrf
                <button class="rounded-xl bg-emerald-700 px-4 py-2 text-sm font-black text-white">Saya Sudah Membaca Jadwal Ini</button>
            </form>
        @endif
    </section>
</div>
@endsection
