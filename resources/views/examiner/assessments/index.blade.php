@extends('layouts.app')
@section('title','Penilaian Ujian - '.config('app.name'))
@section('page_title','Penilaian Ujian')
@section('content')
<section class="overflow-hidden rounded-3xl bg-white shadow-sm ring-1 ring-slate-100"><div class="p-6"><h2 class="text-xl font-black text-slate-950">Ujian yang Ditugaskan</h2></div><div class="overflow-x-auto"><table class="min-w-full divide-y divide-slate-100 text-sm"><tbody class="divide-y divide-slate-100">@forelse($exams as $exam)<tr><td class="px-5 py-4"><p class="font-bold">{{ $exam->assignment->student->user->name }}</p><p class="text-xs text-slate-500">{{ $exam->assignment->student->nim }} · {{ $exam->scheduleLabel() }}</p></td><td class="px-5 py-4">{{ $exam->assignment->place->name }}</td><td class="px-5 py-4"><a href="{{ route('examiner.assessments.show',$exam) }}" class="font-bold text-cyan-700">Input Nilai</a></td></tr>@empty<tr><td class="px-5 py-10 text-center text-slate-500">Belum ada jadwal ujian.</td></tr>@endforelse</tbody></table></div><div class="p-5">{{ $exams->links() }}</div></section>
@endsection
