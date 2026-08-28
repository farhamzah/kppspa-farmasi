@extends('layouts.app')
@section('title','Referensi Draf TU - '.config('app.name'))
@section('page_title','Referensi Draf TU')
@section('content')
<div class="space-y-5">
    <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="text-xs font-black uppercase tracking-widest text-cyan-700">Local draft only</p>
                <h2 class="mt-2 text-2xl font-black text-slate-950">Referensi Dokumen Eksternal TU</h2>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500">Halaman ini menyimpan draf referensi dokumen di database lokal PKPA saja. Tidak ada sinkronisasi, unggah ulang, atau request ke TU.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('management.integration.tu-payload-preview') }}" class="rounded-lg border border-cyan-200 px-4 py-2 text-sm font-semibold text-cyan-700">Periksa TU</a>
                <a href="{{ route('management.integration.safa-public-info-preview') }}" class="rounded-lg border border-cyan-200 px-4 py-2 text-sm font-semibold text-cyan-700">Periksa SAFA</a>
            </div>
        </div>
    </section>

    <section class="grid gap-4 md:grid-cols-4">
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Sumber pratinjau</p>
            <p class="mt-2 text-xl font-black text-cyan-700">TU Payload</p>
        </div>
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Documents scanned</p>
            <p class="mt-2 text-xl font-black text-cyan-700">{{ data_get($preview, 'summary.documents_scanned', 0) }}</p>
        </div>
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Draf dipratinjau</p>
            <p class="mt-2 text-xl font-black text-cyan-700">{{ data_get($preview, 'summary.references_previewed', 0) }}</p>
        </div>
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Auto-sync</p>
            <p class="mt-2 text-xl font-black text-emerald-700">Tidak aktif</p>
        </div>
    </section>

    <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <form method="GET" class="grid gap-3 md:grid-cols-[1fr_1fr_140px_auto]">
            <div>
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Assignment ID</label>
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
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Limit</label>
                <input type="number" min="1" max="25" name="limit" value="{{ $filters['limit'] }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <button class="self-end rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Pratinjau Draf dari Payload TU</button>
        </form>

        <form method="POST" action="{{ route('management.integration.external-document-references.store-drafts') }}" class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4" onsubmit="return confirm('Buat atau perbarui draf referensi lokal PKPA dari preview payload TU? Tidak ada request ke TU.')">
            @csrf
            <input type="hidden" name="assignment_id" value="{{ $filters['assignment_id'] }}">
            <input type="hidden" name="document_type" value="{{ $filters['document_type'] }}">
            <input type="hidden" name="limit" value="{{ $filters['limit'] }}">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <p class="text-sm font-semibold text-amber-900">Aksi ini hanya membuat atau memperbarui draf referensi di database lokal PKPA. Tidak ada upload, sync, atau HTTP request ke TU.</p>
                <button class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white">Buat Draf Referensi Lokal</button>
            </div>
        </form>
    </section>

    <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <h3 class="text-lg font-black text-slate-950">Pratinjau Draf dari Payload TU</h3>
        <div class="mt-4 overflow-hidden rounded-xl border border-slate-200">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Document</th>
                        <th class="px-4 py-3">Source</th>
                        <th class="px-4 py-3">Status Payload</th>
                        <th class="px-4 py-3">Warning</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                    @forelse($preview['references'] as $draft)
                        <tr>
                            <td class="px-4 py-4">
                                <div class="font-semibold text-slate-950">{{ str_replace('_', ' ', $draft['document_type']) }}</div>
                                <div class="text-xs text-cyan-700">{{ $draft['service_code'] }}</div>
                            </td>
                            <td class="px-4 py-4">{{ $draft['source_reference_type'] }}:{{ $draft['source_reference_id'] }}</td>
                            <td class="px-4 py-4">{{ data_get($draft, 'metadata.payload_status') ?: '-' }}</td>
                            <td class="px-4 py-4 text-xs text-slate-500">{{ implode('; ', data_get($draft, 'metadata.validation_warnings', [])) ?: '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-10 text-center text-slate-500">Belum ada payload TU untuk dipratinjau.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <h3 class="text-lg font-black text-slate-950">Reference Lokal Tersimpan</h3>
        <div class="mt-4 overflow-hidden rounded-xl border border-slate-200">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Document</th>
                        <th class="px-4 py-3">Source</th>
                        <th class="px-4 py-3">External Status</th>
                        <th class="px-4 py-3">External Number</th>
                        <th class="px-4 py-3">External ID</th>
                        <th class="px-4 py-3">Reference URL</th>
                        <th class="px-4 py-3">Tersinkron</th>
                        <th class="px-4 py-3">Created</th>
                        <th class="px-4 py-3">Aksi</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                    @forelse($references as $reference)
                        <tr>
                            <td class="px-4 py-4">
                                <div class="font-semibold text-slate-950">{{ str_replace('_', ' ', $reference->document_type) }}</div>
                                <div class="text-xs text-cyan-700">{{ $reference->service_code }}</div>
                            </td>
                            <td class="px-4 py-4">{{ $reference->source_reference_type }}:{{ $reference->source_reference_id }}</td>
                            <td class="px-4 py-4"><span class="rounded-full px-2 py-1 text-xs font-semibold ring-1 {{ $reference->statusBadgeClass() }}">{{ $reference->statusLabel() }}</span></td>
                            <td class="px-4 py-4">{{ $reference->external_document_number ?: '-' }}</td>
                            <td class="px-4 py-4">{{ $reference->external_document_id ?: '-' }}</td>
                            <td class="px-4 py-4">
                                @if($reference->reference_url && $reference->isSafeReferenceUrl())
                                    <span class="text-xs text-cyan-700">{{ $reference->reference_url }}</span>
                                @else
                                    <span class="text-xs text-slate-400">Belum ada</span>
                                @endif
                            </td>
                            <td class="px-4 py-4">{{ $reference->synced_at?->format('d M Y H:i') ?? '-' }}</td>
                            <td class="px-4 py-4">{{ $reference->created_at?->format('d M Y H:i') ?? '-' }}</td>
                            <td class="px-4 py-4">
                                <a href="{{ route('management.integration.external-document-references.edit', $reference) }}" class="rounded-lg border border-cyan-200 px-3 py-2 text-xs font-semibold text-cyan-700">Edit Link</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-4 py-10 text-center text-slate-500">Belum ada draf referensi lokal.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-4">{{ $references->links() }}</div>
    </section>
</div>
@endsection
