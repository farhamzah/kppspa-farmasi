@extends('layouts.app')
@section('title', 'Ganti Pembimbing Dalam - '.config('app.name'))
@section('page_title', 'Ganti Pembimbing Dalam')
@section('content')
<div class="space-y-5">
    <a href="{{ route('management.pkpa-enrollments.index', ['program_id' => $publication->pkpa_program_id]) }}" class="inline-flex min-h-11 items-center text-sm font-bold text-cyan-700">Kembali ke Peserta PKPA</a>
    @if($errors->any())<div role="alert" class="rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">{{ $errors->first() }}</div>@endif
    <div class="border-b border-slate-200 pb-4"><h2 class="text-xl font-black text-slate-950">Penggantian seluruh wahana</h2><p class="mt-1 text-sm text-slate-600">{{ $students->count() }} mahasiswa / {{ $publication->assignments->count() }} penempatan / {{ $students->first()->program->code }}</p></div>
    <form method="POST" action="{{ route('management.pkpa-enrollments.supervisor-replacement.store') }}" class="space-y-5">
        @csrf
        <input type="hidden" name="publication_id" value="{{ $publication->id }}">
        @foreach($students as $student)<input type="hidden" name="enrollment_ids[]" value="{{ $student->id }}">@endforeach
        <div class="grid gap-4 lg:grid-cols-2">
            <label class="block text-sm font-bold text-slate-700">Dosen pengganti
                <select name="replacement_core_user_id" required class="mt-2 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">
                    <option value="">Pilih Pembimbing Dalam</option>
                    @foreach($supervisors as $supervisor)<option value="{{ $supervisor->core_user_id }}" @selected(old('replacement_core_user_id') === (string) $supervisor->core_user_id)>{{ $supervisor->display_name }}</option>@endforeach
                </select>
            </label>
            <label class="block text-sm font-bold text-slate-700">Tanggal serah terima<input type="date" name="effective_date" value="{{ old('effective_date', today()->toDateString()) }}" required class="mt-2 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm"></label>
            <label class="block text-sm font-bold text-slate-700 lg:col-span-2">Alasan penggantian<textarea name="reason" rows="3" minlength="10" maxlength="2000" required class="mt-2 w-full rounded-lg border border-slate-300 bg-white p-3 text-sm">{{ old('reason') }}</textarea></label>
        </div>
        @if($supervisors->isEmpty())<p role="alert" class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">Belum ada dosen aktif untuk seluruh wahana terpilih. Periksa menu Pembimbing Dalam.</p>@endif
        <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
            <table class="min-w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 text-slate-600"><tr><th class="p-3">Mahasiswa</th><th class="p-3">Wahana / tempat</th><th class="p-3">Pembimbing saat ini</th><th class="p-3">Periode praktik</th></tr></thead>
                <tbody class="divide-y divide-slate-100">@foreach($publication->assignments->sortBy('student_name_snapshot') as $assignment)
                    <tr><td class="p-3 font-bold">{{ $assignment->student_name_snapshot }}<span class="mt-1 block text-xs font-normal text-slate-500">{{ $assignment->student_number_snapshot }}</span></td><td class="p-3">{{ $assignment->practice_domain_name_snapshot }}<span class="mt-1 block text-xs text-slate-500">{{ $assignment->practice_site_name_snapshot }}</span></td><td class="p-3">{{ $assignment->supervisors->firstWhere('supervisor_type', 'internal')?->display_name ?: 'Belum ditentukan' }}</td><td class="whitespace-nowrap p-3">{{ $assignment->start_date->format('d M Y') }} - {{ $assignment->end_date->format('d M Y') }}</td></tr>
                @endforeach</tbody>
            </table>
        </div>
        <label class="flex items-start gap-3 text-sm text-slate-700"><input type="checkbox" name="include_finished_reviews" value="1" required @checked(old('include_finished_reviews', '1') == '1') class="mt-1 h-4 w-4 rounded border-slate-300"><span>Alihkan tanggung jawab seluruh wahana, termasuk pemeriksaan yang belum selesai pada rotasi yang telah berakhir. Tanggal praktik, riwayat validasi, dan nilai final sebelumnya tetap dipertahankan.</span></label>
        <div class="flex flex-wrap items-center gap-3 border-t border-slate-200 pt-4"><button @disabled($supervisors->isEmpty()) class="min-h-11 rounded-lg bg-cyan-700 px-5 py-2 text-sm font-bold text-white disabled:opacity-40">Tinjau Penggantian</button><a href="{{ route('management.pkpa-enrollments.index') }}" class="inline-flex min-h-11 items-center px-3 text-sm font-bold text-slate-600">Batal</a></div>
    </form>
</div>
@endsection
