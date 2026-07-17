@extends('layouts.app')

@section('title', 'Pelaporan dan Analytics')

@section('content')
<div class="space-y-6">
    <section class="rounded-3xl border border-slate-100 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-sm font-bold uppercase tracking-wide text-cyan-700">Pelaporan dan Analytics</p>
                <h1 class="mt-2 text-3xl font-black text-slate-950">Ringkasan program, penempatan, operasional, nilai, kelulusan, dan dokumen</h1>
            </div>
            <a href="{{ route('management.pkpa-analytics.export', request()->only('program_id')) }}" class="rounded-2xl bg-cyan-700 px-4 py-3 text-sm font-bold text-white">Ekspor CSV</a>
        </div>
        <form method="GET" class="mt-4 flex max-w-xl gap-3">
            <select name="program_id" class="flex-1 rounded-2xl border-slate-200 text-sm">
                <option value="">Semua program</option>
                @foreach($programs as $program)
                    <option value="{{ $program->id }}" @selected($selectedProgramId === $program->id)>{{ $program->name }}</option>
                @endforeach
            </select>
            <button class="rounded-2xl bg-slate-900 px-4 py-2 text-sm font-bold text-white">Filter</button>
        </form>
    </section>

    <div class="grid gap-6 xl:grid-cols-2">
        @foreach($analytics as $section => $items)
            <section class="rounded-3xl border border-slate-100 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-black text-slate-950">{{ str($section)->headline() }}</h2>
                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    @foreach($items as $label => $value)
                        <div class="rounded-2xl bg-slate-50 p-4">
                            <p class="text-xs font-bold uppercase text-slate-500">{{ str($label)->headline() }}</p>
                            <p class="mt-2 text-2xl font-black text-slate-950">{{ $value }}</p>
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>
</div>
@endsection
