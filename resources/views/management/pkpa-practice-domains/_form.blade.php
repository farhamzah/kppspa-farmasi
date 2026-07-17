@csrf
<div class="grid gap-4 lg:grid-cols-2">
    <div>
        <label class="text-xs font-black uppercase tracking-widest text-slate-500">Kode</label>
        <input name="code" value="{{ old('code', $domain->code) }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" required>
        @error('code')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="text-xs font-black uppercase tracking-widest text-slate-500">Nama</label>
        <input name="name" value="{{ old('name', $domain->name) }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" required>
    </div>
    <div>
        <label class="text-xs font-black uppercase tracking-widest text-slate-500">Nama Singkat</label>
        <input name="short_name" value="{{ old('short_name', $domain->short_name) }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
    </div>
    <div>
        <label class="text-xs font-black uppercase tracking-widest text-slate-500">Urutan</label>
        <input type="number" name="sort_order" value="{{ old('sort_order', $domain->sort_order ?? 0) }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
    </div>
    <div class="lg:col-span-2">
        <label class="text-xs font-black uppercase tracking-widest text-slate-500">Deskripsi</label>
        <textarea name="description" rows="4" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">{{ old('description', $domain->description) }}</textarea>
    </div>
    <label class="flex items-center gap-2 text-sm font-bold text-slate-700"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $domain->exists ? $domain->is_active : true)) class="rounded border-slate-300"> Aktif</label>
    @if($domain->is_system)
        <label class="flex items-center gap-2 text-sm font-bold text-amber-700"><input type="checkbox" name="confirm_system_deactivation" value="1" class="rounded border-amber-300"> Saya paham dampak menonaktifkan wahana sistem.</label>
    @endif
</div>
<div class="mt-5 flex flex-wrap gap-2"><button class="rounded-xl bg-cyan-700 px-4 py-2 text-sm font-black text-white">Simpan</button><a href="{{ route('management.pkpa-practice-domains.index') }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-bold">Batal</a></div>
