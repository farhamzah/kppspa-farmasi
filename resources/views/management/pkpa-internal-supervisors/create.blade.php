@extends('layouts.app')
@section('title', 'Tambah Pembimbing Dalam - '.config('app.name'))
@section('page_title', 'Tambah Pembimbing Dalam')
@section('content')
@php
    $statusLabels = [
        'draft' => 'Draf',
        'active' => 'Aktif',
        'inactive' => 'Nonaktif',
        'suspended' => 'Ditangguhkan',
        'expired' => 'Kedaluwarsa',
    ];
@endphp
<div class="max-w-3xl space-y-5">
    @if($errors->any())<div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $errors->first() }}</div>@endif
    <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <div class="mb-5 rounded-2xl border border-cyan-100 bg-cyan-50/70 px-4 py-3 text-sm leading-6 text-cyan-950">
            Pilih program dan wahana, lalu cari dosen dari Core. Hanya dosen yang sudah aktif dan memiliki akses MY PKPA yang akan muncul sebagai pilihan Pembimbing Dalam.
        </div>
        <form method="POST" action="{{ route('management.pkpa-internal-supervisors.store') }}" class="space-y-4">
            @csrf
            <div class="grid gap-4 md:grid-cols-2">
                <div><label class="text-xs font-black uppercase tracking-widest text-slate-500">Program PKPA</label><select name="pkpa_program_id" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"><option value="">Pilih program</option>@foreach($programs as $program)<option value="{{ $program->id }}" @selected(old('pkpa_program_id') == $program->id)>{{ $program->code }} - {{ $program->name }}</option>@endforeach</select></div>
                <div><label class="text-xs font-black uppercase tracking-widest text-slate-500">Wahana</label><select name="practice_domain_id" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"><option value="">Pilih wahana</option>@foreach($domains as $domain)<option value="{{ $domain->id }}" @selected(old('practice_domain_id') == $domain->id)>{{ $domain->name }}</option>@endforeach</select></div>
            </div>
            <x-management.core-directory-picker
                field-name="core_user_id"
                field-label="Dosen Dari Core"
                :search-url="route('management.core-directory.lecturers')"
                placeholder="Ketik nama dosen, email, NIDN, atau Core ID"
                helper="Nama, email, dan peran dosen diambil dari Core. Sistem tidak membuat akun lokal atau password baru."
                :required="true"
                :value="old('core_user_id')"
            />
            <div class="grid gap-4 md:grid-cols-3">
                <div><label class="text-xs font-black uppercase tracking-widest text-slate-500">Maks. Mahasiswa Aktif</label><input type="number" name="maximum_active_students" min="0" value="{{ old('maximum_active_students', 0) }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"><p class="mt-1 text-xs text-slate-500">0 berarti tidak dibatasi.</p></div>
                <div><label class="text-xs font-black uppercase tracking-widest text-slate-500">Maks. per Program</label><input type="number" name="maximum_students_per_program" min="0" value="{{ old('maximum_students_per_program', 0) }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"><p class="mt-1 text-xs text-slate-500">0 berarti tidak dibatasi.</p></div>
                <div><label class="text-xs font-black uppercase tracking-widest text-slate-500">Status</label><select name="status" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">@foreach(\App\Models\PkpaInternalSupervisorEligibility::STATUSES as $status)<option value="{{ $status }}" @selected(old('status', 'active') === $status)>{{ $statusLabels[$status] ?? str($status)->replace('_', ' ')->headline() }}</option>@endforeach</select></div>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
                <div><label class="text-xs font-black uppercase tracking-widest text-slate-500">Efektif Mulai</label><input type="date" name="effective_start_date" value="{{ old('effective_start_date') }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"></div>
                <div><label class="text-xs font-black uppercase tracking-widest text-slate-500">Efektif Selesai</label><input type="date" name="effective_end_date" value="{{ old('effective_end_date') }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"></div>
            </div>
            <div><label class="text-xs font-black uppercase tracking-widest text-slate-500">Catatan</label><textarea name="notes" rows="3" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">{{ old('notes') }}</textarea></div>
            <div class="flex flex-wrap gap-2"><button class="rounded-xl bg-cyan-700 px-4 py-2 text-sm font-black text-white">Simpan</button><a href="{{ route('management.pkpa-internal-supervisors.index') }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-bold">Batal</a></div>
        </form>
    </div>
</div>
@endsection
