@extends('layouts.app')
@section('title','Hasil '.$attempt->test->typeLabel().' - '.config('app.name'))
@section('page_title','Hasil '.$attempt->test->typeLabel())
@section('content')
<div class="space-y-6">
    <section class="rounded-3xl bg-white p-8 shadow-sm ring-1 ring-slate-100">
        <p class="text-xs font-black uppercase tracking-widest text-cyan-700">{{ $attempt->test->typeLabel() }}</p>
        <h2 class="mt-2 text-2xl font-black text-slate-950">{{ $attempt->test->title }}</h2>
        <div class="mt-6 grid gap-4 md:grid-cols-3">
            <div class="rounded-2xl bg-cyan-50 p-5"><p class="text-xs font-black uppercase tracking-widest text-cyan-700">Skor</p><p class="mt-2 text-4xl font-black text-slate-950">{{ $attempt->score }}</p></div>
            <div class="rounded-2xl bg-slate-50 p-5"><p class="text-xs font-black uppercase tracking-widest text-slate-500">Maksimal</p><p class="mt-2 text-4xl font-black text-slate-950">{{ $attempt->max_score }}</p></div>
            <div class="rounded-2xl bg-emerald-50 p-5"><p class="text-xs font-black uppercase tracking-widest text-emerald-700">Persentase</p><p class="mt-2 text-4xl font-black text-slate-950">{{ number_format((float) $attempt->percentage, 0) }}%</p></div>
        </div>
        <p class="mt-4 text-sm text-slate-500">Dikirim: {{ $attempt->submitted_at?->format('d M Y H:i') }}</p>
    </section>

    @foreach($attempt->answers as $answer)
        <section class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-100">
            <div class="flex items-start justify-between gap-4">
                <h3 class="text-base font-black leading-7 text-slate-950">{{ $loop->iteration }}. {{ $answer->question->question_text }}</h3>
                <span class="rounded-full px-3 py-1 text-xs font-black {{ $answer->is_correct ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }}">{{ $answer->is_correct ? 'Benar' : 'Salah' }}</span>
            </div>
            <div class="mt-4 grid gap-3 md:grid-cols-2">
                <div class="rounded-2xl bg-slate-50 p-4"><p class="text-xs font-black uppercase tracking-widest text-slate-500">Jawaban Anda</p><p class="mt-1 text-sm font-bold text-slate-800">{{ $answer->selectedChoice() }}</p></div>
                <div class="rounded-2xl bg-emerald-50 p-4"><p class="text-xs font-black uppercase tracking-widest text-emerald-700">Jawaban Benar</p><p class="mt-1 text-sm font-bold text-slate-800">{{ $answer->question->correctChoice() }}</p></div>
            </div>
            <p class="mt-4 rounded-2xl bg-cyan-50 px-4 py-3 text-sm leading-6 text-cyan-900">{{ $answer->question->explanation }}</p>
        </section>
    @endforeach
</div>
@endsection
