@extends('layouts.app')
@section('title','Pemeriksaan Payload TU - '.config('app.name'))
@section('page_title','Pemeriksaan Integrasi TU')
@section('content')
<div class="space-y-5">
    <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="text-xs font-black uppercase tracking-widest text-cyan-700">Simulasi payload</p>
                <h2 class="mt-2 text-2xl font-black text-slate-950">Pratinjau Dokumen KP untuk TU</h2>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500">Halaman ini hanya membaca pratinjau payload lokal. Tidak ada permintaan keluar dan tidak ada perubahan data lintas aplikasi.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('management.integration.external-document-references.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Referensi Draf</a>
                <a href="{{ route('management.integration.safa-public-info-preview') }}" class="rounded-lg border border-cyan-200 px-4 py-2 text-sm font-semibold text-cyan-700">Periksa SAFA</a>
                <a href="{{ route('management.integration.tu-payload-preview.json', request()->query()) }}" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Pratinjau JSON</a>
            </div>
        </div>
    </section>

    <section class="grid gap-4 md:grid-cols-4">
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Simulasi</p>
            <p class="mt-2 text-xl font-black {{ $payload['dry_run'] ? 'text-emerald-700' : 'text-rose-700' }}">{{ $payload['dry_run'] ? 'Aktif' : 'Tidak aktif' }}</p>
        </div>
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Permintaan keluar</p>
            <p class="mt-2 text-xl font-black {{ $payload['external_request_sent'] ? 'text-rose-700' : 'text-emerald-700' }}">{{ $payload['external_request_sent'] ? 'Ada' : 'Tidak ada' }}</p>
        </div>
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Penempatan dipindai</p>
            <p class="mt-2 text-xl font-black text-cyan-700">{{ $payload['summary']['assignments_scanned'] ?? 0 }}</p>
        </div>
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Dokumen dipratinjau</p>
            <p class="mt-2 text-xl font-black text-cyan-700">{{ $payload['summary']['documents_previewed'] ?? 0 }}</p>
        </div>
    </section>

    <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <form method="GET" class="grid gap-3 md:grid-cols-[1fr_1fr_140px_auto]">
            <div>
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">ID Penempatan</label>
                <input name="assignment_id" value="{{ $filters['assignment_id'] }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Opsional">
            </div>
            <div>
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Jenis Dokumen</label>
                <select name="document_type" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Semua dokumen</option>
                    @foreach($documentTypes as $type => $serviceCode)
                        <option value="{{ $type }}" @selected(($filters['document_type'] ?? '') === $type)>{{ str_replace('_', ' ', ucfirst($type)) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Batas</label>
                <input type="number" min="1" max="25" name="limit" value="{{ $filters['limit'] }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <button class="self-end rounded-lg bg-cyan-700 px-4 py-2 text-sm font-semibold text-white">Terapkan</button>
        </form>
    </section>

    <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <h3 class="text-lg font-black text-slate-950">Cakupan Dokumen</h3>
        <div class="mt-4 grid gap-2 md:grid-cols-2 xl:grid-cols-4">
            @foreach($documentTypes as $type => $serviceCode)
                <div class="rounded-xl border border-slate-200 p-3 text-sm">
                    <p class="font-bold text-slate-900">{{ str_replace('_', ' ', ucfirst($type)) }}</p>
                    <p class="mt-1 text-xs font-semibold text-cyan-700">{{ $serviceCode }}</p>
                </div>
            @endforeach
        </div>
    </section>

    <section class="space-y-4">
        @forelse($payload['documents'] as $document)
            <article class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <p class="text-xs font-black uppercase tracking-widest text-slate-500">{{ str_replace('_', ' ', $document['document_type']) }}</p>
                        <h3 class="mt-1 text-xl font-black text-slate-950">{{ $document['service_code'] }}</h3>
                        <p class="mt-1 text-sm text-slate-500">{{ $document['source_reference_id'] }} - {{ $document['source_module'] }}</p>
                    </div>
                    <span class="w-fit rounded-full px-3 py-1 text-xs font-bold {{ $document['status'] === 'ready_for_preview' ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-100' : 'bg-amber-50 text-amber-700 ring-1 ring-amber-100' }}">{{ str_replace('_', ' ', ucfirst($document['status'])) }}</span>
                </div>

                <div class="mt-5 grid gap-4 lg:grid-cols-4">
                    <div class="rounded-xl bg-slate-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Mahasiswa</p>
                        <p class="mt-2 font-bold text-slate-900">{{ data_get($document, 'student.name') ?? '-' }}</p>
                        <p class="text-xs text-slate-500">{{ data_get($document, 'student.nim') ?? '-' }} / {{ data_get($document, 'student.study_program') ?? '-' }}</p>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Periode</p>
                        <p class="mt-2 font-bold text-slate-900">{{ data_get($document, 'period.name') ?? '-' }}</p>
                        <p class="text-xs text-slate-500">{{ data_get($document, 'period.academic_year') ?? '-' }} / {{ data_get($document, 'period.semester') ?? '-' }}</p>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Pembimbing</p>
                        <p class="mt-2 font-bold text-slate-900">{{ data_get($document, 'supervisors.internal.name') ?? '-' }}</p>
                        <p class="text-xs text-slate-500">{{ data_get($document, 'supervisors.field.name') ?? '-' }}</p>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Penguji</p>
                        <p class="mt-2 font-bold text-slate-900">{{ data_get($document, 'examiner.name') ?? '-' }}</p>
                        <p class="text-xs text-slate-500">{{ data_get($document, 'exam_schedule.date') ?? 'Jadwal belum tersedia' }}</p>
                    </div>
                </div>

                @if($document['validation_warnings'])
                    <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4">
                        <p class="text-sm font-bold text-amber-800">Peringatan validasi</p>
                        <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-amber-800">
                            @foreach($document['validation_warnings'] as $warning)
                                <li>{{ $warning }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </article>
        @empty
            <section class="rounded-2xl bg-white p-8 text-center text-sm text-slate-500 shadow-sm ring-1 ring-slate-200">Belum ada penempatan KP untuk dipratinjau.</section>
        @endforelse
    </section>
</div>
@endsection
