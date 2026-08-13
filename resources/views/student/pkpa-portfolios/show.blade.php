@extends('layouts.app')

@section('title', 'Detail Portofolio PKPA')

@section('content')
<div class="space-y-6">
    <section class="rounded-3xl border border-slate-100 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="text-sm font-bold uppercase tracking-wide text-cyan-700">Portofolio PKPA</p>
                <h1 class="mt-2 text-3xl font-black text-slate-950">{{ data_get($portfolio->placement_snapshot, 'practice_domain') }}</h1>
                <p class="mt-2 max-w-3xl text-sm text-slate-600">{{ data_get($portfolio->placement_snapshot, 'practice_site') }} - {{ data_get($portfolio->placement_snapshot, 'start_date') }} s.d. {{ data_get($portfolio->placement_snapshot, 'end_date') }}</p>
            </div>
            <span class="rounded-full bg-cyan-50 px-4 py-2 text-sm font-bold text-cyan-700">{{ $portfolio->statusLabel() }}</span>
        </div>
    </section>
    @if(session('status'))<div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-bold text-emerald-800">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm font-bold text-rose-800">{{ $errors->first() }}</div>@endif

    <section class="grid gap-4 md:grid-cols-4">
        @foreach(['sections' => 'Bagian', 'cases' => 'Studi Kasus', 'reflections' => 'Refleksi', 'documentation' => 'Bukti Kegiatan'] as $key => $label)
            <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
                <p class="text-xs font-bold uppercase text-slate-500">{{ $label }}</p>
                <p class="mt-2 text-2xl font-black text-slate-950">{{ data_get($portfolio->progress_snapshot, 'counts.'.$key, 0) }}</p>
            </div>
        @endforeach
    </section>

    <section class="rounded-3xl border border-slate-100 bg-white p-5 shadow-sm">
        <h2 class="text-lg font-black text-slate-950">Kemajuan</h2>
        <ul class="mt-3 space-y-2 text-sm text-slate-700">
            @forelse(data_get($portfolio->progress_snapshot, 'blocking', []) as $item)
                <li class="rounded-2xl bg-amber-50 px-4 py-3 font-semibold text-amber-800">{{ $item }}</li>
            @empty
                <li class="rounded-2xl bg-emerald-50 px-4 py-3 font-semibold text-emerald-800">Siap dikirim.</li>
            @endforelse
        </ul>
    </section>

    <div class="grid gap-6 xl:grid-cols-2">
        <section class="rounded-3xl border border-slate-100 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-black text-slate-950">Pakta Integritas</h2>
            <p class="mt-2 text-sm text-slate-600">{{ $portfolio->integrity_pact_text }}</p>
            <form method="POST" action="{{ route('student.pkpa-portfolios.integrity.acknowledge', $portfolio) }}" class="mt-4">@csrf<button class="rounded-2xl bg-slate-900 px-4 py-3 text-sm font-bold text-white">Setujui Pakta</button></form>
        </section>
        <section class="rounded-3xl border border-slate-100 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-black text-slate-950">Unduhan</h2>
            <div class="mt-4 flex flex-wrap gap-3">
                <form method="POST" action="{{ route('student.pkpa-portfolios.exports.store', [$portfolio, 'docx']) }}">@csrf<button class="rounded-2xl bg-cyan-700 px-4 py-3 text-sm font-bold text-white">Unduh DOCX</button></form>
                <form method="POST" action="{{ route('student.pkpa-portfolios.exports.store', [$portfolio, 'pdf']) }}">@csrf<button class="rounded-2xl bg-cyan-700 px-4 py-3 text-sm font-bold text-white">Unduh PDF</button></form>
            </div>
        </section>
    </div>

    <section class="rounded-3xl border border-slate-100 bg-white p-5 shadow-sm">
        <h2 class="text-lg font-black text-slate-950">Studi Kasus</h2>
        <form method="POST" action="{{ route('student.pkpa-portfolios.cases.store', $portfolio) }}" class="mt-4 grid gap-3 md:grid-cols-2">@csrf
            <input name="case_code" placeholder="Kode kasus" class="rounded-2xl border-slate-200 text-sm" required>
            <input type="date" name="case_date" class="rounded-2xl border-slate-200 text-sm">
            <input name="patient_initials" placeholder="Inisial pasien" class="rounded-2xl border-slate-200 text-sm">
            <input name="gender" placeholder="Jenis kelamin" class="rounded-2xl border-slate-200 text-sm">
            <input name="age" type="number" placeholder="Umur" class="rounded-2xl border-slate-200 text-sm">
            <textarea name="complaint" placeholder="Keluhan" class="rounded-2xl border-slate-200 text-sm md:col-span-2"></textarea>
            <textarea name="diagnosis" placeholder="Diagnosis" class="rounded-2xl border-slate-200 text-sm md:col-span-2"></textarea>
            <textarea name="drp" placeholder="DRP" class="rounded-2xl border-slate-200 text-sm md:col-span-2"></textarea>
            <textarea name="intervention" placeholder="Intervensi dan monitoring" class="rounded-2xl border-slate-200 text-sm md:col-span-2"></textarea>
            <label class="flex items-center gap-2 text-sm font-semibold md:col-span-2"><input type="checkbox" name="anonymization_confirmed" value="1" required> Saya memastikan tidak ada nama, nomor rekam medis, alamat, atau kontak pasien.</label>
            <button class="rounded-2xl bg-slate-900 px-4 py-3 text-sm font-bold text-white md:col-span-2">Simpan Studi Kasus</button>
        </form>
    </section>

    <section class="grid gap-6 xl:grid-cols-3">
        <form method="POST" action="{{ route('student.pkpa-portfolios.reflections.store', $portfolio) }}" class="rounded-3xl border border-slate-100 bg-white p-5 shadow-sm">@csrf
            <h2 class="text-lg font-black text-slate-950">Refleksi</h2>
            <div class="mt-4 grid gap-3"><input type="number" name="week_number" min="1" placeholder="Minggu" class="rounded-2xl border-slate-200 text-sm" required><input name="unit" placeholder="Unit" class="rounded-2xl border-slate-200 text-sm"><textarea name="target" placeholder="Target" class="rounded-2xl border-slate-200 text-sm"></textarea><textarea name="achievement" placeholder="Pencapaian dan refleksi" class="rounded-2xl border-slate-200 text-sm"></textarea><button class="rounded-2xl bg-slate-900 px-4 py-3 text-sm font-bold text-white">Simpan</button></div>
        </form>
        <form method="POST" action="{{ route('student.pkpa-portfolios.self-assessments.store', $portfolio) }}" class="rounded-3xl border border-slate-100 bg-white p-5 shadow-sm">@csrf
            <h2 class="text-lg font-black text-slate-950">Penilaian Diri</h2>
            <div class="mt-4 grid gap-3"><input name="aspect" placeholder="Aspek/kompetensi" class="rounded-2xl border-slate-200 text-sm" required><input type="number" name="score" min="1" max="5" placeholder="Skor 1-5" class="rounded-2xl border-slate-200 text-sm" required><textarea name="evidence_experience" placeholder="Bukti/pengalaman" class="rounded-2xl border-slate-200 text-sm"></textarea><textarea name="improvement_plan" placeholder="Rencana perbaikan" class="rounded-2xl border-slate-200 text-sm"></textarea><button class="rounded-2xl bg-slate-900 px-4 py-3 text-sm font-bold text-white">Simpan</button></div>
        </form>
        <form method="POST" enctype="multipart/form-data" action="{{ route('student.pkpa-portfolios.documentation.store', $portfolio) }}" class="rounded-3xl border border-slate-100 bg-white p-5 shadow-sm">@csrf
            <h2 class="text-lg font-black text-slate-950">Dokumentasi</h2>
            <div class="mt-4 grid gap-3"><input name="activity" placeholder="Kegiatan" class="rounded-2xl border-slate-200 text-sm" required><input type="date" name="activity_date" class="rounded-2xl border-slate-200 text-sm"><input type="file" name="file" class="rounded-2xl border border-slate-200 p-2 text-sm"><label class="flex items-center gap-2 text-sm"><input type="checkbox" name="anonymization_confirmed" value="1" required> Anonim</label><label class="flex items-center gap-2 text-sm"><input type="checkbox" name="consent_confirmed" value="1" required> Izin ada</label><button class="rounded-2xl bg-slate-900 px-4 py-3 text-sm font-bold text-white">Simpan</button></div>
        </form>
    </section>

    <section class="rounded-3xl border border-slate-100 bg-white p-5 shadow-sm">
        <h2 class="text-lg font-black text-slate-950">Pemeriksaan</h2>
        <div class="mt-4 flex flex-wrap gap-3">
            <form method="POST" action="{{ route('student.pkpa-portfolios.submit', $portfolio) }}">@csrf<button class="rounded-2xl bg-cyan-700 px-4 py-3 text-sm font-bold text-white">Kirim ke PL</button></form>
            <form method="POST" action="{{ route('student.pkpa-portfolios.submit-internal', $portfolio) }}">@csrf<button class="rounded-2xl bg-cyan-700 px-4 py-3 text-sm font-bold text-white">Kirim ke PD</button></form>
        </div>
    </section>
</div>
@endsection
