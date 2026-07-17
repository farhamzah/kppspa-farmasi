@extends('layouts.app')
@section('title', 'Monitoring Logbook PKPA')
@section('page_title', 'Monitoring Logbook PKPA')
@section('content')
<div class="space-y-5">
    @if($errors->any())<div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-bold text-rose-700">{{ $errors->first() }}</div>@endif
    <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-sky-100"><p class="text-xs font-black uppercase tracking-widest text-cyan-700">{{ $run->student_core_user_id }}</p><h2 class="mt-1 text-2xl font-black">{{ $run->practiceSite?->name }}</h2></section>
    <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-sky-100">
        <h3 class="text-lg font-black">Logbook Tervalidasi Lapangan</h3>
        <div class="mt-4 space-y-3">
            @foreach($run->logbookEntries->whereIn('status', ['approved', 'reviewed_by_internal']) as $entry)
                <form method="POST" action="{{ route('internal-supervisor.pkpa-logbooks.monitoring', $entry) }}" class="rounded-xl bg-slate-50 p-3">
                    @csrf
                    <p class="font-bold">{{ $entry->title }} / {{ $entry->entry_date?->format('d M Y') }}</p>
                    <p class="mt-1 text-sm text-slate-500">{{ $entry->learning_outcomes }}</p>
                    <textarea name="comments" class="mt-2 w-full rounded-xl border-slate-200 text-sm" placeholder="Catatan monitoring pembimbing dalam" required></textarea>
                    <button class="mt-2 rounded-lg bg-cyan-700 px-3 py-1.5 text-xs font-black text-white">Simpan Catatan</button>
                </form>
            @endforeach
        </div>
    </section>
</div>
@endsection
