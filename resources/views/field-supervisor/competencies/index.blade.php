@extends('layouts.app')
@section('title','Checklist Kompetensi - '.config('app.name'))
@section('page_title','Checklist Kompetensi')
@section('content')
<section class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
    <div class="border-b border-slate-100 p-5">
        <h2 class="text-lg font-black text-slate-950">Mahasiswa Bimbingan Preseptor</h2>
        <p class="mt-1 text-sm text-slate-500">Centang kompetensi ketika mahasiswa sudah mencapai indikator yang ditetapkan koordinator.</p>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500"><tr><th class="px-4 py-3">Mahasiswa</th><th class="px-4 py-3">Periode</th><th class="px-4 py-3">Tempat</th><th class="px-4 py-3">Capaian</th><th class="px-4 py-3 text-right">Aksi</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($assignments as $assignment)
                    <tr>
                        <td class="px-4 py-4"><div class="font-semibold text-slate-950">{{ $assignment->student->user->name }}</div><div class="text-xs text-slate-500">{{ $assignment->student->nim ?: '-' }}</div></td>
                        <td class="px-4 py-4">{{ $assignment->period->name }}</td>
                        <td class="px-4 py-4">{{ $assignment->place->name }}</td>
                        <td class="px-4 py-4 font-bold text-cyan-700">{{ $assignment->competencyAchievements->count() }} checklist</td>
                        <td class="px-4 py-4 text-right"><a href="{{ route('field-supervisor.competencies.show', $assignment) }}" class="rounded-lg border border-teal-200 px-3 py-1.5 text-xs font-semibold text-teal-700">Checklist</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-slate-500">Belum ada mahasiswa PKPA.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="border-t px-4 py-3">{{ $assignments->links() }}</div>
</section>
@endsection
