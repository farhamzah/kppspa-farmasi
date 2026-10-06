@php
    $signedDocuments = $portfolio->signedDocuments()->latest('version_number')->get();
    $currentSignedDocument = app(\App\Services\PkpaPortfolioSignedDocumentService::class)->currentDocument($portfolio);
    $canUploadSigned = ($allowSignedUpload ?? false) && ! $portfolio->locked_at
        && in_array($portfolio->status, ['draft', 'in_progress', 'field_revision_requested', 'internal_revision_requested', 'submitted_to_field_supervisor', 'field_verified', 'submitted_to_internal_supervisor'], true);
@endphp
<section id="portofolio-bertanda-tangan" class="border-y border-slate-200 bg-white px-5 py-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-lg font-bold text-slate-950">Portofolio Bertanda Tangan</h2>
        <span class="text-sm font-semibold {{ $currentSignedDocument ? 'text-emerald-700' : 'text-amber-800' }}">{{ $currentSignedDocument ? 'PDF tersimpan' : ($signedDocuments->isEmpty() ? 'Belum diunggah' : 'Perlu scan terbaru') }}</span>
    </div>
    @if($canUploadSigned)
        <p class="mt-2 text-sm text-slate-600">Scan portofolio final dengan tanda tangan mahasiswa dan Preseptor. PDF maksimal 20 MB.</p>
        <form method="POST" action="{{ route('student.pkpa-portfolios.exports.store', [$portfolio, 'pdf']) }}" class="mt-3">
            @csrf
            <button class="min-h-11 rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold">Unduh PDF untuk Dicetak</button>
        </form>
        <form method="POST" enctype="multipart/form-data" action="{{ route('student.pkpa-portfolios.signed-document.store', $portfolio) }}" class="mt-4 grid gap-3">
            @csrf
            <label class="grid gap-2 text-sm font-semibold">Scan PDF Bertanda Tangan<input type="file" name="signed_file" accept="application/pdf,.pdf" required class="w-full min-w-0 rounded-lg border border-slate-300 p-2 text-sm"></label>
            <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="signatures_confirmed" value="1" required class="mt-1">Saya memastikan PDF lengkap, terbaca, dan sudah ditandatangani mahasiswa serta Preseptor.</label>
            <button class="min-h-11 justify-self-start rounded-lg bg-cyan-700 px-4 py-2 text-sm font-bold text-white">{{ $signedDocuments->isEmpty() ? 'Unggah PDF Bertanda Tangan' : 'Unggah Versi Baru' }}</button>
        </form>
    @endif
    @if($signedDocuments->isNotEmpty())
        <ul class="mt-4 divide-y divide-slate-100">
            @foreach($signedDocuments as $signedDocument)
                <li class="flex flex-wrap items-center gap-3 py-3">
                    <div class="min-w-0 flex-1"><p class="break-all text-sm font-semibold">{{ $signedDocument->download_filename }}</p><p class="mt-1 text-xs text-slate-500">Versi {{ $signedDocument->version_number }} · {{ $signedDocument->created_at?->format('d M Y H:i') }} · {{ number_format($signedDocument->file_size / 1024, 0) }} KB{{ $currentSignedDocument?->id === $signedDocument->id ? ' · Dokumen terbaru' : ' · Riwayat unggahan' }}</p></div>
                    <a href="{{ route('pkpa-signed-documents.show', $signedDocument) }}" target="_blank" rel="noopener" class="min-h-11 rounded-lg border border-cyan-200 px-3 py-2 text-sm font-semibold text-cyan-800">Pratinjau</a>
                    <a href="{{ route('pkpa-signed-documents.show', ['document' => $signedDocument, 'download' => 1]) }}" class="min-h-11 rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold">Unduh</a>
                </li>
            @endforeach
        </ul>
        @unless($allowSignedUpload ?? false)<p class="mt-2 text-sm text-slate-600">Tanda tangan dan keterbacaan scan perlu diperiksa pada PDF sebelum persetujuan final.</p>@endunless
    @endif
</section>
