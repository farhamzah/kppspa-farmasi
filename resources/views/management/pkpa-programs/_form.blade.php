@csrf
<div class="grid gap-4 lg:grid-cols-2">
    <div>
        <label class="text-xs font-black uppercase tracking-widest text-slate-500">Kode Program</label>
        <input name="code" value="{{ old('code', $program->code) }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" required>
        @error('code')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="text-xs font-black uppercase tracking-widest text-slate-500">Nama Program</label>
        <input name="name" value="{{ old('name', $program->name) }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" required>
        @error('name')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="text-xs font-black uppercase tracking-widest text-slate-500">Tahun Akademik</label>
        <input name="academic_year" value="{{ old('academic_year', $program->academic_year) }}" placeholder="2026/2027" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
    </div>
    <div>
        <label class="text-xs font-black uppercase tracking-widest text-slate-500">Angkatan</label>
        <input name="cohort_name" value="{{ old('cohort_name', $program->cohort_name) }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
    </div>
    <div>
        <label class="text-xs font-black uppercase tracking-widest text-slate-500">Semester</label>
        <input name="semester" value="{{ old('semester', $program->semester) }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
    </div>
    <div class="grid grid-cols-2 gap-3">
        <div>
            <label class="text-xs font-black uppercase tracking-widest text-slate-500">Mulai</label>
            <input type="date" name="start_date" value="{{ old('start_date', optional($program->start_date)->format('Y-m-d')) }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
        </div>
        <div>
            <label class="text-xs font-black uppercase tracking-widest text-slate-500">Selesai</label>
            <input type="date" name="end_date" value="{{ old('end_date', optional($program->end_date)->format('Y-m-d')) }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
            @error('end_date')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
        </div>
    </div>
    <div>
        <label class="text-xs font-black uppercase tracking-widest text-slate-500">Pendaftaran Mulai</label>
        <input type="datetime-local" name="registration_start_at" value="{{ old('registration_start_at', optional($program->registration_start_at)->format('Y-m-d\TH:i')) }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
    </div>
    <div>
        <label class="text-xs font-black uppercase tracking-widest text-slate-500">Pendaftaran Selesai</label>
        <input type="datetime-local" name="registration_end_at" value="{{ old('registration_end_at', optional($program->registration_end_at)->format('Y-m-d\TH:i')) }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
        @error('registration_end_at')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
    </div>
    <div class="lg:col-span-2">
        <label class="text-xs font-black uppercase tracking-widest text-slate-500">Deskripsi</label>
        <textarea name="description" rows="4" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">{{ old('description', $program->description) }}</textarea>
    </div>
</div>
<div class="mt-5 flex flex-wrap gap-2">
    <button class="rounded-xl bg-cyan-700 px-4 py-2 text-sm font-black text-white">Simpan</button>
    <a href="{{ route('management.pkpa-programs.index') }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-bold text-slate-700">Batal</a>
</div>
