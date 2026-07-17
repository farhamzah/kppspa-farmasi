@extends('layouts.app')
@section('title', 'Jadwal PKPA - '.config('app.name'))
@section('page_title', 'Jadwal PKPA')

@section('content')
@php($routePrefix = $type === 'internal' ? 'internal-supervisor' : 'field-supervisor')
<div class="space-y-5">
    <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <p class="text-xs font-black uppercase tracking-widest text-cyan-700">{{ $type === 'internal' ? 'Pembimbing Dalam' : 'Pembimbing Lapangan' }}</p>
        <h2 class="mt-1 text-2xl font-black text-slate-950">Jadwal Bimbingan PKPA</h2>
        <p class="mt-1 text-sm text-slate-500">Daftar ini berasal dari publikasi resmi. Tanda membaca jadwal bukan persetujuan.</p>
    </section>

    <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <div class="overflow-x-auto">
            <table class="min-w-[900px] w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs font-black uppercase tracking-widest text-slate-500"><tr><th class="px-3 py-3">Mahasiswa</th><th>Wahana</th><th>Tempat</th><th>Tanggal</th><th>Status</th><th>Aksi</th></tr></thead>
                <tbody>
                    @forelse($assignments as $assignment)
                        @php($acknowledged = $assignment->acknowledgements()->where('core_user_id', auth()->user()->core_user_id)->where('acknowledgement_type', 'acknowledged')->exists())
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
