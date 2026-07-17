@extends('layouts.app')
@section('title', 'Konfigurasi Wahana Program - '.config('app.name'))
@section('page_title', 'Konfigurasi Wahana dan Durasi')
@section('content')
<div class="space-y-5">
    @if($errors->any())<div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $errors->first() }}</div>@endif
    <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div><h2 class="text-xl font-black text-slate-950">{{ $program->name }}</h2><p class="text-sm text-slate-500">{{ $program->code }} · durasi boleh berbeda per wahana dan belum diisi otomatis.</p></div>
            <a href="{{ route('management.pkpa-programs.readiness', $program) }}" class="rounded-xl border border-cyan-200 px-4 py-2 text-sm font-black text-cyan-700">Periksa Kesiapan Program</a>
        </div>
    </div>
    <form method="POST" action="{{ route('management.pkpa-programs.configure.update', $program) }}" class="space-y-4">
        @csrf
        @method('PUT')
        @foreach($program->domains as $domainConfig)
            <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <h3 class="text-lg font-black text-slate-950">{{ $domainConfig->practiceDomain->name }}</h3>
                        <p class="text-sm text-slate-500">{{ $domainConfig->selectionModeLabel() }} · {{ $domainConfig->is_required ? 'Wajib' : 'Opsional' }} · {{ $domainConfig->durationLabel() }}</p>
                        @if($domainConfig->practiceDomain->code === 'PEM')
                            <p class="mt-2 text-sm font-bold text-cyan-700">Pilihan tersedia: {{ $domainConfig->practiceDomain->options->where('is_active', true)->pluck('name')->join(', ') }}. Mahasiswa nantinya wajib memperoleh salah satu.</p>
                        @endif
                    </div>
                    <span class="rounded-full px-2 py-1 text-xs font-black {{ $domainConfig->isDurationComplete() ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-100' : 'bg-amber-50 text-amber-700 ring-1 ring-amber-100' }}">{{ $domainConfig->isDurationComplete() ? 'Lengkap' : 'Belum lengkap' }}</span>
                </div>
                <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-6">
                    <input type="hidden" name="domains[{{ $domainConfig->id }}][selection_mode]" value="{{ $domainConfig->selection_mode }}">
                    <input type="hidden" name="domains[{{ $domainConfig->id }}][minimum_option_count]" value="{{ $domainConfig->minimum_option_count }}">
                    <div><label class="text-xs font-black uppercase text-slate-500">Durasi</label><input type="number" step="0.01" min="0.01" name="domains[{{ $domainConfig->id }}][duration_value]" value="{{ old("domains.$domainConfig->id.duration_value", $domainConfig->duration_value) }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"></div>
                    <div><label class="text-xs font-black uppercase text-slate-500">Satuan</label><select name="domains[{{ $domainConfig->id }}][duration_unit]" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"><option value="">Pilih</option>@foreach($durationUnits as $unit)<option value="{{ $unit }}" @selected(old("domains.$domainConfig->id.duration_unit", $domainConfig->duration_unit) === $unit)>{{ str_replace('_', ' ', $unit) }}</option>@endforeach</select></div>
                    <div><label class="text-xs font-black uppercase text-slate-500">Hari Efektif</label><input type="number" min="1" name="domains[{{ $domainConfig->id }}][minimum_effective_days]" value="{{ old("domains.$domainConfig->id.minimum_effective_days", $domainConfig->minimum_effective_days) }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"></div>
                    <div><label class="text-xs font-black uppercase text-slate-500">Jam Praktik</label><input type="number" min="1" name="domains[{{ $domainConfig->id }}][minimum_practice_hours]" value="{{ old("domains.$domainConfig->id.minimum_practice_hours", $domainConfig->minimum_practice_hours) }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"></div>
                    <div><label class="text-xs font-black uppercase text-slate-500">Bobot %</label><input type="number" step="0.01" min="0" max="100" name="domains[{{ $domainConfig->id }}][weight_percentage]" value="{{ old("domains.$domainConfig->id.weight_percentage", $domainConfig->weight_percentage) }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"></div>
                    <div class="md:col-span-2 xl:col-span-1"><label class="text-xs font-black uppercase text-slate-500">Instruksi</label><input name="domains[{{ $domainConfig->id }}][instructions]" value="{{ old("domains.$domainConfig->id.instructions", $domainConfig->instructions) }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"></div>
                </div>
            </div>
        @endforeach
        <div class="sticky bottom-3 rounded-2xl border border-cyan-100 bg-white/95 p-4 shadow-lg backdrop-blur">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><p class="text-sm font-bold text-slate-600">Total bobot saat ini: {{ number_format($program->domains->sum('weight_percentage'), 2) }}%. Total 100% belum diwajibkan pada tahap ini.</p><button class="rounded-xl bg-cyan-700 px-4 py-2 text-sm font-black text-white">Simpan Konfigurasi</button></div>
        </div>
    </form>
</div>
@endsection
