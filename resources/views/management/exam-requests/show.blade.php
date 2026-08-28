@extends('layouts.app')
@section('title','Detail Pengajuan Ujian - '.config('app.name'))
@section('page_title','Detail Pengajuan Ujian')
@section('content')
<div class="grid gap-5 lg:grid-cols-[1fr_360px]">
    <x-ui.card>
        <p class="text-sm text-slate-500">{{ $examRequest->assignment->student->user->name }} | {{ $examRequest->assignment->student->nim ?: '-' }}</p>
        <h2 class="mt-1 text-2xl font-bold text-slate-950">{{ $examRequest->assignment->place->name }}</h2>
        <span class="mt-3 inline-flex rounded-full px-3 py-1 text-xs font-semibold ring-1 {{ $examRequest->statusBadgeClass() }}">{{ $examRequest->statusLabel() }}</span>
        <div class="mt-5 grid gap-4 md:grid-cols-2 text-sm"><div class="rounded-xl bg-slate-50 p-4"><p class="font-semibold">Pembimbing Dalam</p><p>{{ $examRequest->assignment->internalSupervisor ? lecturer_display_name($examRequest->assignment->internalSupervisor) : '-' }}</p></div><div class="rounded-xl bg-slate-50 p-4"><p class="font-semibold">Laporan Akhir</p><p>{{ $examRequest->assignment->finalReport?->statusLabel() ?? '-' }}</p></div></div>
        @if($examRequest->exam)<a href="{{ route('management.exams.show',$examRequest->exam) }}" class="mt-5 inline-flex rounded-lg border border-cyan-200 px-4 py-2 text-sm font-semibold text-cyan-700">Lihat Jadwal</a>@endif
    </x-ui.card>
    <aside class="space-y-5">
        <x-ui.card>
            <h3 class="font-bold text-slate-950">Aksi Pengajuan</h3>
            <form method="POST" action="{{ route('management.exam-requests.approve',$examRequest) }}" class="mt-4">@csrf<textarea name="review_note" rows="2" placeholder="Catatan opsional" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea><button class="mt-3 w-full rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white">Setujui</button></form>
            <form method="POST" action="{{ route('management.exam-requests.revision',$examRequest) }}" class="mt-4">@csrf<textarea name="review_note" rows="2" required placeholder="Catatan revisi" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea><button class="mt-3 w-full rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white">Minta Revisi</button></form>
            <form method="POST" action="{{ route('management.exam-requests.reject',$examRequest) }}" class="mt-4">@csrf<textarea name="review_note" rows="2" required placeholder="Alasan penolakan" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea><button class="mt-3 w-full rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white">Tolak</button></form>
            @if($examRequest->canBeScheduled())<a href="{{ route('management.exam-requests.schedule',$examRequest) }}" class="mt-4 block rounded-lg bg-cyan-700 px-4 py-2 text-center text-sm font-semibold text-white">Jadwalkan Ujian</a>@endif
        </x-ui.card>
    </aside>
</div>
@endsection
