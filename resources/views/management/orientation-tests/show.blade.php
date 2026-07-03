@extends('layouts.app')
@section('title','Detail Hasil Test - '.config('app.name'))
@section('page_title','Detail Hasil Test')
@section('content')
<div class="space-y-6">
    <section class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-100">
        <a href="{{ route('management.orientation-tests.index', request()->query()) }}" class="text-sm font-black text-cyan-700">Kembali ke hasil test</a>
        <h2 class="mt-4 text-2xl font-black text-slate-950">{{ $attempt->student->user->name }}</h2>
        <p class="mt-1 text-sm text-slate-500">{{ $attempt->student->nim ?: '-' }} - {{ $attempt->student->user->email }}</p>
        <div class="mt-5 grid gap-4 md:grid-cols-3">
            <div class="rounded-2xl bg-cyan-50 p-5"><p class="text-xs font-black uppercase tracking-widest text-cyan-700">Test</p><p class="mt-1 font-black">{{ $attempt->test->typeLabel() }}</p></div>
            <div class="rounded-2xl bg-emerald-50 p-5"><p class="text-xs font-black uppercase tracking-widest text-emerald-700">Skor</p><p class="mt-1 text-3xl font-black">{{ $attempt->score }}/{{ $attempt->max_score }}</p></div>
            <div class="rounded-2xl bg-slate-50 p-5"><p class="text-xs font-black uppercase tracking-widest text-slate-500">Submit</p><p class="mt-1 font-black">{{ $attempt->submitted_at?->format('d M Y H:i') }}</p></div>
        </div>
    </section>

    @foreach($attempt->answers as $answer)
        <section class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-100">
            <div class="flex items-start justify-between gap-4">
                <h3 class="font-black leading-7 text-slate-950">{{ $loop->iteration }}. {{ $answer->question->question_text }}</h3>
                <span class="rounded-full px-3 py-1 text-xs font-black {{ $answer->is_correct ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }}">{{ $answer->points_awarded }} poin</span>
            </div>
            <p class="mt-4 text-sm"><span class="font-black">Jawaban mahasiswa:</span> {{ $answer->selectedChoice() }}</p>
            <p class="mt-2 text-sm"><span class="font-black">Jawaban benar:</span> {{ $answer->question->correctChoice() }}</p>
            <p class="mt-4 rounded-2xl bg-slate-50 px-4 py-3 text-sm leading-6 text-slate-600">{{ $answer->question->explanation }}</p>
        </section>
    @endforeach
</div>
@endsection
