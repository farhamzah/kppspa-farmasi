@extends('layouts.app')
@section('title','Detail Sidang Bimbingan - '.config('app.name'))
@section('page_title','Detail Sidang')
@section('content')
<x-ui.card>
    <p class="text-sm text-slate-500">{{ $exam->assignment->student->user->name }} | {{ $exam->assignment->student->nim ?: '-' }}</p>
    <h2 class="mt-1 text-2xl font-bold text-slate-950">{{ $exam->assignment->place->name }}</h2>
    <div class="mt-5 grid gap-4 md:grid-cols-3"><div class="rounded-xl bg-slate-50 p-4"><p class="text-xs text-slate-500">Jadwal</p><p class="font-bold">{{ $exam->scheduleLabel() }}</p></div><div class="rounded-xl bg-slate-50 p-4"><p class="text-xs text-slate-500">Penguji</p><p class="font-bold">{{ $exam->examinerNamesLabel() }}</p></div><div class="rounded-xl bg-slate-50 p-4"><p class="text-xs text-slate-500">Status</p><p class="font-bold">{{ $exam->statusLabel() }}</p></div></div>
    <div class="mt-5 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">Input nilai sidang akan tersedia pada tahap berikutnya.</div>
</x-ui.card>
@endsection
