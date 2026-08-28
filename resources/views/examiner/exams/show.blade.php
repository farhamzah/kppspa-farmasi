@extends('layouts.app')
@section('title','Detail Ujian Penguji - '.config('app.name'))
@section('page_title','Detail Ujian')
@section('content')
<x-ui.card>
    <p class="text-sm text-slate-500">{{ $exam->assignment->student->user->name }} | {{ $exam->assignment->student->nim ?: '-' }}</p>
    <h2 class="mt-1 text-2xl font-bold text-slate-950">{{ $exam->assignment->place->name }}</h2>
    <div class="mt-5 grid gap-4 md:grid-cols-4"><div class="rounded-xl bg-slate-50 p-4"><p class="text-xs text-slate-500">Jadwal</p><p class="font-bold">{{ $exam->scheduleLabel() }}</p></div><div class="rounded-xl bg-slate-50 p-4"><p class="text-xs text-slate-500">Pembimbing Dalam</p><p class="font-bold">{{ $exam->supervisor ? lecturer_display_name($exam->supervisor) : '-' }}</p></div><div class="rounded-xl bg-slate-50 p-4"><p class="text-xs text-slate-500">Mode</p><p class="font-bold">{{ $exam->modeLabel() }}</p></div><div class="rounded-xl bg-slate-50 p-4"><p class="text-xs text-slate-500">Status</p><p class="font-bold">{{ $exam->statusLabel() }}</p></div></div>
    <div class="mt-5 rounded-xl border border-cyan-200 bg-cyan-50 px-4 py-3 text-sm text-cyan-800">Gunakan menu Penilaian Ujian untuk mengisi komponen nilai penguji pada jadwal ini.</div>
</x-ui.card>
@endsection
