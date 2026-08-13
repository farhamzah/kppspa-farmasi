@extends('layouts.app')
@section('title', 'Import Peserta PKPA - '.config('app.name'))
@section('page_title', 'Import Peserta PKPA')
@section('content')
<div class="space-y-5">
    @if(session('status'))<div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-700">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $errors->first() }}</div>@endif
    <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
            <div><p class="text-xs font-black uppercase tracking-widest text-cyan-700">Pratinjau wajib sebelum final</p><h2 class="mt-2 text-xl font-black">Unggah CSV/XLSX peserta</h2><p class="mt-1 max-w-2xl text-sm text-slate-600">Kolom wajib: core_user_id, npm, group_code, notes. Password dan peran akan ditolak.</p></div>
            <a href="{{ route('management.pkpa-enrollments.import.template') }}" class="rounded-xl border border-cyan-200 px-4 py-2 text-sm font-black text-cyan-700">Unduh Template</a>
        </div>
        <form method="POST" action="{{ route('management.pkpa-enrollments.import.preview') }}" enctype="multipart/form-data" class="mt-6 grid gap-4 md:grid-cols-[1fr_1fr_auto] md:items-end">
            @csrf
            <div><label class="text-xs font-black uppercase tracking-widest text-slate-500">Program</label><select name="pkpa_program_id" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" required><option value="">Pilih program</option>@foreach($programs as $program)<option value="{{ $program->id }}">{{ $program->code }} - {{ $program->name }}</option>@endforeach</select></div>
            <div><label class="text-xs font-black uppercase tracking-widest text-slate-500">File</label><input type="file" name="file" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" accept=".csv,.txt,.xlsx,.xls" required></div>
            <button class="rounded-xl bg-cyan-700 px-4 py-2 text-sm font-black text-white">Pratinjau</button>
        </form>
    </div>
    <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        <div class="border-b border-slate-200 px-5 py-4"><h3 class="font-black">Riwayat import terakhir</h3></div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-black uppercase tracking-widest text-slate-500"><tr><th class="px-4 py-3">File</th><th class="px-4 py-3">Program</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Valid</th><th class="px-4 py-3">Invalid</th><th class="px-4 py-3 text-right">Aksi</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @forelse($batches as $batch)
                    <tr><td class="px-4 py-4">{{ $batch->original_filename }}</td><td class="px-4 py-4">{{ $batch->program?->code }}</td><td class="px-4 py-4">{{ str($batch->status)->headline() }}</td><td class="px-4 py-4">{{ $batch->valid_rows }}</td><td class="px-4 py-4">{{ $batch->invalid_rows }}</td><td class="px-4 py-4 text-right"><a href="{{ route('management.pkpa-enrollment-imports.show', $batch) }}" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-bold">Detail</a></td></tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-10 text-center text-slate-500">Belum ada batch import.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
