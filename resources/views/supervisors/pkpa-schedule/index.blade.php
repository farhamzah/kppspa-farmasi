@extends('layouts.app')
@section('title', 'Jadwal PKPA - '.config('app.name'))
@section('page_title', 'Jadwal PKPA')

@section('content')
@php($routePrefix = $type === 'internal' ? 'internal-supervisor' : 'field-supervisor')
<div class="space-y-5">
    <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <p class="text-xs font-black uppercase tracking-widest text-cyan-700">{{ $type === 'internal' ? 'Pembimbing Dalam' : 'Preseptor' }}</p>
        <h2 class="mt-1 text-2xl font-black text-slate-950">Jadwal Bimbingan PKPA</h2>
        <p class="mt-1 text-sm text-slate-500">Daftar ini berasal dari publikasi resmi program. Silakan cek setiap penempatan agar pembimbingan berjalan sesuai jadwal.</p>
        @if($publication)
            <div class="mt-4 rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-600">
                <p class="font-black text-slate-950">{{ $publication->code }} - {{ $publication->title }}</p>
                <p class="mt-1">Publikasi aktif untuk portal ini.</p>
            </div>
        @endif
    </section>

    <section class="grid gap-4 md:grid-cols-3">
        <article class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <p class="text-xs font-black uppercase tracking-widest text-slate-500">Mahasiswa Binaan</p>
            <p class="mt-3 text-3xl font-black text-slate-950">{{ $summary['total'] }}</p>
            <p class="mt-1 text-sm text-slate-500">Jumlah penempatan yang perlu Anda dampingi.</p>
        </article>
        <article class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <p class="text-xs font-black uppercase tracking-widest text-emerald-700">Sudah Dicek</p>
            <p class="mt-3 text-3xl font-black text-emerald-700">{{ $summary['acknowledged'] }}</p>
            <p class="mt-1 text-sm text-slate-500">Penempatan yang sudah Anda tandai dibaca.</p>
        </article>
        <article class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <p class="text-xs font-black uppercase tracking-widest text-amber-700">Perlu Ditinjau</p>
            <p class="mt-3 text-3xl font-black text-amber-700">{{ $summary['pending'] }}</p>
            <p class="mt-1 text-sm text-slate-500">Masih ada jadwal yang sebaiknya dibuka lebih dulu.</p>
        </article>
    </section>

    <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <div class="overflow-x-auto">
            <table class="min-w-[900px] w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs font-black uppercase tracking-widest text-slate-500"><tr><th class="px-3 py-3">Mahasiswa</th><th>Wahana</th><th>Tempat</th><th>Tanggal</th><th>Status</th><th>Aksi</th></tr></thead>
                <tbody>
                    @forelse($assignments as $assignment)
                        @php($acknowledged = $assignment->acknowledged_count > 0)
                        <tr class="border-t border-slate-200">
                            <td class="px-3 py-3"><span class="font-black text-slate-950">{{ $assignment->student_name_snapshot }}</span><br><span class="text-xs text-slate-500">{{ $assignment->student_number_snapshot }}</span></td>
                            <td class="px-3 py-3">{{ $assignment->practice_domain_name_snapshot }}</td>
                            <td class="px-3 py-3">{{ $assignment->practice_site_name_snapshot }}</td>
                            <td class="px-3 py-3">{{ optional($assignment->start_date)->format('d M Y') }} - {{ optional($assignment->end_date)->format('d M Y') }}</td>
                            <td class="px-3 py-3"><span class="rounded-full {{ $acknowledged ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }} px-3 py-1 text-xs font-black">{{ $acknowledged ? 'Sudah Dibaca' : 'Belum Dibaca' }}</span></td>
                            <td class="px-3 py-3"><a href="{{ route($routePrefix.'.pkpa-schedule.show', $assignment) }}" class="font-black text-cyan-700">Detail</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-3 py-8 text-center text-sm text-slate-500">Belum ada jadwal PKPA resmi untuk akun Anda.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
