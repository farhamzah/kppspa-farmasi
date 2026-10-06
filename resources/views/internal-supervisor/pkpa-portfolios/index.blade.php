@extends('layouts.app')
@section('title', 'Pemeriksaan Portofolio')
@section('page_title', 'Pemeriksaan Portofolio')
@section('content')
<div class="space-y-5">
    <h2 class="text-sm font-semibold text-cyan-800">Wahana PKPA</h2>
    @if($errors->any())<p class="rounded-lg bg-rose-50 p-4 text-sm text-rose-800">{{ $errors->first() }}</p>@endif
    <form method="GET" class="grid gap-3 rounded-lg bg-white p-4 sm:grid-cols-2 lg:grid-cols-4">
        <label class="grid gap-1 text-sm font-semibold">Cari mahasiswa atau tempat<input name="q" value="{{ request('q') }}" class="rounded-lg border-slate-200"></label>
        <label class="grid gap-1 text-sm font-semibold">Wahana<select name="domain" class="rounded-lg border-slate-200"><option value="">Semua wahana</option>@foreach(\App\Models\PkpaPracticeDomain::orderBy('name')->get() as $domain)<option value="{{ $domain->id }}" @selected(request('domain') == $domain->id)>{{ $domain->name }}</option>@endforeach</select></label>
        <label class="grid gap-1 text-sm font-semibold">Status<select name="status" class="rounded-lg border-slate-200"><option value="ready" @selected(request('status', 'ready') === 'ready')>Siap Divalidasi</option><option value="all" @selected(request('status') === 'all')>Semua Portofolio</option></select></label>
        <div class="flex items-end gap-2"><button class="min-h-11 rounded-lg bg-cyan-700 px-4 text-sm font-bold text-white">Terapkan</button><a href="{{ route('internal-supervisor.pkpa-portfolios.index') }}" class="p-3 text-sm font-semibold">Reset</a></div>
    </form>
    <x-pkpa.bulk-validation :action="route('internal-supervisor.pkpa-portfolios.bulk-approve')" label="Setujui Terpilih" />
    @forelse($runs->groupBy(fn ($run) => $run->practiceDomain?->name ?: 'Wahana lainnya') as $domainName => $domainRuns)
        <section class="overflow-hidden rounded-lg bg-white ring-1 ring-slate-200">
            <h2 class="border-b border-slate-100 px-5 py-4 text-lg font-bold">{{ $domainName }}</h2>
            <div class="divide-y divide-slate-100">
                @foreach($domainRuns as $run)
                    @php($portfolio = $run->currentPortfolio)
                    <article class="flex flex-wrap items-center gap-4 p-5">
                        @if($portfolio && in_array($portfolio->status, \App\Models\PkpaRotationPortfolio::internalReviewStatuses(), true))
                            <input type="checkbox" name="ids[]" value="{{ $portfolio->id }}" form="bulk-validation" aria-label="Pilih portofolio {{ $run->studentDisplayName() }}">
                        @endif
                        <div class="min-w-0 flex-1"><p class="font-bold">{{ $run->studentDisplayName() }}</p><p class="mt-1 text-sm text-slate-500">{{ $run->studentDisplaySecondary() }} · {{ $run->practiceSite?->name }}</p><p class="mt-2 text-sm font-semibold text-cyan-700">{{ $portfolio?->statusLabel() ?? 'Belum diisi' }}</p></div>
                        @if($portfolio)<a href="{{ route('internal-supervisor.pkpa-portfolios.show', $portfolio) }}" class="rounded-lg border border-cyan-200 px-4 py-3 text-sm font-bold text-cyan-800">Lihat Detail</a>@endif
                    </article>
                @endforeach
            </div>
        </section>
    @empty
        <p class="rounded-lg bg-white p-6 text-sm text-slate-500">Tidak ada portofolio pada filter ini.</p>
    @endforelse
    {{ $pagination->links() }}
</div>
@endsection
