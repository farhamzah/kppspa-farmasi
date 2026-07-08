@extends('layouts.app')
@section('title','Komponen Penilaian - '.config('app.name'))
@section('page_title','Komponen Penilaian')
@section('content')
<div class="space-y-6">
    <section class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-100">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div><h2 class="text-xl font-black text-slate-950">Komponen Penilaian</h2><p class="text-sm text-slate-500">Default mengikuti simulasi KP; koordinator bisa menambah, mengurangi, atau mengubah bobot internal tiap penilai.</p></div>
            <a href="{{ route('management.assessment-components.create') }}" class="rounded-2xl bg-cyan-700 px-4 py-2 text-sm font-bold text-white">Tambah Komponen</a>
        </div>
        <form class="mt-5 grid gap-3 md:grid-cols-4">
            <select name="period" class="rounded-2xl border-slate-200 text-sm"><option value="">Semua periode</option>@foreach($periods as $period)<option value="{{ $period->id }}" @selected(($filters['period'] ?? '') == $period->id)>{{ $period->name }}</option>@endforeach</select>
            <select name="assessor_type" class="rounded-2xl border-slate-200 text-sm"><option value="">Semua penilai</option>@foreach(['pembimbing_dalam'=>'Pembimbing Dalam','pembimbing_lapangan'=>'Pembimbing Lapangan','penguji'=>'Penguji'] as $key=>$label)<option value="{{ $key }}" @selected(($filters['assessor_type'] ?? '') === $key)>{{ $label }}</option>@endforeach</select>
            <button class="rounded-2xl border border-cyan-200 px-4 py-2 text-sm font-bold text-cyan-700">Filter</button>
        </form>
    </section>
    <section class="overflow-hidden rounded-3xl bg-white shadow-sm ring-1 ring-slate-100">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-black uppercase tracking-widest text-slate-500"><tr><th class="px-5 py-3">Periode</th><th class="px-5 py-3">Penilai</th><th class="px-5 py-3">Komponen</th><th class="px-5 py-3">Bobot Internal</th><th class="px-5 py-3">Status</th><th class="px-5 py-3">Aksi</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($components as $component)
                        <tr><td class="px-5 py-4">{{ $component->period->name }}</td><td class="px-5 py-4">{{ $component->assessorTypeLabel() }}</td><td class="px-5 py-4 font-bold">{{ $component->component_name }} @if($component->is_required)<span class="text-rose-600">*</span>@endif</td><td class="px-5 py-4">{{ $component->weight }}%</td><td class="px-5 py-4"><span class="rounded-full {{ $component->statusBadgeClass() }} px-3 py-1 text-xs font-bold">{{ $component->statusLabel() }}</span></td><td class="px-5 py-4"><a href="{{ route('management.assessment-components.edit', $component) }}" class="font-bold text-cyan-700">Edit</a></td></tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-10 text-center text-slate-500">Belum ada komponen penilaian.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-5">{{ $components->links() }}</div>
    </section>
    @foreach($weightTotals as $key => $total)
        @if((float) $total !== 100.0)
            @php
                [$periodId, $assessorType] = explode('-', $key, 2);
            @endphp
            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm font-semibold text-amber-800">Peringatan: total bobot internal periode ID {{ $periodId }} untuk {{ str_replace('_',' ', $assessorType) }} saat ini {{ $total }}%. Sistem tetap menormalkan perhitungan, tetapi idealnya 100%.</div>
        @endif
    @endforeach
</div>
@endsection
