@extends('layouts.app')
@section('title', 'Preview Import Peserta - '.config('app.name'))
@section('page_title', 'Preview Import Peserta')
@section('content')
<div class="space-y-5">
    @if(session('status'))<div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-700">{{ session('status') }}</div>@endif
    <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div><p class="text-xs font-black uppercase tracking-widest text-cyan-700">{{ $batch->program?->code }}</p><h2 class="mt-1 text-xl font-black">{{ $batch->original_filename }}</h2><p class="mt-1 text-sm text-slate-500">Valid {{ $batch->valid_rows }} / Invalid {{ $batch->invalid_rows }} / Diimpor {{ $batch->imported_rows }}</p></div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('management.pkpa-enrollments.import') }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-bold">Kembali</a>
                @if($batch->valid_rows > 0 && $batch->status !== 'completed')
                    <form method="POST" action="{{ route('management.pkpa-enrollment-imports.run', $batch) }}">@csrf<button class="rounded-xl bg-cyan-700 px-4 py-2 text-sm font-black text-white">Import Row Valid</button></form>
                @endif
            </div>
        </div>
    </div>
    <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-black uppercase tracking-widest text-slate-500"><tr><th class="px-4 py-3">Baris</th><th class="px-4 py-3">NPM</th><th class="px-4 py-3">Nama Core</th><th class="px-4 py-3">Core ID</th><th class="px-4 py-3">Kelompok</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Pesan</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @foreach($batch->rows as $row)
                    <tr>
                        <td class="px-4 py-4">{{ $row->row_number }}</td>
                        <td class="px-4 py-4">{{ $row->student_number ?: '-' }}</td>
                        <td class="px-4 py-4">{{ $row->student_name ?: '-' }}</td>
                        <td class="px-4 py-4">{{ $row->resolved_core_user_id ?: $row->core_user_id ?: '-' }}</td>
                        <td class="px-4 py-4">{{ $row->group_code ?: '-' }}</td>
                        <td class="px-4 py-4"><span class="rounded-full px-2 py-1 text-xs font-black {{ $row->validation_status === 'valid' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">{{ str($row->validation_status)->replace('_', ' ')->headline() }}</span></td>
                        <td class="px-4 py-4 text-slate-600">{{ collect($row->validation_messages)->implode(' ') }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
