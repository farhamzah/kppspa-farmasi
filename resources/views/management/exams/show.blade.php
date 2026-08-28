@extends('layouts.app')
@section('title','Detail Jadwal Ujian - '.config('app.name'))
@section('page_title','Detail Jadwal Ujian')
@section('content')
<div class="grid gap-5 lg:grid-cols-[1fr_360px]">
    <x-ui.card>
        <p class="text-sm text-slate-500">{{ $exam->assignment->student->user->name }} | {{ $exam->assignment->student->nim ?: '-' }}</p>
        <h2 class="mt-1 text-2xl font-bold text-slate-950">{{ $exam->assignment->place->name }}</h2>
        <span class="mt-3 inline-flex rounded-full px-3 py-1 text-xs font-semibold ring-1 {{ $exam->statusBadgeClass() }}">{{ $exam->statusLabel() }}</span>
        <div class="mt-5 grid gap-4 md:grid-cols-2"><div class="rounded-xl bg-slate-50 p-4"><p class="text-xs text-slate-500">Jadwal</p><p class="font-bold">{{ $exam->scheduleLabel() }}</p></div><div class="rounded-xl bg-slate-50 p-4"><p class="text-xs text-slate-500">Mode</p><p class="font-bold">{{ $exam->modeLabel() }}</p></div><div class="rounded-xl bg-slate-50 p-4"><p class="text-xs text-slate-500">Pembimbing</p><p class="font-bold">{{ $exam->supervisor ? lecturer_display_name($exam->supervisor) : '-' }}</p></div><div class="rounded-xl bg-slate-50 p-4"><p class="text-xs text-slate-500">Penguji</p><p class="font-bold">{{ $exam->examinerNamesLabel() }}</p></div></div>
        <p class="mt-4 text-sm text-slate-600">Ruangan: {{ $exam->room ?: '-' }} | Link: {{ $exam->meeting_link ?: '-' }}</p>
    </x-ui.card>
    <aside class="space-y-5">
        <x-ui.card><a href="{{ route('management.exams.edit',$exam) }}" class="block rounded-lg bg-cyan-700 px-4 py-2 text-center text-sm font-semibold text-white">Edit Jadwal</a><form method="POST" action="{{ route('management.exams.cancel',$exam) }}" class="mt-4">@csrf<input name="reason" required placeholder="Alasan cancel" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"><button class="mt-2 w-full rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white">Batalkan</button></form><form method="POST" action="{{ route('management.exams.complete',$exam) }}" class="mt-4">@csrf<button class="w-full rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white">Tandai Selesai</button></form></x-ui.card>
    </aside>
</div>
@endsection
