@extends('layouts.app')
@section('title', 'Kesiapan Penempatan PKPA - '.config('app.name'))
@section('page_title', 'Kesiapan Penempatan PKPA')
@section('content')
<div class="space-y-5">
    @if($errors->any())<div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $errors->first() }}</div>@endif
    <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <form method="GET" class="flex flex-col gap-3 md:flex-row md:items-end">
            <div class="flex-1"><label class="text-xs font-black uppercase tracking-widest text-slate-500">Program</label><select name="program_id" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"><option value="">Program terbaru</option>@foreach($programs as $item)<option value="{{ $item->id }}" @selected($program?->id === $item->id)>{{ $item->code }} - {{ $item->name }}</option>@endforeach</select></div>
            <button class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-black text-white">Periksa</button>
            @if($program)<a href="{{ route('management.pkpa-program-sites.index', ['program_id' => $program->id]) }}" class="rounded-xl border border-cyan-200 px-4 py-2 text-center text-sm font-black text-cyan-700">Kelola Tempat</a>@endif
        </form>
    </div>

    @if(! $program)
        <div class="rounded-2xl bg-white p-8 text-center text-sm text-slate-500 shadow-sm ring-1 ring-slate-200">Belum ada Program PKPA.</div>
    @else
        <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <span class="rounded-full px-3 py-1 text-xs font-black {{ $readiness['status'] === 'Siap menyusun penempatan' ? 'bg-emerald-50 text-emerald-700' : ($readiness['status'] === 'Perlu perhatian' ? 'bg-amber-50 text-amber-700' : 'bg-rose-50 text-rose-700') }}">{{ $readiness['status'] }}</span>
                    <h2 class="mt-3 text-2xl font-black text-slate-950">{{ $program->code }} - {{ $program->name }}</h2>
                    <p class="text-sm text-slate-500">Checklist ini hanya memvalidasi fondasi kapasitas, tempat, dan pembimbing. Penempatan mahasiswa belum dibuat di tahap ini.</p>
                </div>
                <div class="text-sm text-slate-500">{{ now()->format('d M Y H:i') }}</div>
            </div>
            <div class="mt-6 grid gap-3 md:grid-cols-4 xl:grid-cols-8">
                @foreach([
                    'participants' => 'Peserta',
                    'groups' => 'Kelompok',
                    'active_sites' => 'Tempat',
                    'availability_periods' => 'Availability',
                    'internal_supervisors' => 'Pembimbing Dalam',
                    'field_supervisors' => 'Pembimbing Lapangan',
                    'critical' => 'Isu Kritis',
                    'warnings' => 'Peringatan',
                ] as $key => $label)
                    <div class="rounded-xl bg-slate-50 p-4"><p class="text-xs font-black uppercase text-slate-500">{{ $label }}</p><p class="mt-1 text-xl font-black text-slate-950">{{ $readiness['summary'][$key] ?? 0 }}</p></div>
                @endforeach
            </div>
        </div>

        <div class="grid gap-4">
            @forelse($readiness['domains'] as $card)
                <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <h3 class="text-lg font-black text-slate-950">{{ $card['domain']?->name ?: 'Wahana tidak ditemukan' }}</h3>
                            <p class="mt-1 text-sm text-slate-500">{{ $card['sites'] }} tempat / {{ $card['availability'] }} availability / kapasitas {{ $card['capacity'] }} dari {{ $card['participants'] }} peserta</p>
                        </div>
                        <span class="rounded-full px-3 py-1 text-xs font-black {{ $card['status'] === 'Siap menyusun penempatan' ? 'bg-emerald-50 text-emerald-700' : ($card['status'] === 'Perlu perhatian' ? 'bg-amber-50 text-amber-700' : 'bg-rose-50 text-rose-700') }}">{{ $card['status'] }}</span>
                    </div>
                    <div class="mt-4 grid gap-3 md:grid-cols-4">
                        <div class="rounded-xl bg-slate-50 p-3"><p class="text-xs font-black uppercase text-slate-500">Tempat</p><p class="font-black">{{ $card['sites'] }}</p></div>
                        <div class="rounded-xl bg-slate-50 p-3"><p class="text-xs font-black uppercase text-slate-500">Kapasitas</p><p class="font-black">{{ $card['capacity'] }}</p></div>
                        <div class="rounded-xl bg-slate-50 p-3"><p class="text-xs font-black uppercase text-slate-500">Pembimbing Dalam</p><p class="font-black">{{ $card['internal_supervisors'] }}</p></div>
                        <div class="rounded-xl bg-slate-50 p-3"><p class="text-xs font-black uppercase text-slate-500">Tempat Tanpa PL</p><p class="font-black">{{ $card['field_missing'] }}</p></div>
                    </div>
                    @if(count($card['issues']) || count($card['warnings']))
                        <div class="mt-4 grid gap-3 md:grid-cols-2">
                            @if(count($card['issues']))
                                <div class="rounded-xl border border-rose-200 bg-rose-50 p-4">
                                    <p class="text-xs font-black uppercase tracking-widest text-rose-700">Isu Kritis</p>
                                    <ul class="mt-2 space-y-1 text-sm text-rose-800">@foreach($card['issues'] as $issue)<li>{{ $issue }}</li>@endforeach</ul>
                                </div>
                            @endif
                            @if(count($card['warnings']))
                                <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                                    <p class="text-xs font-black uppercase tracking-widest text-amber-700">Peringatan</p>
                                    <ul class="mt-2 space-y-1 text-sm text-amber-800">@foreach($card['warnings'] as $warning)<li>{{ $warning }}</li>@endforeach</ul>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            @empty
                <div class="rounded-2xl bg-white p-8 text-center text-sm text-slate-500 shadow-sm ring-1 ring-slate-200">Program belum memiliki wahana aktif.</div>
            @endforelse
        </div>
    @endif
</div>
@endsection
