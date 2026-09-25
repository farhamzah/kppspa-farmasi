@extends('layouts.app')
@section('title', 'Kelola Preseptor - '.config('app.name'))
@section('page_title', 'Kelola Preseptor')
@section('content')
<div class="space-y-5">
    @if(session('status'))<div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-700">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $errors->first() }}</div>@endif

    <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-xs font-black uppercase tracking-widest text-cyan-700">{{ $programSite->practiceDomain?->name }}</p>
                <h2 class="mt-1 text-2xl font-black text-slate-950">{{ $programSite->practiceSite?->name }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ $programSite->program?->code }} <span class="text-slate-300">/</span> {{ $programSite->practiceSite?->city ?: 'Kota belum diisi' }}</p>
            </div>
            <a href="{{ route('management.pkpa-preceptors.index', ['practice_domain_id' => $programSite->practice_domain_id, 'program_id' => $programSite->pkpa_program_id]) }}" class="inline-flex min-h-10 items-center justify-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-bold text-slate-700">Kembali ke Daftar</a>
        </div>
    </section>

    <div class="grid gap-5 xl:grid-cols-[minmax(0,.85fr)_minmax(0,1.15fr)]">
        <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <h3 class="text-lg font-black text-slate-950">Tambahkan Preseptor</h3>
            <p class="mt-1 text-sm text-slate-600">Pilih akun dari Core. Preseptor akan tetap aktif sampai statusnya diubah manual.</p>

            <form method="POST" action="{{ route('management.pkpa-program-sites.field-supervisors.store', $programSite) }}" class="mt-5 space-y-4">
                @csrf
                <input type="hidden" name="status" value="active">
                <x-management.core-directory-picker
                    field-name="core_user_id"
                    field-label="Pilih Preseptor dari Core"
                    :search-url="route('management.core-directory.field-supervisors')"
                    placeholder="Ketik nama, email, jabatan, atau Core ID"
                    helper="Hanya akun Core aktif yang memiliki akses Preseptor MY PKPA yang ditampilkan."
                    :required="true"
                    :value="old('core_user_id')"
                />

                <div><label class="text-xs font-black uppercase tracking-widest text-slate-500">Jabatan di Tempat Praktik <span class="normal-case text-slate-400">(opsional)</span></label><input name="position_title" value="{{ old('position_title') }}" placeholder="Contoh: Apoteker Penanggung Jawab" class="mt-1 h-11 w-full rounded-lg border border-slate-300 px-3 text-sm"></div>
                <div><label class="text-xs font-black uppercase tracking-widest text-slate-500">Batas Mahasiswa Aktif <span class="normal-case text-slate-400">(opsional)</span></label><input type="number" name="maximum_active_students" min="0" value="{{ old('maximum_active_students') }}" placeholder="Kosongkan jika tidak dibatasi" class="mt-1 h-11 w-full rounded-lg border border-slate-300 px-3 text-sm"></div>
                <label class="flex items-start gap-3 rounded-lg border border-slate-200 p-3 text-sm text-slate-700"><input type="checkbox" name="is_primary_contact" value="1" class="mt-0.5" @checked(old('is_primary_contact', true))><span><span class="block font-bold text-slate-900">Kontak utama tempat praktik</span><span class="mt-0.5 block text-xs text-slate-500">Gunakan untuk preseptor yang menjadi penanggung jawab utama mahasiswa.</span></span></label>

                <button class="inline-flex min-h-11 w-full items-center justify-center rounded-lg bg-cyan-700 px-4 py-2 text-sm font-black text-white">Tambahkan Preseptor</button>
            </form>
        </section>

        <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <div class="flex items-center justify-between gap-3">
                <div><h3 class="text-lg font-black text-slate-950">Preseptor Terhubung</h3><p class="mt-1 text-sm text-slate-600">Daftar penanggung jawab untuk tempat praktik ini.</p></div>
                <span class="rounded-full bg-cyan-50 px-3 py-1 text-xs font-black text-cyan-700">{{ $programSite->practiceSite?->fieldSupervisors?->count() ?? 0 }} orang</span>
            </div>

            <div class="mt-5 space-y-4">
                @forelse($programSite->practiceSite?->fieldSupervisors ?? [] as $supervisor)
                    <article class="rounded-lg border border-slate-200 p-4">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="rounded-full px-2 py-1 text-xs font-black {{ $supervisor->status === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">{{ str($supervisor->status)->replace('_', ' ')->headline() }}</span>
                                    @if($supervisor->is_primary_contact)<span class="rounded-full bg-cyan-50 px-2 py-1 text-xs font-black text-cyan-700">Kontak utama</span>@endif
                                </div>
                                <h4 class="mt-3 font-black text-slate-950">{{ $supervisor->display_name }}</h4>
                                <p class="mt-1 break-words text-xs text-slate-500">{{ $supervisor->email_snapshot ?: '-' }} <span class="text-slate-300">/</span> Core {{ $supervisor->core_user_id }}</p>
                                <p class="mt-2 text-sm text-slate-600">{{ $supervisor->position_title ?: 'Jabatan belum diisi' }}</p>
                                <p class="mt-1 text-xs text-slate-500">Batas mahasiswa: {{ $supervisor->maximum_active_students ?: 'tidak dibatasi' }}</p>
                            </div>
                            <form method="POST" action="{{ route('management.pkpa-program-sites.field-supervisors.sync', [$programSite, $supervisor]) }}">@csrf<button class="rounded-lg border border-cyan-200 px-3 py-2 text-xs font-bold text-cyan-700">Perbarui dari Core</button></form>
                        </div>

                        <details class="mt-4 border-t border-slate-100 pt-3">
                            <summary class="cursor-pointer text-sm font-black text-slate-700">Atur ketidaktersediaan</summary>
                            <form method="POST" action="{{ route('management.pkpa-program-sites.field-supervisors.unavailability.store', [$programSite, $supervisor]) }}" class="mt-3 grid gap-3 sm:grid-cols-2">
                                @csrf
                                <div><label class="text-xs font-bold text-slate-500">Mulai</label><input type="date" name="start_date" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
                                <div><label class="text-xs font-bold text-slate-500">Selesai</label><input type="date" name="end_date" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
                                <div class="sm:col-span-2"><label class="text-xs font-bold text-slate-500">Alasan</label><input name="reason" required placeholder="Contoh: cuti atau tugas luar" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
                                <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-black text-white sm:col-span-2 sm:justify-self-start">Simpan Ketidaktersediaan</button>
                            </form>
                        </details>

                        @if($supervisor->unavailabilityPeriods->count())
                            <div class="mt-3 space-y-2 text-xs">
                                @foreach($supervisor->unavailabilityPeriods as $period)
                                    <div class="flex flex-col gap-2 rounded-lg bg-slate-50 px-3 py-2 sm:flex-row sm:items-center sm:justify-between">
                                        <span>{{ $period->start_date?->format('d M Y') }} - {{ $period->end_date?->format('d M Y') }} / {{ $period->reason }}</span>
                                        @if($period->status === 'active')<form method="POST" action="{{ route('management.pkpa-supervisor-unavailability.cancel', $period) }}">@csrf<button class="font-bold text-rose-700">Batalkan</button></form>@endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </article>
                @empty
                    <div class="rounded-lg border border-dashed border-slate-300 p-8 text-center"><p class="font-bold text-slate-700">Belum ada preseptor</p><p class="mt-1 text-sm text-slate-500">Pilih akun Core melalui formulir untuk menambahkan penanggung jawab tempat ini.</p></div>
                @endforelse
            </div>
        </section>
    </div>
</div>
@endsection
