@extends('layouts.app')

@section('title', 'Penilaian PKPA')

@section('content')
<div class="space-y-6">
    <div class="rounded-3xl border border-sky-100 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-sm font-bold uppercase tracking-wide text-cyan-700">Penilaian Per Wahana PKPA</p>
                <h1 class="mt-2 text-3xl font-black text-slate-950">Skema, assessment, finalisasi, dan release nilai wahana</h1>
                <p class="mt-2 max-w-3xl text-sm text-slate-600">Nilai di halaman ini hanya untuk satu rotasi/wahana PKPA dan belum merupakan nilai akhir keseluruhan Program PKPA.</p>
            </div>
            <a href="{{ route('management.pkpa-assessments.export') }}" class="inline-flex items-center justify-center rounded-2xl bg-cyan-700 px-4 py-3 text-sm font-bold text-white shadow-sm hover:bg-cyan-800">Export Rekap</a>
        </div>
    </div>

    <div class="grid gap-4 md:grid-cols-5">
        @foreach ($summary as $label => $value)
            <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
                <p class="text-xs font-bold uppercase text-slate-500">{{ str($label)->headline() }}</p>
                <p class="mt-2 text-2xl font-black text-slate-950">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    @if (session('status'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-800">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-bold text-rose-800">{{ $errors->first() }}</div>
    @endif

    <div class="grid gap-6 xl:grid-cols-[0.9fr_1.1fr]">
        <section class="rounded-3xl border border-slate-100 bg-white p-5 shadow-sm">
            <h2 class="text-xl font-black text-slate-950">Builder Skema</h2>
            <div class="mt-4 space-y-4">
                @foreach ($programDomains as $domain)
                    <div class="rounded-2xl border border-slate-100 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-black text-slate-900">{{ $domain->practiceDomain?->name }}</p>
                                <p class="text-xs text-slate-500">{{ $domain->program?->name }} · Current: {{ $domain->activeAssessmentScheme?->code ?? 'Belum ada' }}</p>
                            </div>
                            @if ($domain->activeAssessmentScheme)
                                <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700">Aktif</span>
                            @endif
                        </div>
                        <form method="POST" action="{{ route('management.pkpa-assessment-schemes.store', $domain) }}" class="mt-4 grid gap-3">
                            @csrf
                            <div class="grid gap-3 sm:grid-cols-2">
                                <input name="code" placeholder="Kode skema" class="rounded-2xl border-slate-200 text-sm" required>
                                <input name="name" placeholder="Nama skema" class="rounded-2xl border-slate-200 text-sm" required>
                                <input name="maximum_score" type="number" step="0.01" value="100" class="rounded-2xl border-slate-200 text-sm">
                                <select name="rounding_mode" class="rounded-2xl border-slate-200 text-sm">
                                    <option value="half_up">Half up</option>
                                    <option value="half_even">Half even</option>
                                    <option value="floor">Floor</option>
                                    <option value="ceil">Ceil</option>
                                </select>
                            </div>
                            <button class="rounded-2xl bg-slate-900 px-4 py-2 text-sm font-bold text-white">Buat Skema</button>
                        </form>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="rounded-3xl border border-slate-100 bg-white p-5 shadow-sm">
            <h2 class="text-xl font-black text-slate-950">Skema Terbaru</h2>
            <div class="mt-4 space-y-4">
                @forelse ($schemes as $scheme)
                    @php $weight = $scheme->components->where('status', 'active')->sum(fn ($c) => (float) $c->weight_percentage); @endphp
                    <div class="rounded-2xl border border-slate-100 p-4">
                        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                            <div>
                                <p class="font-black text-slate-900">{{ $scheme->code }} v{{ $scheme->version_number }} · {{ $scheme->name }}</p>
                                <p class="text-xs text-slate-500">{{ $scheme->programDomain?->practiceDomain?->name }} · Bobot aktif {{ number_format($weight, 2) }}%</p>
                            </div>
                            <form method="POST" action="{{ route('management.pkpa-assessment-schemes.activate', $scheme) }}">
                                @csrf
                                <button class="rounded-xl bg-cyan-700 px-3 py-2 text-xs font-bold text-white">Aktifkan</button>
                            </form>
                        </div>
                        @if ($scheme->status === 'draft')
                            <form method="POST" action="{{ route('management.pkpa-assessment-components.store', $scheme) }}" class="mt-4 grid gap-3 lg:grid-cols-7">
                                @csrf
                                <input name="code" placeholder="Kode" class="rounded-xl border-slate-200 text-xs" required>
                                <input name="name" placeholder="Komponen" class="rounded-xl border-slate-200 text-xs lg:col-span-2" required>
                                <select name="component_type" class="rounded-xl border-slate-200 text-xs"><option value="field_supervisor_assessment">PL</option><option value="internal_supervisor_assessment">PD</option><option value="special_task">Tugas</option><option value="rotation_report">Laporan</option><option value="custom">Custom</option></select>
                                <select name="assessor_type" class="rounded-xl border-slate-200 text-xs"><option value="field_supervisor">PL</option><option value="internal_supervisor">PD</option><option value="multiple">PL+PD</option><option value="coordinator">Koordinator</option><option value="system">Sistem</option></select>
                                <input name="weight_percentage" type="number" step="0.0001" placeholder="Bobot" class="rounded-xl border-slate-200 text-xs" required>
                                <input type="hidden" name="calculation_method" value="direct_score">
                                <input type="hidden" name="maximum_raw_score" value="100">
                                <button class="rounded-xl bg-slate-900 px-3 py-2 text-xs font-bold text-white">Tambah</button>
                            </form>
                        @endif
                        <div class="mt-3 flex flex-wrap gap-2">
                            @foreach ($scheme->components as $component)
                                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-700">{{ $component->code }} {{ $component->weight_percentage }}%</span>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">Belum ada skema penilaian.</p>
                @endforelse
            </div>
        </section>
    </div>

    <section class="rounded-3xl border border-slate-100 bg-white p-5 shadow-sm">
        <h2 class="text-xl font-black text-slate-950">Assessment Wahana</h2>
        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 text-sm">
                <thead class="text-left text-xs uppercase text-slate-500">
                    <tr><th class="px-3 py-2">Mahasiswa</th><th class="px-3 py-2">Wahana</th><th class="px-3 py-2">Status</th><th class="px-3 py-2">Nilai</th><th class="px-3 py-2">Aksi</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($assessments as $assessment)
                        <tr>
                            <td class="px-3 py-3 font-bold">{{ $assessment->rotationRun?->student_core_user_id }}</td>
                            <td class="px-3 py-3">{{ $assessment->rotationRun?->practiceDomain?->name }}</td>
                            <td class="px-3 py-3">{{ $assessment->status }} · {{ $assessment->completion_status }}</td>
                            <td class="px-3 py-3">{{ $assessment->gradeResult?->final_score ?? '-' }}</td>
                            <td class="px-3 py-3">
                                <div class="flex flex-wrap gap-2">
                                    <form method="POST" action="{{ route('management.pkpa-rotation-assessments.finalize', $assessment) }}">@csrf<button class="rounded-xl bg-slate-900 px-3 py-2 text-xs font-bold text-white">Finalisasi</button></form>
                                    @if ($assessment->gradeResult && $assessment->gradeResult->result_status !== 'released')
                                        <form method="POST" action="{{ route('management.pkpa-grade-results.release', $assessment->gradeResult) }}">@csrf<button class="rounded-xl bg-cyan-700 px-3 py-2 text-xs font-bold text-white">Release</button></form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $assessments->links() }}</div>
    </section>

    <section class="rounded-3xl border border-slate-100 bg-white p-5 shadow-sm">
        <h2 class="text-xl font-black text-slate-950">Buat Assessment dari Rotasi Ready</h2>
        <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($runs as $run)
                <div class="rounded-2xl border border-slate-100 p-4">
                    <p class="font-black text-slate-900">{{ $run->student_core_user_id }}</p>
                    <p class="text-sm text-slate-500">{{ $run->practiceDomain?->name }} · {{ $run->practiceSite?->name }}</p>
                    <p class="mt-1 text-xs text-slate-500">Readiness: {{ $run->academicReadinessReviews->first()?->status ?? '-' }}</p>
                    <form method="POST" action="{{ route('management.pkpa-rotation-assessments.store', $run) }}" class="mt-3">
                        @csrf
                        <button class="rounded-xl bg-cyan-700 px-3 py-2 text-xs font-bold text-white">Buat Assessment</button>
                    </form>
                </div>
            @endforeach
        </div>
    </section>
</div>
@endsection
