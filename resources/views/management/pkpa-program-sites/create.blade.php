@extends('layouts.app')
@section('title', 'Tambah Tempat Tersedia - '.config('app.name'))
@section('page_title', 'Tambah Tempat Tersedia')
@section('content')
<div class="max-w-3xl space-y-5">
    @if($errors->any())<div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $errors->first() }}</div>@endif
    <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <form method="POST" action="{{ route('management.pkpa-program-sites.store') }}" class="space-y-4">
            @csrf
            <div>
                <label class="text-xs font-black uppercase tracking-widest text-slate-500">Program PKPA</label>
                <select name="pkpa_program_id" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Pilih program</option>
                    @foreach($programs as $program)<option value="{{ $program->id }}" @selected(old('pkpa_program_id') == $program->id)>{{ $program->code }} - {{ $program->name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="text-xs font-black uppercase tracking-widest text-slate-500">Tempat Praktik</label>
                <select name="practice_site_id" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Pilih tempat</option>
                    @foreach($sites as $site)<option value="{{ $site->id }}" @selected(old('practice_site_id') == $site->id)>{{ $site->code }} - {{ $site->name }} / {{ $site->practiceDomain?->name }}{{ $site->practiceDomainOption ? ' - '.$site->practiceDomainOption->name : '' }}</option>@endforeach
                </select>
                <p class="mt-2 text-xs text-slate-500">Wahana dan subjenis diambil dari master tempat praktik. Sistem menolak tempat nonaktif, domain tidak aktif pada program, dan duplikasi program-tempat.</p>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
                <div><label class="text-xs font-black uppercase tracking-widest text-slate-500">Status</label><select name="status" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">@foreach(\App\Models\PkpaProgramSite::STATUSES as $status)<option value="{{ $status }}" @selected(old('status', 'active') === $status)>{{ str($status)->replace('_', ' ')->headline() }}</option>@endforeach</select></div>
                <label class="mt-6 flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm font-bold text-slate-700"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', '1'))> Aktif</label>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
                <div><label class="text-xs font-black uppercase tracking-widest text-slate-500">Default Minimal Peserta</label><input type="number" name="default_minimum_students" min="0" value="{{ old('default_minimum_students', 0) }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"></div>
                <div><label class="text-xs font-black uppercase tracking-widest text-slate-500">Default Kapasitas Maks</label><input type="number" name="default_maximum_students" min="1" value="{{ old('default_maximum_students', 1) }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"></div>
            </div>
            <div class="grid gap-4 md:grid-cols-3">
                <div><label class="text-xs font-black uppercase tracking-widest text-slate-500">Catatan Registrasi</label><textarea name="registration_notes" rows="3" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">{{ old('registration_notes') }}</textarea></div>
                <div><label class="text-xs font-black uppercase tracking-widest text-slate-500">Catatan Operasional</label><textarea name="operational_notes" rows="3" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">{{ old('operational_notes') }}</textarea></div>
                <div><label class="text-xs font-black uppercase tracking-widest text-slate-500">Catatan Syarat</label><textarea name="requirements_notes" rows="3" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">{{ old('requirements_notes') }}</textarea></div>
            </div>
            <div class="flex flex-wrap gap-2">
                <button class="rounded-xl bg-cyan-700 px-4 py-2 text-sm font-black text-white">Simpan</button>
                <a href="{{ route('management.pkpa-program-sites.index') }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-bold">Batal</a>
            </div>
        </form>
    </div>
</div>
@endsection
