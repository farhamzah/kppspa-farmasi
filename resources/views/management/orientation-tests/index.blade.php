@extends('layouts.app')
@section('title','Hasil Pre/Post Test - '.config('app.name'))
@section('page_title','Hasil Pre/Post Test')
@section('content')
<div class="space-y-6">
    <section class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-100">
        <h2 class="text-xl font-black text-slate-950">Pemantauan Hasil Pre/Post Test</h2>
        <form class="mt-5 grid gap-3 md:grid-cols-[1fr_220px_140px]">
            <input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Cari nama, NIM, email" class="rounded-2xl border-slate-200 text-sm">
            <select name="type" class="rounded-2xl border-slate-200 text-sm">
                <option value="">Semua test</option>
                <option value="pre" @selected(($filters['type'] ?? '') === 'pre')>Pre-Test</option>
                <option value="post" @selected(($filters['type'] ?? '') === 'post')>Post-Test</option>
            </select>
            <button class="rounded-2xl bg-slate-950 px-4 py-2 text-sm font-black text-white">Filter</button>
        </form>
    </section>

    <section class="overflow-hidden rounded-3xl bg-white shadow-sm ring-1 ring-slate-100">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-black uppercase tracking-widest text-slate-500"><tr><th class="px-5 py-3">Mahasiswa</th><th class="px-5 py-3">Test</th><th class="px-5 py-3">Skor</th><th class="px-5 py-3">Dikirim</th><th class="px-5 py-3 text-right">Aksi</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($attempts as $attempt)
                        <tr>
                            <td class="px-5 py-4"><p class="font-bold text-slate-950">{{ $attempt->student->user->name }}</p><p class="text-xs text-slate-500">{{ $attempt->student->nim ?: $attempt->student->user->email }}</p></td>
                            <td class="px-5 py-4">{{ $attempt->test->typeLabel() }}</td>
                            <td class="px-5 py-4"><span class="rounded-full bg-cyan-50 px-3 py-1 text-xs font-black text-cyan-700">{{ $attempt->score }}/{{ $attempt->max_score }}</span></td>
                            <td class="px-5 py-4">{{ $attempt->submitted_at?->format('d M Y H:i') }}</td>
                            <td class="px-5 py-4 text-right"><a href="{{ route('management.orientation-tests.show', $attempt) }}" class="rounded-xl border border-cyan-200 px-3 py-2 text-xs font-black text-cyan-700">Detail</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-10 text-center text-slate-500">Belum ada hasil test.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t px-5 py-4">{{ $attempts->links() }}</div>
    </section>
</div>
@endsection
