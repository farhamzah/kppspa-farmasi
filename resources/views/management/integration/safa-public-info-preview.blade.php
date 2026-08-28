@extends('layouts.app')
@section('title','Pemeriksaan Info SAFA - '.config('app.name'))
@section('page_title','Pemeriksaan Integrasi SAFA')
@section('content')
<div class="space-y-5">
    <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="text-xs font-black uppercase tracking-widest text-cyan-700">Pratinjau aman publik</p>
                <h2 class="mt-2 text-2xl font-black text-slate-950">Pratinjau Informasi Publik PKPA untuk SAFA</h2>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500">Halaman ini menampilkan whitelist informasi umum yang aman untuk portal publik. Data operasional individual tidak ditampilkan.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('management.integration.external-document-references.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Referensi Draf</a>
                <a href="{{ route('management.integration.tu-payload-preview') }}" class="rounded-lg border border-cyan-200 px-4 py-2 text-sm font-semibold text-cyan-700">Periksa TU</a>
                <a href="{{ route('management.integration.safa-public-info-preview.json', request()->query()) }}" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Pratinjau JSON</a>
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
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Visibilitas</p>
            <p class="mt-2 text-sm font-black text-cyan-700">{{ $payload['public_visibility'] }}</p>
        </div>
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Sanitasi publik</p>
            <p class="mt-2 text-xl font-black {{ $payload['private_data_excluded'] ? 'text-emerald-700' : 'text-rose-700' }}">{{ $payload['private_data_excluded'] ? 'Aktif' : 'Perlu cek' }}</p>
        </div>
    </section>

    <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <form method="GET" class="grid gap-3 md:grid-cols-[1fr_auto]">
            <div>
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">ID Periode</label>
                <input name="period_id" value="{{ $periodId }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Opsional">
            </div>
            <button class="self-end rounded-lg bg-cyan-700 px-4 py-2 text-sm font-semibold text-white">Terapkan</button>
        </form>
    </section>

    @if($payload['period'])
        <section class="grid gap-5 xl:grid-cols-[0.8fr_1.2fr]">
            <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Periode Aktif</p>
                <h3 class="mt-2 text-2xl font-black text-slate-950">{{ $payload['period']['name'] }}</h3>
                <p class="mt-1 text-sm text-slate-500">{{ $payload['period']['academic_year'] }} / {{ $payload['period']['semester'] }}</p>
                <span class="mt-4 inline-flex rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700 ring-1 ring-emerald-100">{{ $payload['period']['status_label'] }}</span>
                <p class="mt-4 text-sm leading-6 text-slate-600">{{ $payload['period']['description'] ?: 'Deskripsi periode belum tersedia.' }}</p>
            </div>

            <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Timeline</p>
                <div class="mt-4 grid gap-3 md:grid-cols-2">
                    @foreach($payload['timeline'] as $item)
                        <div class="rounded-xl border border-slate-200 p-4">
                            <p class="font-bold text-slate-900">{{ $item['label'] }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ $item['start'] ?: '-' }} sampai {{ $item['end'] ?: '-' }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="grid gap-5 xl:grid-cols-2">
            <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <h3 class="text-lg font-black text-slate-950">Persyaratan Umum</h3>
                <div class="mt-4 space-y-3">
                    @forelse($payload['requirements'] as $requirement)
                        <div class="rounded-xl border border-slate-200 p-4">
                            <div class="flex items-start justify-between gap-3">
                                <p class="font-bold text-slate-900">{{ $requirement['name'] }}</p>
                                <span class="rounded-full {{ $requirement['is_required'] ? 'bg-cyan-50 text-cyan-700' : 'bg-slate-100 text-slate-600' }} px-2 py-1 text-xs font-bold">{{ $requirement['is_required'] ? 'Wajib' : 'Opsional' }}</span>
                            </div>
                            <p class="mt-2 text-sm leading-6 text-slate-500">{{ $requirement['description'] ?: '-' }}</p>
                            <p class="mt-2 text-xs text-slate-500">{{ implode(', ', $requirement['allowed_file_types']) }} / max {{ $requirement['max_file_size_mb'] }} MB</p>
                        </div>
                    @empty
                        <p class="rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-500">Belum ada persyaratan umum aktif.</p>
                    @endforelse
                </div>
            </div>

            <div class="space-y-5">
                <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                    <h3 class="text-lg font-black text-slate-950">Pengumuman PKPA</h3>
                    <div class="mt-4 space-y-3">
                        @foreach($payload['announcements'] as $announcement)
                            <div class="rounded-xl border border-slate-200 p-4">
                                <p class="font-bold text-slate-900">{{ $announcement['title'] }}</p>
                                <p class="mt-2 text-sm leading-6 text-slate-500">{{ $announcement['body'] }}</p>
                                <p class="mt-2 text-xs font-semibold text-cyan-700">{{ $announcement['period_name'] }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                    <h3 class="text-lg font-black text-slate-950">Kontak dan Status Umum</h3>
                    <div class="mt-4 grid gap-3 md:grid-cols-2">
                        <div class="rounded-xl bg-slate-50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Unit</p>
                            <p class="mt-2 font-bold text-slate-900">{{ $payload['contact']['unit'] }}</p>
                            <p class="text-xs text-slate-500">{{ $payload['contact']['source'] }}</p>
                        </div>
                        <div class="rounded-xl bg-slate-50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Pendaftaran</p>
                            <p class="mt-2 font-bold text-slate-900">{{ data_get($payload, 'registration_status.label') ?? '-' }}</p>
                            <p class="text-xs text-slate-500">Daftar: {{ data_get($payload, 'registration_status.registration_open') ? 'dibuka' : 'ditutup' }} / Pilih tempat: {{ data_get($payload, 'registration_status.selection_open') ? 'dibuka' : 'ditutup' }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    @else
        <section class="rounded-2xl bg-white p-8 text-center text-sm text-slate-500 shadow-sm ring-1 ring-slate-200">Belum ada periode PKPA untuk dipratinjau.</section>
    @endif
</div>
@endsection
