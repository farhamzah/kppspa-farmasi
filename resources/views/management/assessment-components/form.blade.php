@extends('layouts.app')
@section('title','Form Komponen Penilaian - '.config('app.name'))
@section('page_title',$component->exists ? 'Edit Komponen Penilaian' : 'Tambah Komponen Penilaian')
@section('content')
<form method="POST" action="{{ $component->exists ? route('management.assessment-components.update', $component) : route('management.assessment-components.store') }}" class="max-w-3xl rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-100">
    @csrf @if($component->exists) @method('PUT') @endif
    <div class="grid gap-4 md:grid-cols-2">
        <label class="space-y-1 text-sm font-bold">Periode<select name="kp_period_id" class="w-full rounded-2xl border-slate-200">@foreach($periods as $period)<option value="{{ $period->id }}" @selected(old('kp_period_id',$component->kp_period_id)==$period->id)>{{ $period->name }}</option>@endforeach</select></label>
        <label class="space-y-1 text-sm font-bold">Jenis Penilai<select name="assessor_type" class="w-full rounded-2xl border-slate-200">@foreach(['pembimbing_dalam'=>'Pembimbing Dalam','pembimbing_lapangan'=>'Preseptor','penguji'=>'Penguji'] as $key=>$label)<option value="{{ $key }}" @selected(old('assessor_type',$component->assessor_type)===$key)>{{ $label }}</option>@endforeach</select></label>
        <label class="space-y-1 text-sm font-bold md:col-span-2">Nama Komponen<input name="component_name" value="{{ old('component_name',$component->component_name) }}" class="w-full rounded-2xl border-slate-200"></label>
        <label class="space-y-1 text-sm font-bold">Bobot (%)<input type="number" step="0.01" name="weight" value="{{ old('weight',$component->weight ?? 0) }}" class="w-full rounded-2xl border-slate-200"></label>
        <label class="space-y-1 text-sm font-bold">Max Score<input type="number" step="0.01" name="max_score" value="{{ old('max_score',$component->max_score ?? 100) }}" class="w-full rounded-2xl border-slate-200"></label>
        <label class="space-y-1 text-sm font-bold">Urutan<input type="number" name="sort_order" value="{{ old('sort_order',$component->sort_order ?? 0) }}" class="w-full rounded-2xl border-slate-200"></label>
        <label class="space-y-1 text-sm font-bold">Status<select name="status" class="w-full rounded-2xl border-slate-200"><option value="aktif" @selected(old('status',$component->status ?? 'aktif')==='aktif')>Aktif</option><option value="nonaktif" @selected(old('status',$component->status)==='nonaktif')>Nonaktif</option></select></label>
        <label class="flex items-center gap-2 text-sm font-bold md:col-span-2"><input type="checkbox" name="is_required" value="1" @checked(old('is_required',$component->is_required ?? true))> Wajib dinilai</label>
        <label class="space-y-1 text-sm font-bold md:col-span-2">Deskripsi<textarea name="description" rows="3" class="w-full rounded-2xl border-slate-200">{{ old('description',$component->description) }}</textarea></label>
    </div>
    @if($errors->any())<div class="mt-4 rounded-2xl bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700">{{ $errors->first() }}</div>@endif
    <div class="mt-6 flex justify-end gap-2"><a href="{{ route('management.assessment-components.index') }}" class="rounded-2xl border px-4 py-2 text-sm font-bold">Batal</a><button class="rounded-2xl bg-cyan-700 px-4 py-2 text-sm font-bold text-white">Simpan</button></div>
</form>
@endsection
