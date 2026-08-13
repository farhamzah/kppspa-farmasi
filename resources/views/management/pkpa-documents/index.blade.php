@extends('layouts.app')

@section('title', 'Dokumen PKPA')

@section('content')
<div class="space-y-6">
    <div class="rounded-3xl border border-slate-100 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-sm font-bold uppercase tracking-wide text-cyan-700">Dokumen Internal MY PSPA</p>
                <h1 class="mt-2 text-3xl font-black text-slate-950">Template, penomoran, penerbitan, dan distribusi dokumen PKPA</h1>
                <p class="mt-2 max-w-3xl text-sm text-slate-600">Semua keluaran disimpan privat, berbasis snapshot, dan tidak menggantikan dokumen resmi universitas.</p>
            </div>
            <a href="{{ route('management.pkpa-documents.export') }}" class="rounded-2xl bg-cyan-700 px-4 py-3 text-sm font-bold text-white">Ekspor Dokumen</a>
        </div>
    </div>

    @if (session('status'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-800">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-bold text-rose-800">{{ $errors->first() }}</div>
    @endif

    <div class="grid gap-6 xl:grid-cols-3">
        <section class="rounded-3xl border border-slate-100 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-black text-slate-950">Jenis Dokumen</h2>
            <form method="POST" action="{{ route('management.pkpa-document-types.store') }}" class="mt-4 grid gap-3">
                @csrf
                <input name="code" placeholder="kode_jenis_dokumen" class="rounded-2xl border-slate-200 text-sm" required>
                <input name="name" placeholder="Nama jenis dokumen" class="rounded-2xl border-slate-200 text-sm" required>
                <select name="scope_type" class="rounded-2xl border-slate-200 text-sm">
                    @foreach(['program','student','rotation','site','supervisor','grade','graduation','custom'] as $scope)
                        <option value="{{ $scope }}">{{ str($scope)->headline() }}</option>
                    @endforeach
                </select>
                <div class="grid grid-cols-2 gap-2 text-sm text-slate-700">
                    @foreach(['docx','pdf','xlsx','csv'] as $format)
                        <label class="flex items-center gap-2 rounded-xl bg-slate-50 px-3 py-2"><input type="checkbox" name="output_formats[]" value="{{ $format }}" @checked($format === 'docx')> {{ strtoupper($format) }}</label>
                    @endforeach
                </div>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="requires_number" value="1"> Perlu nomor</label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="requires_signatory" value="1"> Perlu penandatangan</label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="requires_approval" value="1" checked> Perlu persetujuan</label>
                <button class="rounded-2xl bg-slate-900 px-4 py-3 text-sm font-bold text-white">Tambah Jenis</button>
            </form>
        </section>

        <section class="rounded-3xl border border-slate-100 bg-white p-5 shadow-sm xl:col-span-2">
            <h2 class="text-lg font-black text-slate-950">Jenis Sistem</h2>
            <div class="mt-4 grid gap-3 md:grid-cols-2">
                @foreach($types as $type)
                    <article class="rounded-2xl border border-slate-100 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-black text-slate-900">{{ $type->name }}</p>
                                <p class="text-xs text-slate-500">{{ $type->code }} - {{ implode(', ', $type->output_formats ?? []) }}</p>
                            </div>
                            <span class="rounded-full bg-cyan-50 px-3 py-1 text-xs font-bold text-cyan-700">{{ $type->scope_type }}</span>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
        <section class="rounded-3xl border border-slate-100 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-black text-slate-950">Template Dokumen</h2>
            @foreach($types as $type)
                <details class="mt-3 rounded-2xl border border-slate-100 p-4">
                    <summary class="cursor-pointer font-bold text-slate-900">{{ $type->name }}</summary>
                    <form method="POST" action="{{ route('management.pkpa-document-types.templates.store', $type) }}" class="mt-3 grid gap-3">
                        @csrf
                        <select name="pkpa_program_id" class="rounded-2xl border-slate-200 text-sm"><option value="">Global</option>@foreach($programs as $program)<option value="{{ $program->id }}">{{ $program->name }}</option>@endforeach</select>
                        <input name="code" placeholder="Kode template" class="rounded-2xl border-slate-200 text-sm" required>
                        <input name="name" placeholder="Nama template" class="rounded-2xl border-slate-200 text-sm" required>
                        <select name="template_engine" class="rounded-2xl border-slate-200 text-sm"><option value="html">HTML aman</option><option value="docx_template">DOCX template</option><option value="spreadsheet">Spreadsheet</option><option value="csv">CSV</option></select>
                        <textarea name="template_content" rows="6" class="rounded-2xl border-slate-200 text-sm" placeholder="Contoh: Dokumen Internal MY PSPA&#10;Program: @{{ program.name }}&#10;Mahasiswa: @{{ student.name }}"></textarea>
                        <button class="rounded-2xl bg-slate-900 px-4 py-3 text-sm font-bold text-white">Simpan Draf Template</button>
                    </form>
                </details>
            @endforeach
            <div class="mt-4 space-y-2">
                @foreach($templates as $template)
                    <div class="flex flex-col gap-2 rounded-2xl bg-slate-50 p-3 text-sm md:flex-row md:items-center md:justify-between">
                        <div><b>{{ $template->name }}</b><span class="text-slate-500"> - {{ $template->type?->name }} v{{ $template->version_number }} - {{ $template->status }}</span></div>
                        @if($template->status === 'draft')
                            <form method="POST" action="{{ route('management.pkpa-document-templates.activate', $template) }}">@csrf<button class="rounded-xl bg-cyan-700 px-3 py-2 text-xs font-bold text-white">Aktifkan</button></form>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>

        <section class="rounded-3xl border border-slate-100 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-black text-slate-950">Nomor dan Penandatangan</h2>
            @foreach($types->where('requires_number', true) as $type)
                <details class="mt-3 rounded-2xl border border-slate-100 p-4">
                    <summary class="cursor-pointer font-bold text-slate-900">Nomor: {{ $type->name }}</summary>
                    <form method="POST" action="{{ route('management.pkpa-document-types.numbering.store', $type) }}" class="mt-3 grid gap-3">
                        @csrf
                        <select name="pkpa_program_id" class="rounded-2xl border-slate-200 text-sm"><option value="">Global</option>@foreach($programs as $program)<option value="{{ $program->id }}">{{ $program->name }}</option>@endforeach</select>
                        <input name="code" placeholder="Kode aturan" class="rounded-2xl border-slate-200 text-sm" required>
                        <input name="name" placeholder="Nama aturan" class="rounded-2xl border-slate-200 text-sm" required>
                        <input name="pattern" placeholder="{sequence}/{type}/{month}/{year}" class="rounded-2xl border-slate-200 text-sm" required>
                        <select name="sequence_scope" class="rounded-2xl border-slate-200 text-sm"><option value="document_type">Per jenis dokumen</option><option value="program">Per program</option><option value="global">Global</option><option value="yearly">Tahunan</option><option value="custom">Custom</option></select>
                        <select name="reset_policy" class="rounded-2xl border-slate-200 text-sm"><option value="never">Tidak reset</option><option value="yearly">Tahunan</option><option value="monthly">Bulanan</option><option value="per_program">Per program</option></select>
                        <button class="rounded-2xl bg-slate-900 px-4 py-3 text-sm font-bold text-white">Simpan Nomor</button>
                    </form>
                </details>
            @endforeach
            @foreach($types->where('requires_signatory', true) as $type)
                <details class="mt-3 rounded-2xl border border-slate-100 p-4">
                    <summary class="cursor-pointer font-bold text-slate-900">Penandatangan: {{ $type->name }}</summary>
                    <form method="POST" action="{{ route('management.pkpa-document-types.signatories.store', $type) }}" class="mt-3 grid gap-3">
                        @csrf
                        <input name="signatory_role" placeholder="Peran penandatangan" class="rounded-2xl border-slate-200 text-sm" required>
                        <input name="name_snapshot" placeholder="Nama snapshot" class="rounded-2xl border-slate-200 text-sm" required>
                        <input name="title_snapshot" placeholder="Jabatan snapshot" class="rounded-2xl border-slate-200 text-sm">
                        <select name="signature_mode" class="rounded-2xl border-slate-200 text-sm"><option value="name_only">Nama saja</option><option value="wet_signature">Tanda tangan basah</option><option value="manual_external">Manual eksternal</option><option value="digital_placeholder">Placeholder digital</option></select>
                        <div class="grid gap-3 sm:grid-cols-2"><input type="date" name="effective_start_date" class="rounded-2xl border-slate-200 text-sm" required><input type="date" name="effective_end_date" class="rounded-2xl border-slate-200 text-sm" required></div>
                        <button class="rounded-2xl bg-slate-900 px-4 py-3 text-sm font-bold text-white">Simpan Penandatangan</button>
                    </form>
                </details>
            @endforeach
        </section>
    </div>

    <section class="rounded-3xl border border-slate-100 bg-white p-5 shadow-sm">
        <h2 class="text-lg font-black text-slate-950">Buat Dokumen</h2>
        <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach($types as $type)
                <form method="POST" action="{{ route('management.pkpa-document-types.generate', $type) }}" class="rounded-2xl border border-slate-100 p-4">
                    @csrf
                    <p class="font-black text-slate-900">{{ $type->name }}</p>
                    <input name="title" value="{{ $type->name }}" class="mt-3 w-full rounded-2xl border-slate-200 text-sm" required>
                    <select name="pkpa_program_id" class="mt-3 w-full rounded-2xl border-slate-200 text-sm"><option value="">Tanpa program</option>@foreach($programs as $program)<option value="{{ $program->id }}">{{ $program->name }}</option>@endforeach</select>
                    <select name="scope_type" class="mt-3 w-full rounded-2xl border-slate-200 text-sm"><option value="custom">Custom</option><option value="publication">Publication</option><option value="assignment">Assignment</option><option value="final_release">Hasil akhir</option></select>
                    <input name="scope_id" placeholder="ID scope" class="mt-3 w-full rounded-2xl border-slate-200 text-sm">
                    <div class="mt-3 grid grid-cols-2 gap-2 text-sm">@foreach($type->output_formats ?? ['docx'] as $format)<label class="flex items-center gap-2 rounded-xl bg-slate-50 px-3 py-2"><input type="checkbox" name="formats[]" value="{{ $format }}" checked> {{ strtoupper($format) }}</label>@endforeach</div>
                    <button class="mt-3 w-full rounded-2xl bg-cyan-700 px-4 py-3 text-sm font-bold text-white">Buat Draf</button>
                </form>
            @endforeach
        </div>
    </section>

    <section class="rounded-3xl border border-slate-100 bg-white p-5 shadow-sm">
        <h2 class="text-lg font-black text-slate-950">Dokumen Terbaru</h2>
        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="text-xs uppercase text-slate-500"><tr><th class="p-3">Judul</th><th class="p-3">Jenis</th><th class="p-3">Nomor</th><th class="p-3">Status</th><th class="p-3">Berkas</th><th class="p-3">Aksi</th></tr></thead>
                <tbody>
                    @foreach($documents as $document)
                        <tr class="border-t border-slate-100">
                            <td class="p-3 font-bold text-slate-900">{{ $document->title }}</td>
                            <td class="p-3">{{ $document->type?->name }}</td>
                            <td class="p-3">{{ $document->document_number ?: '-' }}</td>
                            <td class="p-3">{{ $document->status }}</td>
                            <td class="p-3">@foreach($document->versions as $version)<a class="mr-2 font-bold text-cyan-700" href="{{ route('management.pkpa-document-versions.download', $version) }}">{{ strtoupper($version->output_format) }}</a>@endforeach</td>
                            <td class="p-3">
                                <div class="flex flex-wrap gap-2">
                                    @if(in_array($document->status, ['generated','under_review'], true))<form method="POST" action="{{ route('management.pkpa-documents.approve', $document) }}">@csrf<button class="rounded-xl bg-slate-900 px-3 py-2 text-xs font-bold text-white">Setujui</button></form>@endif
                                    @if(in_array($document->status, ['approved','generated'], true))<form method="POST" action="{{ route('management.pkpa-documents.publish', $document) }}">@csrf<button class="rounded-xl bg-cyan-700 px-3 py-2 text-xs font-bold text-white">Terbitkan</button></form>@endif
                                    @if(!in_array($document->status, ['cancelled','published'], true))<form method="POST" action="{{ route('management.pkpa-documents.cancel', $document) }}">@csrf<input type="hidden" name="cancellation_reason" value="Dibatalkan dari dashboard dokumen"><button class="rounded-xl bg-rose-600 px-3 py-2 text-xs font-bold text-white">Batal</button></form>@endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $documents->links() }}</div>
    </section>
</div>
@endsection
