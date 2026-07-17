@csrf
<div class="grid gap-4 lg:grid-cols-2">
    <div>
        <label class="text-xs font-black uppercase tracking-widest text-slate-500">Kode Tempat</label>
        <input name="code" value="{{ old('code', $site->code) }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" required>
        @error('code')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="text-xs font-black uppercase tracking-widest text-slate-500">Nama Tempat</label>
        <input name="name" value="{{ old('name', $site->name) }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" required>
    </div>
    <div>
        <label class="text-xs font-black uppercase tracking-widest text-slate-500">Nama Legal</label>
        <input name="legal_name" value="{{ old('legal_name', $site->legal_name) }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
    </div>
    <div class="grid grid-cols-2 gap-3">
        <div>
            <label class="text-xs font-black uppercase tracking-widest text-slate-500">Wahana</label>
            <select name="practice_domain_id" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" required>
                <option value="">Pilih wahana</option>
                @foreach($domains as $domain)
                    <option value="{{ $domain->id }}" @selected((int) old('practice_domain_id', $site->practice_domain_id) === $domain->id)>{{ $domain->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="text-xs font-black uppercase tracking-widest text-slate-500">Subjenis</label>
            <select name="practice_domain_option_id" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                <option value="">Tidak ada</option>
                @foreach($domains as $domain)
                    @foreach($domain->activeOptions as $option)
                        <option value="{{ $option->id }}" @selected((int) old('practice_domain_option_id', $site->practice_domain_option_id) === $option->id)>{{ $domain->name }} - {{ $option->name }}</option>
                    @endforeach
                @endforeach
            </select>
            @error('practice_domain_option_id')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
        </div>
    </div>
    <div class="lg:col-span-2">
        <label class="text-xs font-black uppercase tracking-widest text-slate-500">Alamat</label>
        <textarea name="address" rows="3" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">{{ old('address', $site->address) }}</textarea>
    </div>
    <div><label class="text-xs font-black uppercase text-slate-500">Desa/Kelurahan</label><input name="village" value="{{ old('village', $site->village) }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"></div>
    <div><label class="text-xs font-black uppercase text-slate-500">Kecamatan</label><input name="district" value="{{ old('district', $site->district) }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"></div>
    <div><label class="text-xs font-black uppercase text-slate-500">Kota</label><input name="city" value="{{ old('city', $site->city) }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"></div>
    <div><label class="text-xs font-black uppercase text-slate-500">Provinsi</label><input name="province" value="{{ old('province', $site->province) }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"></div>
    <div><label class="text-xs font-black uppercase text-slate-500">Kode Pos</label><input name="postal_code" value="{{ old('postal_code', $site->postal_code) }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"></div>
    <div><label class="text-xs font-black uppercase text-slate-500">Telepon</label><input name="phone" value="{{ old('phone', $site->phone) }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"></div>
    <div><label class="text-xs font-black uppercase text-slate-500">Email</label><input type="email" name="email" value="{{ old('email', $site->email) }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"></div>
    <div><label class="text-xs font-black uppercase text-slate-500">Website</label><input name="website" value="{{ old('website', $site->website) }}" placeholder="https://..." class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"></div>
    <div><label class="text-xs font-black uppercase text-slate-500">Contact Person</label><input name="contact_person_name" value="{{ old('contact_person_name', $site->contact_person_name) }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"></div>
    <div><label class="text-xs font-black uppercase text-slate-500">HP Contact Person</label><input name="contact_person_phone" value="{{ old('contact_person_phone', $site->contact_person_phone) }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"></div>
    <div><label class="text-xs font-black uppercase text-slate-500">Mulai Kerja Sama</label><input type="date" name="cooperation_start_date" value="{{ old('cooperation_start_date', optional($site->cooperation_start_date)->format('Y-m-d')) }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"></div>
    <div><label class="text-xs font-black uppercase text-slate-500">Akhir Kerja Sama</label><input type="date" name="cooperation_end_date" value="{{ old('cooperation_end_date', optional($site->cooperation_end_date)->format('Y-m-d')) }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">@error('cooperation_end_date')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror</div>
    <div>
        <label class="text-xs font-black uppercase text-slate-500">Status</label>
        <select name="status" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
            @foreach(\App\Models\PkpaPracticeSite::STATUSES as $status)
                <option value="{{ $status }}" @selected(old('status', $site->status ?: 'draft') === $status)>{{ ucfirst($status) }}</option>
            @endforeach
        </select>
    </div>
    <label class="mt-6 flex items-center gap-2 text-sm font-bold text-slate-700"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $site->exists ? $site->is_active : true)) class="rounded border-slate-300"> Aktif</label>
    <div class="lg:col-span-2"><label class="text-xs font-black uppercase text-slate-500">Catatan</label><textarea name="notes" rows="3" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">{{ old('notes', $site->notes) }}</textarea></div>
</div>
<div class="mt-5 flex flex-wrap gap-2"><button class="rounded-xl bg-cyan-700 px-4 py-2 text-sm font-black text-white">Simpan</button><a href="{{ route('management.pkpa-practice-sites.index') }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-bold">Batal</a></div>
