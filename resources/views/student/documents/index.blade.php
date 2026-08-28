@extends('layouts.app')

@section('title', 'Berkas PKPA - '.config('app.name'))
@section('page_title', 'Berkas PKPA')

@section('content')
<div class="space-y-6">
    <section class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-100">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <p class="text-xs font-black uppercase tracking-widest text-cyan-700">Dokumen Persyaratan</p>
                <h2 class="mt-2 text-2xl font-black text-slate-950">Berkas Administrasi PKPA</h2>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">Unggah dan pantau status verifikasi dokumen persyaratan PKPA Anda pada periode pendaftaran aktif.</p>
            </div>
            @if($registration)
                <span class="inline-flex rounded-full {{ $registration->statusBadgeClass() }} px-4 py-2 text-xs font-bold">{{ $registration->statusLabel() }}</span>
            @endif
        </div>
    </section>

    @if(! $registration)
        <section class="rounded-3xl bg-white p-8 text-center shadow-sm ring-1 ring-slate-100">
            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-3xl bg-cyan-50 text-cyan-700">
                <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6M7 4h7l5 5v11a2 2 0 01-2 2H7a2 2 0 01-2-2V6a2 2 0 012-2z"/></svg>
            </div>
            <h3 class="mt-5 text-lg font-black text-slate-950">Anda belum memiliki pendaftaran PKPA</h3>
            <p class="mx-auto mt-2 max-w-xl text-sm leading-6 text-slate-500">Silakan daftar PKPA terlebih dahulu sebelum mengunggah dokumen persyaratan.</p>
            @if($openPeriods->isEmpty())
                <p class="mt-4 rounded-2xl bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-600">Belum ada periode PKPA yang membuka pendaftaran.</p>
            @else
                <a href="{{ route('student.pkpa-registrations.create') }}" class="mt-5 inline-flex rounded-2xl bg-cyan-700 px-5 py-3 text-sm font-bold text-white shadow-lg shadow-cyan-700/20">Daftar PKPA</a>
            @endif
        </section>
    @else
        @if($registration->isVerified())
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-semibold text-emerald-800">
                Berkas Anda sudah terverifikasi. Selanjutnya Anda menunggu penetapan penempatan PKPA oleh koordinator/admin.
            </div>
        @elseif($registration->status === 'revisi')
            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm font-semibold text-amber-800">
                Ada revisi pada pendaftaran atau dokumen Anda. Perhatikan catatan pada dokumen terkait lalu unggah ulang.
            </div>
        @elseif($registration->canBeSubmitted())
            <section class="rounded-3xl border border-cyan-200 bg-cyan-50 p-5 shadow-sm">
                <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <p class="text-xs font-black uppercase tracking-widest text-cyan-700">Siap Dikirim</p>
                        <h3 class="mt-1 text-lg font-black text-slate-950">Berkas sudah lengkap, kirim pendaftaran Anda.</h3>
                        <p class="mt-1 text-sm leading-6 text-slate-600">Status akan berubah dari Draf menjadi Menunggu Verifikasi setelah dikirim.</p>
                    </div>
                    <form method="POST" action="{{ route('student.pkpa-registrations.submit', $registration) }}" onsubmit="return confirm('Kirim pendaftaran untuk diverifikasi?')">
                        @csrf
                        <button class="inline-flex w-full justify-center rounded-2xl bg-cyan-700 px-5 py-3 text-sm font-black text-white shadow-lg shadow-cyan-700/20 hover:bg-cyan-800 md:w-auto">Kirim Pendaftaran</button>
                    </form>
                </div>
            </section>
        @elseif($registration->isWaitingVerification())
            <div class="rounded-2xl border border-sky-200 bg-sky-50 px-5 py-4 text-sm font-semibold text-sky-800">
                Pendaftaran sudah dikirim dan sedang menunggu verifikasi admin/koordinator.
            </div>
        @endif

        <section class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-100">
            <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                <div>
                    <h3 class="text-lg font-black text-slate-950">{{ $registration->period->name }}</h3>
                    <p class="text-sm text-slate-500">Kemajuan berkas {{ $registration->progressPercentage() }}%</p>
                </div>
                <a href="{{ route('student.pkpa-registrations.show', $registration) }}" class="inline-flex rounded-2xl border border-cyan-200 px-4 py-2 text-sm font-bold text-cyan-700 hover:bg-cyan-50">Detail Pendaftaran</a>
            </div>
            <div class="mt-5 h-3 overflow-hidden rounded-full bg-slate-100">
                <div class="h-full rounded-full bg-cyan-700" style="width: {{ $registration->progressPercentage() }}%"></div>
            </div>
        </section>

        <section class="grid gap-4">
            @forelse($registration->period->documentRequirements as $requirement)
                @php
                    $document = $registration->documents->firstWhere('kp_document_requirement_id', $requirement->id);
                    $canUpload = ! $registration->isVerified() && (in_array($registration->status, ['draft', 'revisi'], true) || in_array($document?->status, [null, 'belum_upload', 'revisi', 'ditolak'], true));
                @endphp
                <article class="rounded-3xl bg-white p-5 shadow-sm ring-1 ring-slate-100">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h4 class="text-base font-black text-slate-950">{{ $requirement->name }}</h4>
                                <span class="rounded-full {{ $requirement->is_required ? 'bg-rose-50 text-rose-700' : 'bg-slate-100 text-slate-600' }} px-3 py-1 text-[11px] font-black uppercase tracking-widest">{{ $requirement->is_required ? 'Wajib' : 'Opsional' }}</span>
                                <span class="rounded-full {{ $document?->statusBadgeClass() ?? 'bg-slate-100 text-slate-700' }} px-3 py-1 text-xs font-bold">{{ $document?->statusLabel() ?? 'Belum Unggah' }}</span>
                            </div>
                            <p class="mt-2 text-sm leading-6 text-slate-500">{{ $requirement->description ?: 'Format '.$requirement->allowed_file_types.', maksimal '.$requirement->max_file_size_mb.'MB.' }}</p>
                            @if($document?->file_path)
                                <div class="mt-3 rounded-2xl bg-slate-50 px-4 py-3 text-sm text-slate-600">
                                    <p class="font-bold text-slate-800">{{ $document->original_filename }}</p>
                                    <p class="mt-1 text-xs">Unggah: {{ $document->uploaded_at?->format('d M Y H:i') ?? '-' }} · {{ $document->humanFileSize() }}</p>
                                </div>
                            @endif
                            @if($document?->review_note)
                                <p class="mt-3 rounded-2xl bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-800">{{ $document->review_note }}</p>
                            @endif
                        </div>
                        <div class="flex flex-col gap-2 lg:w-80">
                            @if($document?->file_path)
                                <a href="{{ route('student.pkpa-registrations.documents.download', [$registration, $document]) }}" class="rounded-2xl border border-slate-200 px-4 py-2 text-center text-sm font-bold text-slate-700 hover:bg-slate-50">Unduh</a>
                            @endif
                            @if($canUpload)
                                <form method="POST" action="{{ route('student.pkpa-registrations.documents.store', [$registration, $requirement]) }}" enctype="multipart/form-data" class="rounded-2xl border border-dashed border-cyan-200 bg-cyan-50/40 p-3">
                                    @csrf
                                    <input type="file" name="document" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                                    @error('document')<p class="mt-2 text-xs font-semibold text-rose-600">{{ $message }}</p>@enderror
                                    <button class="mt-3 w-full rounded-xl bg-cyan-700 px-4 py-2 text-sm font-bold text-white">{{ $document?->file_path ? 'Unggah Ulang' : 'Unggah Dokumen' }}</button>
                                </form>
                            @endif
                        </div>
                    </div>
                </article>
            @empty
                <div class="rounded-3xl bg-white p-8 text-center shadow-sm ring-1 ring-slate-100">
                    <p class="font-black text-slate-950">Belum ada persyaratan dokumen</p>
                    <p class="mt-2 text-sm text-slate-500">Persyaratan dokumen untuk periode ini belum diatur oleh Koordinator/Admin.</p>
                </div>
            @endforelse
        </section>
    @endif
</div>
@endsection
