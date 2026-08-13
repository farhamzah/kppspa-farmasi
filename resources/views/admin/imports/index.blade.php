@extends('layouts.app')

@section('title', 'Impor Pengguna - '.config('app.name'))
@section('page_title', 'Impor Pengguna')

@section('content')
<div class="space-y-5">
    @if($errors->any())
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif

    <section class="grid gap-4 md:grid-cols-5">
        @foreach(['Unduh template', 'Unggah berkas', 'Pratinjau validasi', 'Proses impor', 'Lihat hasil'] as $step)
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
                <p class="text-xs font-semibold uppercase tracking-wide text-teal-700">Langkah {{ $loop->iteration }}</p>
                <p class="mt-2 text-sm font-bold text-slate-950">{{ $step }}</p>
            </div>
        @endforeach
    </section>

    <section class="grid gap-5 xl:grid-cols-[0.8fr_1.2fr]">
        <div class="space-y-5">
            <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <h2 class="text-lg font-bold text-slate-950">Unduh Template</h2>
                <p class="mt-1 text-sm text-slate-500">Pilih template sesuai tipe impor.</p>
                <div class="mt-4 grid gap-2">
                    @foreach($types as $type)
                        <a href="{{ route('admin.import-users.template', $type) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Template {{ str_replace('_', ' ', ucwords($type, '_')) }}</a>
                    @endforeach
                </div>
            </div>

            <form method="POST" action="{{ route('admin.import-users.preview') }}" enctype="multipart/form-data" class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                @csrf
                <h2 class="text-lg font-bold text-slate-950">Unggah Berkas</h2>
                <div class="mt-4 space-y-4">
                    <div>
                        <label class="text-sm font-semibold text-slate-700">Tipe Impor</label>
                        <select name="import_type" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            @foreach($types as $type)
                                <option value="{{ $type }}" @selected($importType === $type)>{{ str_replace('_', ' ', ucwords($type, '_')) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-slate-700">Berkas Excel/CSV</label>
                        <input name="file" type="file" accept=".xlsx,.xls,.csv" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <p class="mt-1 text-xs text-slate-500">Format: xlsx, xls, csv. Maksimal 5MB.</p>
                    </div>
                    <button class="w-full rounded-lg bg-teal-600 px-4 py-2.5 text-sm font-semibold text-white">Pratinjau Impor</button>
                </div>
            </form>
        </div>

        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-bold text-slate-950">Pratinjau Validasi</h2>
                    <p class="mt-1 text-sm text-slate-500">Baris valid dapat diproses. Baris bermasalah akan dicatat dan tidak dibuat.</p>
                </div>
                <a href="{{ route('admin.import-users.history') }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700">Riwayat</a>
            </div>

            @if($preview)
                @php
                    $validCount = collect($preview)->where('valid', true)->count();
                    $errorCount = collect($preview)->where('valid', false)->count();
                @endphp
                <div class="mt-4 grid gap-3 md:grid-cols-3">
                    <div class="rounded-lg bg-slate-50 p-3"><p class="text-xs text-slate-500">Total</p><p class="text-xl font-bold">{{ count($preview) }}</p></div>
                    <div class="rounded-lg bg-emerald-50 p-3"><p class="text-xs text-emerald-700">Valid</p><p class="text-xl font-bold text-emerald-700">{{ $validCount }}</p></div>
                    <div class="rounded-lg bg-rose-50 p-3"><p class="text-xs text-rose-700">Error</p><p class="text-xl font-bold text-rose-700">{{ $errorCount }}</p></div>
                </div>
                <div class="mt-4 max-h-[520px] overflow-auto rounded-xl border border-slate-200">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-3 py-2">Baris</th>
                                <th class="px-3 py-2">Nama</th>
                                <th class="px-3 py-2">Email</th>
                                <th class="px-3 py-2">Tipe</th>
                                <th class="px-3 py-2">Peran</th>
                                <th class="px-3 py-2">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($preview as $item)
                                <tr class="align-top">
                                    <td class="px-3 py-2">{{ $item['row_number'] }}</td>
                                    <td class="px-3 py-2">{{ $item['data']['name'] ?: '-' }}</td>
                                    <td class="px-3 py-2">{{ $item['data']['email'] ?: '-' }}</td>
                                    <td class="px-3 py-2">{{ $item['data']['profile_type'] ?: '-' }}</td>
                                    <td class="px-3 py-2">{{ implode(', ', $item['data']['roles']) }}</td>
                                    <td class="px-3 py-2">
                                        @if($item['valid'])
                                            <span class="rounded-full bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-700">Valid</span>
                                        @else
                                            <span class="rounded-full bg-rose-50 px-2 py-1 text-xs font-semibold text-rose-700">{{ implode(' ', $item['errors']) }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <form method="POST" action="{{ route('admin.import-users.process') }}" class="mt-4" onsubmit="return confirm('Proses baris valid dari preview ini?')">
                    @csrf
                    <button class="w-full rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white">Proses Impor</button>
                </form>
            @else
                <div class="mt-6 rounded-xl bg-slate-50 px-4 py-10 text-center text-sm text-slate-500">Belum ada pratinjau. Unggah berkas untuk melihat validasi per baris.</div>
            @endif
        </div>
    </section>
</div>
@endsection
