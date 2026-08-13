@extends('layouts.app')
@section('title',$test->typeLabel().' - '.config('app.name'))
@section('page_title',$test->typeLabel())
@section('content')
<form method="POST" action="{{ route('student.orientation-tests.submit', $test) }}" class="space-y-6">
    @csrf
    <section class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-100">
        <p class="text-xs font-black uppercase tracking-widest text-cyan-700">{{ $test->typeLabel() }}</p>
        <h2 class="mt-2 text-2xl font-black text-slate-950">{{ $test->title }}</h2>
        <p class="mt-2 text-sm leading-6 text-slate-600">{{ $test->description }}</p>
        @error('answers')
            <p class="mt-4 rounded-2xl bg-rose-50 px-4 py-3 text-sm font-bold text-rose-700">{{ $message }}</p>
        @enderror
    </section>

    @foreach($test->activeQuestions as $question)
        <section class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-100">
            <div class="flex gap-4">
                <span class="flex h-10 w-10 flex-none items-center justify-center rounded-2xl bg-cyan-700 text-sm font-black text-white">{{ $loop->iteration }}</span>
                <div class="min-w-0 flex-1">
                    <h3 class="text-lg font-black leading-7 text-slate-950">{{ $question->question_text }}</h3>
                    <div class="mt-5 grid gap-3">
                        @foreach($question->choices as $choiceIndex => $choice)
                            <label class="flex cursor-pointer gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:border-cyan-200 hover:bg-cyan-50">
                                <input required type="radio" name="answers[{{ $question->id }}]" value="{{ $choiceIndex }}" class="mt-1 border-slate-300 text-cyan-700 focus:ring-cyan-500">
                                <span>{{ $choice }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>
    @endforeach

    <div class="sticky bottom-4 z-10 rounded-3xl bg-white/95 p-4 shadow-xl shadow-slate-900/10 ring-1 ring-slate-100 backdrop-blur">
        <button class="w-full rounded-2xl bg-teal-600 px-5 py-3 text-sm font-black text-white shadow-lg shadow-teal-700/20">Kirim {{ $test->typeLabel() }}</button>
    </div>
</form>
@endsection
