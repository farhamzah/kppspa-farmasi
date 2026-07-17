@extends('layouts.app')
@section('title', 'Timeline Rotasi PKPA - '.config('app.name'))
@section('page_title', 'Timeline Rotasi PKPA')
@section('content')
<div class="space-y-5">
    <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div>
                <p class="text-xs font-black uppercase tracking-widest text-cyan-700">Rancangan Internal - Belum Dipublikasikan</p>
                <h2 class="mt-1 text-2xl font-black text-slate-950">{{ $plan->program?->code }} - {{ $plan->name }} v{{ $plan->version_number }}</h2>
                <p class="text-sm text-slate-500">Urutan rotasi dapat berbeda untuk setiap mahasiswa. Overlap dan jeda ditandai per baris.</p>
            </div>
            <a href="{{ route('management.pkpa-placement-planner.index', ['program_id' => $plan->pkpa_program_id, 'plan_id' => $plan->id]) }}" class="rounded-xl border border-cyan-200 px-4 py-2 text-sm font-black text-cyan-700">Kembali ke Matriks</a>
        </div>
    </div>

    <div class="grid gap-4">
        @forelse($timeline as $row)
            <article class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h3 class="font-black text-slate-950">{{ $row['enrollment']?->student_name_snapshot }}</h3>
                        <p class="text-xs text-slate-500">{{ $row['enrollment']?->student_number }}</p>
                    </div>
                    <span class="text-xs font-black text-slate-500">{{ count($row['items']) }} rotasi</span>
                </div>
                <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    @foreach($row['items'] as $item)
                        @php($assignment = $item['assignment'])
                        <div class="rounded-xl border {{ $item['has_overlap'] ? 'border-rose-200 bg-rose-50' : ($item['gap_days'] > 7 ? 'border-amber-200 bg-amber-50' : 'border-slate-200 bg-slate-50') }} p-4">
                            <p class="text-xs font-black uppercase tracking-widest {{ $item['has_overlap'] ? 'text-rose-700' : 'text-cyan-700' }}">{{ $assignment->start_date?->format('d M Y') }} - {{ $assignment->end_date?->format('d M Y') }}</p>
                            <p class="mt-1 font-black text-slate-950">{{ $item['label'] }}</p>
                            @if($item['has_overlap'])<p class="mt-2 text-xs font-bold text-rose-700">Konflik: overlap dengan rotasi sebelumnya.</p>@endif
                            @if($item['gap_days'] > 0)<p class="mt-2 text-xs font-bold text-slate-500">Jeda {{ $item['gap_days'] }} hari.</p>@endif
                        </div>
                    @endforeach
                </div>
            </article>
        @empty
            <div class="rounded-2xl bg-white p-8 text-center text-sm text-slate-500 shadow-sm ring-1 ring-slate-200">Belum ada penempatan pada rancangan ini.</div>
        @endforelse
    </div>
</div>
@endsection
