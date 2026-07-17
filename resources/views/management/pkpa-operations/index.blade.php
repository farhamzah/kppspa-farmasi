@extends('layouts.app')

@section('title', 'Operasional Rotasi PKPA')
@section('page_title', 'Operasional Rotasi PKPA')

@section('content')
@php
    $statusLabels = ['ready' => 'Siap', 'scheduled' => 'Terjadwal', 'active' => 'Aktif', 'on_hold' => 'Ditahan', 'awaiting_operational_review' => 'Menunggu Review', 'operational_complete' => 'Operasional Selesai'];
    $syncLabels = ['current' => 'Terkini', 'review_required' => 'Perlu Review'];
@endphp
<div class="space-y-6">
    @if($errors->any())
        <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-bold text-rose-700">{{ $errors->first() }}</div>
    @endif

    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @foreach(['Total Rotasi' => $summary['total'], 'Aktif' => $summary['active'], 'Perlu Review' => $summary['attention'], 'Selesai Operasional' => $summary['complete']] as $label => $value)
            <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-sky-100">
                <p class="text-xs font-black uppercase tracking-widest text-cyan-700">{{ $label }}</p>
                <p class="mt-2 text-3xl font-black text-slate-950">{{ $value }}</p>
            </div>
        @endforeach
    </section>

    <section class="grid gap-4 xl:grid-cols-[1.1fr_.9fr]">
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-sky-100">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-xs font-black uppercase tracking-widest text-cyan-700">Publikasi Current</p>
                    <h2 class="mt-1 text-xl font-black text-slate-950">Bentuk Runtime dari Jadwal Resmi</h2>
                </div>
                <a href="{{ route('management.pkpa-operations.export') }}" class="rounded-xl border border-emerald-200 px-4 py-2 text-sm font-black text-emerald-700">Ekspor CSV</a>
            </div>
            <div class="mt-4 grid gap-3">
                @forelse($publications as $publication)
                    <div class="flex flex-col gap-3 rounded-xl border border-sky-100 p-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <p class="truncate font-black text-slate-950">{{ $publication->code }}</p>
                            <p class="text-sm text-slate-500">{{ $publication->title }} / {{ $publication->program?->code }}</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <form method="POST" action="{{ route('management.pkpa-publications.rotation-runs.store', $publication) }}">@csrf<button class="rounded-xl bg-cyan-700 px-4 py-2 text-sm font-black text-white">Bentuk Runtime</button></form>
                            <form method="POST" action="{{ route('management.pkpa-publications.operations.sync', $publication) }}">@csrf<button class="rounded-xl border border-cyan-200 px-4 py-2 text-sm font-black text-cyan-700">Sinkronkan</button></form>
                        </div>
                    </div>
                @empty
                    <p class="rounded-xl bg-slate-50 p-4 text-sm text-slate-500">Belum ada publikasi current.</p>
                @endforelse
            </div>
        </div>

        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-sky-100">
            <p class="text-xs font-black uppercase tracking-widest text-cyan-700">Aturan Operasional</p>
            <h2 class="mt-1 text-xl font-black text-slate-950">Per Wahana Program</h2>
            <div class="mt-4 max-h-[26rem] space-y-3 overflow-y-auto pr-1">
                @foreach($programDomains as $programDomain)
                    <form method="POST" action="{{ route('management.pkpa-program-domains.operation-rule.store', $programDomain) }}" class="rounded-xl border border-sky-100 p-4">
                        @csrf
                        <p class="font-black text-slate-900">{{ $programDomain->program?->code }} / {{ $programDomain->practiceDomain?->name }}</p>
                        <div class="mt-3 grid gap-2 sm:grid-cols-2">
                            <label class="text-xs font-bold text-slate-600"><input type="checkbox" name="attendance_required" value="1" @checked($programDomain->activeOperationRule?->attendance_required ?? true)> Presensi wajib</label>
                            <label class="text-xs font-bold text-slate-600"><input type="checkbox" name="logbook_required" value="1" @checked($programDomain->activeOperationRule?->logbook_required ?? true)> Logbook wajib</label>
                            <select name="logbook_frequency" class="rounded-xl border-slate-200 text-sm">
                                @foreach(['daily' => 'Harian', 'weekly' => 'Mingguan', 'flexible' => 'Fleksibel'] as $value => $label)
                                    <option value="{{ $value }}" @selected(($programDomain->activeOperationRule?->logbook_frequency ?? 'daily') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <input name="minimum_logbook_entries" type="number" min="0" value="{{ $programDomain->activeOperationRule?->minimum_logbook_entries ?? 0 }}" class="rounded-xl border-slate-200 text-sm" placeholder="Minimal logbook">
                        </div>
                        <textarea name="instructions" rows="2" class="mt-2 w-full rounded-xl border-slate-200 text-sm" placeholder="Instruksi operasional">{{ $programDomain->activeOperationRule?->instructions }}</textarea>
                        <button class="mt-2 rounded-xl bg-slate-900 px-4 py-2 text-xs font-black text-white">Simpan Aturan</button>
                    </form>
                @endforeach
            </div>
        </div>
    </section>

    <section class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-sky-100">
        <div class="border-b border-sky-100 px-5 py-4">
            <h2 class="text-xl font-black text-slate-950">Daftar Runtime Rotasi</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-black uppercase tracking-widest text-slate-500"><tr><th class="px-4 py-3">Mahasiswa</th><th class="px-4 py-3">Wahana</th><th class="px-4 py-3">Periode</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Progress</th><th class="px-4 py-3">Aksi</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($runs as $run)
                        <tr>
                            <td class="px-4 py-3 font-black text-slate-900">{{ $run->student_core_user_id }}</td>
                            <td class="px-4 py-3">{{ $run->practiceDomain?->name }}<br><span class="text-xs text-slate-500">{{ $run->practiceSite?->name }}</span></td>
                            <td class="px-4 py-3">{{ $run->scheduled_start_date?->format('d M Y') }} - {{ $run->scheduled_end_date?->format('d M Y') }}</td>
                            <td class="px-4 py-3">{{ $statusLabels[$run->status] ?? $run->status }} / {{ $syncLabels[$run->publication_sync_status] ?? $run->publication_sync_status }}</td>
                            <td class="px-4 py-3">{{ optional($run->progressSnapshots->first())->progress_percentage ?? 0 }}%</td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-2">
                                    @if(in_array($run->status, ['ready', 'scheduled']))
                                        <form method="POST" action="{{ route('management.pkpa-rotation-runs.activate', $run) }}">@csrf<button class="rounded-lg bg-cyan-700 px-3 py-1.5 text-xs font-black text-white">Aktifkan</button></form>
                                    @endif
                                    <form method="POST" action="{{ route('management.pkpa-rotation-runs.snapshot', $run) }}">@csrf<button class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-black text-slate-700">Snapshot</button></form>
                                    <form method="POST" action="{{ route('management.pkpa-rotation-runs.complete', $run) }}">@csrf<button class="rounded-lg border border-emerald-200 px-3 py-1.5 text-xs font-black text-emerald-700">Complete</button></form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">Runtime rotasi belum terbentuk.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-5 py-4">{{ $runs->links() }}</div>
    </section>
</div>
@endsection
