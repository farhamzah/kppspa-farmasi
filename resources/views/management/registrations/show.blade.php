@extends('layouts.app')
@section('title', 'Detail Verifikasi Pendaftaran KP - '.config('app.name'))
@section('page_title', 'Detail Verifikasi Pendaftaran KP')
@section('content')
@php($studentDisplay = app(\App\Services\KpMasterDataReadService::class)->getStudentDisplayData($registration->student))
<div class="space-y-5">
    @if(session('status'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-3">
        <a href="{{ $backUrl }}" class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:border-cyan-300 hover:text-cyan-700">
            Kembali ke Antrian
        </a>
        <p class="text-sm text-slate-500">Periksa pendaftaran dan dokumen mahasiswa dari satu halaman.</p>
    </div>

    <div class="grid gap-5 xl:grid-cols-[0.85fr_1.15fr]">
        <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <span class="rounded-full {{ $registration->statusBadgeClass() }} px-3 py-1 text-xs font-semibold">{{ $registration->statusLabel() }}</span>
            <h2 class="mt-4 text-2xl font-bold text-slate-950">{{ $studentDisplay->name }}</h2>
            <p class="mt-1 text-sm text-slate-500">{{ $studentDisplay->studentNumber ?: '-' }} - {{ $studentDisplay->email }}</p>

            <dl class="mt-6 grid gap-3 text-sm">
                <div class="rounded-lg bg-slate-50 p-3">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Nomor Pendaftaran</dt>
                    <dd class="mt-1 font-semibold text-slate-900">{{ $registration->registration_number ?: 'Belum dibuat' }}</dd>
                </div>
                <div class="rounded-lg bg-slate-50 p-3">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Periode</dt>
                    <dd class="mt-1 font-semibold text-slate-900">{{ $registration->period->name }}</dd>
                </div>
                <div class="rounded-lg bg-slate-50 p-3">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Tanggal Dikirim</dt>
                    <dd class="mt-1 font-semibold text-slate-900">{{ $registration->submitted_at?->format('d M Y H:i') ?? '-' }}</dd>
                </div>
            </dl>

            <div class="mt-6">
                <div class="h-3 overflow-hidden rounded-full bg-slate-100">
                    <div class="h-full rounded-full bg-teal-600" style="width: {{ $registration->progressPercentage() }}%"></div>
                </div>
                <p class="mt-2 text-sm text-slate-500">Kemajuan unggah berkas {{ $registration->progressPercentage() }}%.</p>
            </div>

            <div class="mt-6 space-y-3">
                @if($registration->isWaitingVerification())
                    <form method="POST" action="{{ route('management.kp-registrations.verify', $registration) }}" onsubmit="return confirm('Verifikasi pendaftaran ini?')">
                        @csrf
                        <textarea name="verification_note" rows="2" placeholder="Catatan verifikasi opsional" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('verification_note') }}</textarea>
                        <button class="mt-2 w-full rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white">Verifikasi Pendaftaran</button>
                    </form>
                @elseif($registration->isDraft())
                    <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-800">
                        Pendaftaran masih draf. Mahasiswa perlu menekan Kirim Pendaftaran sebelum admin/koordinator memverifikasi.
                    </div>
                @endif
                <form method="POST" action="{{ route('management.kp-registrations.revision', $registration) }}" onsubmit="return confirm('Minta revisi pendaftaran ini?')">
                    @csrf
                    <textarea name="verification_note" rows="2" placeholder="Catatan revisi pendaftaran" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('verification_note') }}</textarea>
                    <button class="mt-2 w-full rounded-lg border border-amber-200 px-4 py-2 text-sm font-semibold text-amber-700">Minta Revisi</button>
                </form>
                <form method="POST" action="{{ route('management.kp-registrations.reject', $registration) }}" onsubmit="return confirm('Tolak pendaftaran ini?')">
                    @csrf
                    <textarea name="verification_note" rows="2" placeholder="Alasan penolakan pendaftaran" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('verification_note') }}</textarea>
                    <button class="mt-2 w-full rounded-lg border border-rose-200 px-4 py-2 text-sm font-semibold text-rose-700">Tolak Pendaftaran</button>
                </form>
            </div>
        </section>

        <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <h3 class="text-lg font-bold text-slate-950">Dokumen Persyaratan</h3>
            <div class="mt-4 space-y-3">
                @foreach($registration->period->documentRequirements->where('status', 'aktif')->sortBy('sort_order') as $requirement)
                    @php($document = $registration->documents->firstWhere('kp_document_requirement_id', $requirement->id))
                    <div class="rounded-xl border border-slate-200 p-4">
                        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                            <div>
                                <h4 class="font-bold text-slate-950">{{ $requirement->name }} @if($requirement->is_required)<span class="text-rose-600">*</span>@endif</h4>
                                <p class="text-sm text-slate-500">{{ $document?->original_filename ?: 'Belum ada file.' }}</p>
                                @if($document?->file_size)
                                    <p class="text-xs text-slate-400">{{ $document->humanFileSize() }} · {{ $document->file_mime }}</p>
                                @endif
                                @if($document?->review_note)
                                    <p class="mt-2 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800">{{ $document->review_note }}</p>
                                @endif
                            </div>
                            <span class="rounded-full {{ $document?->statusBadgeClass() ?? 'bg-slate-100 text-slate-700' }} px-3 py-1 text-xs font-semibold">{{ $document?->statusLabel() ?? 'Belum Unggah' }}</span>
                        </div>
                        @if($document?->file_path)
                            <div class="mt-3 flex flex-wrap gap-2">
                                <a href="{{ route('management.kp-registrations.documents.preview', [$registration, $document]) }}" target="_blank" rel="noopener" class="rounded-lg border border-cyan-200 px-3 py-1.5 text-xs font-semibold text-cyan-700">Pratinjau</a>
                                <a href="{{ route('management.kp-registrations.documents.download', [$registration, $document]) }}" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700">Unduh</a>
                                @if($document->status === 'disetujui')
                                    <span class="rounded-lg border border-emerald-100 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700">Sudah disetujui</span>
                                @else
                                    <form method="POST" action="{{ route('management.kp-registrations.documents.approve', [$registration, $document]) }}" onsubmit="return confirm('Setujui dokumen ini?')">
                                        @csrf
                                        <button class="rounded-lg border border-emerald-200 px-3 py-1.5 text-xs font-semibold text-emerald-700">Setujui</button>
                                    </form>
                                @endif
                            </div>
                            <div class="mt-3 grid gap-2 md:grid-cols-2">
                                <form method="POST" action="{{ route('management.kp-registrations.documents.revision', [$registration, $document]) }}" onsubmit="return confirm('Minta revisi dokumen ini?')">
                                    @csrf
                                    <input name="review_note" placeholder="Catatan revisi" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs">
                                    <button class="mt-2 w-full rounded-lg border border-amber-200 px-3 py-1.5 text-xs font-semibold text-amber-700">Revisi</button>
                                </form>
                                <form method="POST" action="{{ route('management.kp-registrations.documents.reject', [$registration, $document]) }}" onsubmit="return confirm('Tolak dokumen ini?')">
                                    @csrf
                                    <input name="review_note" placeholder="Alasan penolakan" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs">
                                    <button class="mt-2 w-full rounded-lg border border-rose-200 px-3 py-1.5 text-xs font-semibold text-rose-700">Tolak</button>
                                </form>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>
    </div>

    <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <h3 class="text-lg font-bold text-slate-950">Riwayat Aktivitas</h3>
        <div class="mt-4 space-y-2">
            @forelse($registration->logs->sortByDesc('created_at') as $log)
                <div class="rounded-lg border border-slate-200 p-3 text-sm">
                    <div class="font-semibold text-slate-900">{{ str_replace('_', ' ', ucfirst($log->action)) }}</div>
                    <div class="text-xs text-slate-500">{{ $log->user?->name ?? 'Sistem' }} · {{ $log->created_at->format('d M Y H:i') }}</div>
                    @if($log->note)<p class="mt-1 text-slate-600">{{ $log->note }}</p>@endif
                </div>
            @empty
                <p class="rounded-lg bg-slate-50 px-4 py-3 text-sm text-slate-500">Belum ada riwayat.</p>
            @endforelse
        </div>
    </section>
</div>
@endsection
