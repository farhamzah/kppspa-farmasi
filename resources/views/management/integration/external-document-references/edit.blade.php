@extends('layouts.app')
@section('title','Edit Reference TU - '.config('app.name'))
@section('page_title','Edit Reference TU')
@section('content')
<div class="space-y-5">
    <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="text-xs font-black uppercase tracking-widest text-cyan-700">Manual local linking</p>
                <h2 class="mt-2 text-2xl font-black text-slate-950">Edit Referensi Dokumen Eksternal TU</h2>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500">Perubahan hanya disimpan pada tabel lokal KP. Tidak ada request HTTP, upload file, atau write ke TU/Core/SAFA.</p>
            </div>
            <a href="{{ route('management.integration.external-document-references.index') }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700">Kembali</a>
        </div>
    </section>

    @if(session('status'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{{ session('status') }}</div>
    @endif

    <section class="grid gap-5 lg:grid-cols-[1.1fr_0.9fr]">
        <form method="POST" action="{{ route('management.integration.external-document-references.update', $reference) }}" class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            @csrf
            @method('PATCH')

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">External Document ID</label>
                    <input name="external_document_id" value="{{ old('external_document_id', $reference->external_document_id) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="ID dokumen dari TU">
                    @error('external_document_id')<p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Nomor Dokumen TU</label>
                    <input name="external_document_number" value="{{ old('external_document_number', $reference->external_document_number) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Contoh: 120/TU/KP/2026">
                    @error('external_document_number')<p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status</label>
                    <select name="external_status" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        @foreach($statuses as $status)
                            <option value="{{ $status }}" @selected(old('external_status', $reference->external_status) === $status)>{{ str_replace('_', ' ', ucfirst($status)) }}</option>
                        @endforeach
                    </select>
                    @error('external_status')<p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Disinkronkan Pada</label>
                    <input type="datetime-local" name="synced_at" value="{{ old('synced_at', $reference->synced_at?->format('Y-m-d\TH:i')) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    @error('synced_at')<p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="mt-4">
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Reference URL Aman</label>
                <input name="reference_url" value="{{ old('reference_url', $reference->reference_url) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="https://tu.example.local/arsip/dokumen/123">
                @error('reference_url')<p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p>@enderror
                <p class="mt-2 text-xs leading-5 text-slate-500">URL dengan token, signed URL, credential, path storage/private, atau path file lokal akan ditolak.</p>
            </div>

            <div class="mt-4">
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Catatan/Error Manual</label>
                <textarea name="last_error" rows="5" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Opsional untuk status failed atau catatan proses TU">{{ old('last_error', $reference->last_error) }}</textarea>
                @error('last_error')<p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p>@enderror
            </div>

            <div class="mt-5 flex flex-col gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 md:flex-row md:items-center md:justify-between">
                <p class="text-sm font-semibold text-amber-900">Simpan hanya metadata/link lokal. Jangan masukkan signed URL, token, password, secret, atau path file internal.</p>
                <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Simpan Reference</button>
            </div>
        </form>

        <aside class="space-y-5">
            <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <h3 class="text-lg font-black text-slate-950">Snapshot Ringkas</h3>
                <dl class="mt-4 space-y-3 text-sm">
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Document Type</dt><dd class="mt-1 font-semibold text-slate-900">{{ $reference->document_type }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Service Code</dt><dd class="mt-1 font-semibold text-slate-900">{{ $reference->service_code }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Source Module</dt><dd class="mt-1 font-semibold text-slate-900">{{ $reference->source_module }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Source Reference</dt><dd class="mt-1 font-semibold text-slate-900">{{ $reference->source_reference_type }}:{{ $reference->source_reference_id }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Created</dt><dd class="mt-1 font-semibold text-slate-900">{{ $reference->created_at?->format('d M Y H:i') ?? '-' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Updated</dt><dd class="mt-1 font-semibold text-slate-900">{{ $reference->updated_at?->format('d M Y H:i') ?? '-' }}</dd></div>
                </dl>
            </section>

            <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <h3 class="text-lg font-black text-slate-950">Guardrails</h3>
                <ul class="mt-4 space-y-2 text-sm leading-6 text-slate-600">
                    <li>Write hanya ke `kp_external_document_references`.</li>
                    <li>Tidak ada HTTP request ke TU/SAFA.</li>
                    <li>Tidak ada upload atau duplicate file.</li>
                    <li>Tidak menyimpan token, secret, signed URL, atau path internal.</li>
                </ul>
            </section>
        </aside>
    </section>
</div>
@endsection
