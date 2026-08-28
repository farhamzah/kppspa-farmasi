@extends('layouts.app')
@section('title', 'PKPA Saya - '.config('app.name'))
@section('page_title', 'PKPA Saya')

@section('content')
<div class="space-y-5">
    @if(session('status'))<div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">{{ session('status') }}</div>@endif

    <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <p class="text-xs font-black uppercase tracking-widest text-cyan-700">Jadwal resmi PKPA</p>
        <h2 class="mt-1 text-2xl font-black text-slate-950">{{ $publication?->title ?: 'Belum ada jadwal publikasi' }}</h2>
        <p class="mt-1 text-sm text-slate-500">Jadwal ini berasal dari publikasi resmi program. Tanda baca hanya memastikan Anda sudah menerima informasi, bukan persetujuan perubahan jadwal.</p>
        @if($publication?->status === 'withdrawn')
            <div class="mt-4 rounded-xl bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-800">Publikasi ini sudah ditarik. Tunggu jadwal revisi dari pengelola.</div>
        @endif
    </section>

    <section class="grid gap-4 md:grid-cols-3">
        <article class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <p class="text-xs font-black uppercase tracking-widest text-slate-500">Jumlah Penempatan</p>
            <p class="mt-3 text-3xl font-black text-slate-950">{{ $summary['total'] }}</p>
            <p class="mt-1 text-sm text-slate-500">Wahana yang sudah dijadwalkan untuk Anda.</p>
        </article>
        <article class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <p class="text-xs font-black uppercase tracking-widest text-emerald-700">Sudah Dibaca</p>
            <p class="mt-3 text-3xl font-black text-emerald-700">{{ $summary['acknowledged'] }}</p>
            <p class="mt-1 text-sm text-slate-500">Jadwal yang sudah Anda tandai sebagai sudah dibaca.</p>
        </article>
        <article class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <p class="text-xs font-black uppercase tracking-widest text-amber-700">Perlu Dicek</p>
            <p class="mt-3 text-3xl font-black text-amber-700">{{ $summary['pending'] }}</p>
            <p class="mt-1 text-sm text-slate-500">Segera buka detailnya agar tidak ada penempatan yang terlewat.</p>
        </article>
    </section>

    <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse($assignments as $assignment)
            @php($acknowledged = $assignment->acknowledged_count > 0)
            <article class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-black uppercase tracking-widest text-cyan-700">{{ $assignment->practice_domain_name_snapshot }}</p>
                        <h3 class="mt-1 text-lg font-black text-slate-950">{{ $assignment->practice_site_name_snapshot }}</h3>
                    </div>
                    <span class="rounded-full {{ $acknowledged ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }} px-3 py-1 text-xs font-black">{{ $acknowledged ? 'Sudah Dibaca' : 'Belum Dibaca' }}</span>
                </div>
                <p class="mt-3 text-sm text-slate-500">{{ optional($assignment->start_date)->format('d M Y') }} - {{ optional($assignment->end_date)->format('d M Y') }}</p>
                <p class="mt-2 text-sm text-slate-500">{{ $assignment->practice_site_address_snapshot ?: 'Alamat belum tersedia' }}</p>
                @if($assignment->supervisors->isNotEmpty())
                    <div class="mt-4 space-y-2">
                        @foreach($assignment->supervisors as $supervisor)
                            <div class="rounded-xl bg-slate-50 px-3 py-2 text-sm">
                                <p class="font-black text-slate-950">{{ $supervisor->display_name }}</p>
                                <p class="text-xs text-slate-500">{{ $supervisor->supervisor_type === 'internal' ? 'Pembimbing Dalam' : 'Preseptor' }}</p>
                            </div>
                        @endforeach
                    </div>
                @endif
                <a href="{{ route('student.pkpa-schedule.show', $assignment) }}" class="mt-4 inline-flex rounded-xl bg-cyan-700 px-4 py-2 text-sm font-black text-white">Lihat Detail</a>
            </article>
        @empty
            <div class="rounded-2xl bg-white p-8 text-center text-sm font-semibold text-slate-500 shadow-sm ring-1 ring-slate-200 md:col-span-2 xl:col-span-3">Jadwal PKPA resmi Anda belum dipublikasikan.</div>
        @endforelse
    </section>

    @if($history->count() > 1)
        <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <h3 class="text-lg font-black text-slate-950">Riwayat Publikasi</h3>
            <div class="mt-3 space-y-2">
                @foreach($history as $item)
                    <div class="rounded-xl border border-slate-200 px-4 py-3 text-sm">
                        <p class="font-black text-slate-950">{{ $item->code }} - {{ $item->title }}</p>
                        <p class="mt-1 text-slate-500">
                            Status:
                            {{ match($item->status) {
                                'published' => 'Dipublikasikan',
                                'withdrawn' => 'Ditarik',
                                'superseded' => 'Digantikan revisi',
                                default => ucfirst((string) $item->status),
                            } }}
                            / {{ optional($item->published_at)->format('d M Y H:i') ?: '-' }}
                        </p>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</div>
@endsection
