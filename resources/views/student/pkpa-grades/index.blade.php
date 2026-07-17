@extends('layouts.app')

@section('title', 'Nilai PKPA')

@section('content')
<div class="space-y-6">
    <div class="rounded-3xl border border-sky-100 bg-white p-6 shadow-sm">
        <p class="text-sm font-bold uppercase tracking-wide text-cyan-700">Portal Nilai PKPA</p>
        <h1 class="mt-2 text-3xl font-black text-slate-950">Nilai Wahana PKPA</h1>
        <p class="mt-2 max-w-3xl text-sm text-slate-600">Nilai ini merupakan hasil penilaian untuk satu wahana PKPA dan belum merupakan nilai akhir keseluruhan Program PKPA.</p>
    </div>
    <div class="overflow-x-auto rounded-3xl border border-slate-100 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                <tr><th class="px-4 py-3">Wahana</th><th class="px-4 py-3">Tempat</th><th class="px-4 py-3">Status</th><th class="px-4 py-3 text-right">Nilai</th><th class="px-4 py-3">Tanggal</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($releases as $release)
                    <tr>
                        <td class="px-4 py-3 font-bold text-slate-900">{{ $release->gradeResult?->rotationRun?->practiceDomain?->name }}</td>
                        <td class="px-4 py-3">{{ $release->gradeResult?->rotationRun?->practiceSite?->name }}</td>
                        <td class="px-4 py-3">Dirilis</td>
                        <td class="px-4 py-3 text-right font-black">{{ $release->gradeResult?->final_score }} / {{ $release->gradeResult?->maximum_score }}</td>
                        <td class="px-4 py-3">{{ $release->released_at?->translatedFormat('d M Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">Belum ada nilai wahana yang dirilis.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
