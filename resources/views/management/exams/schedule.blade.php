@extends('layouts.app')
@section('title','Jadwal Sidang - '.config('app.name'))
@section('page_title', $exam ? 'Edit Jadwal Sidang' : 'Jadwalkan Sidang')
@section('content')
<x-ui.card>
    <div class="mb-5"><h2 class="text-xl font-bold text-slate-950">{{ $examRequest->assignment->student->user->name }}</h2><p class="text-sm text-slate-500">{{ $examRequest->assignment->place->name }} | Pembimbing: {{ $examRequest->assignment->internalSupervisor ? lecturer_display_name($examRequest->assignment->internalSupervisor) : '-' }}</p></div>
    <form method="POST" action="{{ $exam ? route('management.exams.update',$exam) : route('management.exam-requests.schedule.store',$examRequest) }}" class="grid gap-4 md:grid-cols-2">
        @csrf @if($exam) @method('PUT') @endif
        @php
            $selectedExaminerIds = collect(old('examiner_ids', $exam?->examinerIds() ?? []))->map(fn ($id) => (int) $id)->all();
        @endphp
        <div class="md:col-span-2">
            <div class="flex flex-wrap items-end justify-between gap-2">
                <div>
                    <label class="text-sm font-semibold">Penguji Sidang</label>
                    <p class="mt-1 text-xs text-slate-500">Pilih 2 sampai 3 penguji. Pembimbing dalam boleh dipilih bila akun dosen tersebut juga memiliki role Penguji.</p>
                </div>
                <span class="rounded-full bg-cyan-50 px-3 py-1 text-xs font-bold text-cyan-700">Minimal 2 penguji</span>
            </div>
            <div class="mt-3 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                @foreach($examiners as $examiner)
                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 bg-white p-3 shadow-sm transition hover:border-cyan-300 hover:bg-cyan-50/40">
                        <input type="checkbox" name="examiner_ids[]" value="{{ $examiner->id }}" @checked(in_array($examiner->id, $selectedExaminerIds, true)) class="mt-1 rounded border-slate-300 text-cyan-700 focus:ring-cyan-500">
                        <span>
                            <span class="block text-sm font-bold text-slate-950">{{ lecturer_display_name($examiner) }}</span>
                            <span class="block text-xs text-slate-500">{{ $examiner->nidn_nip ?: 'Nomor dosen belum tersedia' }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
            @error('examiner_ids')<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror
            @error('examiner_ids.*')<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div><label class="text-sm font-semibold">Tanggal</label><input type="date" name="exam_date" value="{{ old('exam_date',$exam?->exam_date?->format('Y-m-d')) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">@error('exam_date')<p class="text-xs text-red-600">{{ $message }}</p>@enderror</div>
        <div><label class="text-sm font-semibold">Jam Mulai</label><input type="time" name="start_time" value="{{ old('start_time',$exam?->start_time ? substr($exam->start_time,0,5) : '') }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
        <div><label class="text-sm font-semibold">Jam Selesai</label><input type="time" name="end_time" value="{{ old('end_time',$exam?->end_time ? substr($exam->end_time,0,5) : '') }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">@error('end_time')<p class="text-xs text-red-600">{{ $message }}</p>@enderror</div>
        <div><label class="text-sm font-semibold">Mode</label><select name="mode" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">@foreach(['offline'=>'Offline','online'=>'Online','hybrid'=>'Hybrid'] as $value=>$label)<option value="{{ $value }}" @selected(old('mode',$exam?->mode)===$value)>{{ $label }}</option>@endforeach</select></div>
        <div><label class="text-sm font-semibold">Ruangan</label><input name="room" value="{{ old('room',$exam?->room) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">@error('room')<p class="text-xs text-red-600">{{ $message }}</p>@enderror</div>
        <div class="md:col-span-2"><label class="text-sm font-semibold">Link Meeting</label><input name="meeting_link" value="{{ old('meeting_link',$exam?->meeting_link) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">@error('meeting_link')<p class="text-xs text-red-600">{{ $message }}</p>@enderror</div>
        <div class="md:col-span-2"><label class="text-sm font-semibold">Catatan</label><textarea name="note" rows="3" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('note',$exam?->note) }}</textarea></div>
        <div class="md:col-span-2"><button class="rounded-lg bg-cyan-700 px-4 py-2 text-sm font-semibold text-white">Simpan Jadwal</button></div>
    </form>
</x-ui.card>
@endsection
