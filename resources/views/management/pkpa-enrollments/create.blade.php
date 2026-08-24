@extends('layouts.app')
@section('title', 'Tambah Peserta PKPA - '.config('app.name'))
@section('page_title', 'Tambah Peserta PKPA')
@section('content')
<div class="max-w-4xl space-y-5">
    @if($errors->any())<div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $errors->first() }}</div>@endif
    <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <form method="POST" action="{{ route('management.pkpa-enrollments.store') }}" class="space-y-5">
            @csrf
            <div class="grid gap-4 md:grid-cols-2">
                <div><label class="text-xs font-black uppercase tracking-widest text-slate-500">Program PKPA</label><select name="pkpa_program_id" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" required><option value="">Pilih program</option>@foreach($programs as $program)<option value="{{ $program->id }}" @selected(old('pkpa_program_id') == $program->id)>{{ $program->code }} - {{ $program->name }}</option>@endforeach</select></div>
                <div><label class="text-xs font-black uppercase tracking-widest text-slate-500">Kelompok Opsional</label><select name="pkpa_student_group_id" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"><option value="">Belum dikelompokkan</option>@foreach($groups as $group)<option value="{{ $group->id }}" @selected(old('pkpa_student_group_id') == $group->id)>{{ $group->program?->code }} / {{ $group->code }} - {{ $group->name }}</option>@endforeach</select></div>
                <input type="hidden" name="core_user_id" value="{{ old('core_user_id') }}">
                <div class="md:col-span-2">
                    <label class="text-xs font-black uppercase tracking-widest text-slate-500">NPM / NIM Mahasiswa</label>
                    <input
                        name="student_number"
                        value="{{ old('student_number') }}"
                        placeholder="Ketik NPM atau NIM mahasiswa dari Core"
                        class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"
                        required
                    >
                    <p class="mt-2 text-xs text-slate-500">Input manual NPM/NIM mahasiswa. Sistem akan mencari identitas mahasiswa ke Core saat disimpan.</p>
                </div>
            </div>
            <div><label class="text-xs font-black uppercase tracking-widest text-slate-500">Catatan</label><textarea name="notes" rows="3" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">{{ old('notes') }}</textarea></div>
            <div class="rounded-xl border border-cyan-100 bg-cyan-50 px-4 py-3 text-sm text-cyan-800">Sistem akan memvalidasi mahasiswa ke Core, menolak akun nonaktif atau role yang tidak sesuai, lalu membuat kewajiban wahana otomatis.</div>
            <div class="flex flex-wrap gap-2"><button class="rounded-xl bg-cyan-700 px-4 py-2 text-sm font-black text-white">Tambah Peserta</button><a href="{{ route('management.pkpa-enrollments.index') }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-bold">Batal</a></div>
        </form>
    </div>
</div>
@endsection
