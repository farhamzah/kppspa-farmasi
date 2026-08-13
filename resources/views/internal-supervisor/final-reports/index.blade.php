@extends('layouts.app')

@section('title','Pemeriksaan Laporan - '.config('app.name'))
@section('page_title','Pemeriksaan Laporan')

@section('content')
<div class="si-page">
    <section class="si-card p-5">
        <form method="GET" class="grid gap-3 md:grid-cols-[minmax(0,1fr)_220px_auto]">
            <input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Cari nama/NIM" class="si-input mt-0">
            <select name="status" class="si-input mt-0">
                <option value="">Semua Status</option>
                @foreach(['draft'=>'Draf','menunggu_review'=>'Menunggu Pemeriksaan','revisi'=>'Revisi','disetujui'=>'Disetujui','ditolak'=>'Ditolak'] as $value=>$label)
                    <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <button class="si-btn si-btn-primary">Filter</button>
        </form>
    </section>

    <section class="si-table-wrap">
        <div class="overflow-x-auto">
            <table class="si-data-table">
                <colgroup>
                    <col class="w-[23%]">
                    <col class="w-[25%]">
                    <col class="w-[8%]">
                    <col class="w-[14%]">
                    <col class="w-[18%]">
                    <col class="w-[12%]">
                </colgroup>
                <thead>
                    <tr>
                        <th>Mahasiswa</th>
                        <th>Tempat</th>
                        <th>Versi</th>
                        <th>Status</th>
                        <th>Dikirim</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($reports as $report)
                        <tr>
                            <td>
                                <div class="font-semibold text-slate-950">{{ $report->assignment->student->user->name }}</div>
                                <div class="text-xs text-slate-500">{{ $report->assignment->student->nim ?: '-' }}</div>
                            </td>
                            <td>{{ $report->assignment->place->name }}</td>
                            <td class="font-semibold text-slate-900">{{ $report->current_version }}</td>
                            <td><span class="rounded-full px-2.5 py-1 text-xs font-semibold ring-1 {{ $report->statusBadgeClass() }}">{{ $report->statusLabel() }}</span></td>
                            <td>{{ $report->submitted_at?->format('d M Y H:i') ?? '-' }}</td>
                            <td><a href="{{ route('internal-supervisor.final-reports.show',$report) }}" class="si-btn si-btn-secondary min-h-9 px-3 py-1.5 text-xs text-cyan-700">Detail</a></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-10 text-center text-slate-500">Belum ada laporan mahasiswa bimbingan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-sky-100 px-4 py-3">{{ $reports->links() }}</div>
    </section>
</div>
@endsection
