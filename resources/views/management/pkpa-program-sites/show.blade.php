@extends('layouts.app')
@section('title', 'Kelola Tempat Tersedia - '.config('app.name'))
@section('page_title', 'Kelola Tempat Tersedia')
@section('content')
@php
    $dayLabels = ['monday' => 'Senin', 'tuesday' => 'Selasa', 'wednesday' => 'Rabu', 'thursday' => 'Kamis', 'friday' => 'Jumat', 'saturday' => 'Sabtu', 'sunday' => 'Minggu'];
@endphp
<div class="space-y-5">
    @if(session('status'))<div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-700">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $errors->first() }}</div>@endif

    <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="rounded-full bg-cyan-50 px-2 py-1 text-xs font-black text-cyan-700">{{ $programSite->statusLabel() }}</span>
                    <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-black text-slate-600">{{ $programSite->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                </div>
                <h2 class="mt-3 text-2xl font-black text-slate-950">{{ $programSite->practiceSite?->name }}</h2>
                <p class="text-sm text-slate-500">{{ $programSite->program?->code }} - {{ $programSite->program?->name }}</p>
                <p class="mt-2 text-sm text-slate-600">{{ $programSite->practiceDomain?->name }}{{ $programSite->practiceDomainOption ? ' / '.$programSite->practiceDomainOption->name : '' }} - {{ $programSite->practiceSite?->city ?: 'Kota belum diisi' }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('management.pkpa-placement-readiness.index', ['program_id' => $programSite->pkpa_program_id]) }}" class="rounded-xl border border-cyan-200 px-4 py-2 text-sm font-black text-cyan-700">Cek Kesiapan</a>
                @if($programSite->is_active)
                    <form method="POST" action="{{ route('management.pkpa-program-sites.deactivate', $programSite) }}">@csrf<button class="rounded-xl border border-rose-200 px-4 py-2 text-sm font-bold text-rose-700">Nonaktifkan</button></form>
                @endif
            </div>
        </div>
        <div class="mt-6 grid gap-3 md:grid-cols-4">
            <div class="rounded-xl bg-slate-50 p-4"><p class="text-xs font-black uppercase text-slate-500">Kerja Sama</p><p class="mt-1 font-bold">{{ $programSite->practiceSite?->cooperation_start_date?->format('d M Y') ?: '-' }} - {{ $programSite->practiceSite?->cooperation_end_date?->format('d M Y') ?: '-' }}</p></div>
            <div class="rounded-xl bg-slate-50 p-4"><p class="text-xs font-black uppercase text-slate-500">Periode Availability</p><p class="mt-1 font-bold">{{ $programSite->availabilityPeriods->count() }}</p></div>
            <div class="rounded-xl bg-slate-50 p-4"><p class="text-xs font-black uppercase text-slate-500">Kapasitas Rencana</p><p class="mt-1 font-bold">{{ $programSite->availabilityPeriods->whereIn('status', ['available', 'full'])->sum('maximum_students') }}</p></div>
            <div class="rounded-xl bg-slate-50 p-4"><p class="text-xs font-black uppercase text-slate-500">Pembimbing Lapangan</p><p class="mt-1 font-bold">{{ $programSite->practiceSite?->fieldSupervisors?->where('status', 'active')->count() ?? 0 }}</p></div>
        </div>
    </div>

    <div class="grid gap-5 xl:grid-cols-[1.2fr_.8fr]">
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <div class="flex items-center justify-between gap-3">
                <h3 class="text-lg font-black text-slate-950">Availability Tempat</h3>
                <span class="text-xs font-bold text-slate-500">Belum membuat penempatan mahasiswa</span>
            </div>
            <form method="POST" action="{{ route('management.pkpa-program-sites.availability.store', $programSite) }}" class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                @csrf
                <div><label class="text-xs font-black uppercase tracking-widest text-slate-500">Mulai</label><input type="date" name="start_date" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"></div>
                <div><label class="text-xs font-black uppercase tracking-widest text-slate-500">Selesai</label><input type="date" name="end_date" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"></div>
                <div><label class="text-xs font-black uppercase tracking-widest text-slate-500">Min</label><input type="number" min="0" name="minimum_students" value="{{ $programSite->default_minimum_students ?? 0 }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"></div>
                <div><label class="text-xs font-black uppercase tracking-widest text-slate-500">Maks</label><input type="number" min="1" name="maximum_students" value="{{ $programSite->default_maximum_students ?? 1 }}" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"></div>
                <div><label class="text-xs font-black uppercase tracking-widest text-slate-500">Slot Cadangan</label><input type="number" min="0" name="reserved_slots" value="0" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"></div>
                <div><label class="text-xs font-black uppercase tracking-widest text-slate-500">Jam Mulai</label><input type="time" name="daily_start_time" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"></div>
                <div><label class="text-xs font-black uppercase tracking-widest text-slate-500">Jam Selesai</label><input type="time" name="daily_end_time" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"></div>
                <div><label class="text-xs font-black uppercase tracking-widest text-slate-500">Status</label><select name="status" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">@foreach(\App\Models\PkpaSiteAvailabilityPeriod::STATUSES as $status)<option value="{{ $status }}" @selected($status === 'available')>{{ str($status)->headline() }}</option>@endforeach</select></div>
                <div class="md:col-span-2 xl:col-span-4">
                    <p class="mb-2 text-xs font-black uppercase tracking-widest text-slate-500">Hari Operasional</p>
                    <div class="flex flex-wrap gap-2">@foreach($dayLabels as $key => $label)<label class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-bold text-slate-700"><input type="checkbox" name="operational_days[]" value="{{ $key }}" class="mr-1" @checked(in_array($key, ['monday','tuesday','wednesday','thursday','friday'], true))> {{ $label }}</label>@endforeach</div>
                </div>
                <div class="md:col-span-2 xl:col-span-4"><label class="text-xs font-black uppercase tracking-widest text-slate-500">Catatan</label><textarea name="notes" rows="2" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"></textarea></div>
                <div class="md:col-span-2 xl:col-span-4"><button class="rounded-xl bg-cyan-700 px-4 py-2 text-sm font-black text-white">Tambah Availability</button></div>
            </form>
            <div class="mt-5 overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-black uppercase tracking-widest text-slate-500"><tr><th class="px-3 py-3">Periode</th><th class="px-3 py-3">Kapasitas</th><th class="px-3 py-3">Hari/Jam</th><th class="px-3 py-3">Status</th><th class="px-3 py-3 text-right">Aksi</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                    @forelse($programSite->availabilityPeriods as $period)
                        <tr>
                            <td class="px-3 py-3 font-bold">{{ $period->start_date?->format('d M Y') }} - {{ $period->end_date?->format('d M Y') }}</td>
                            <td class="px-3 py-3">{{ $period->minimum_students }}-{{ $period->maximum_students }}<div class="text-xs text-slate-500">{{ $period->reserved_slots }} cadangan</div></td>
                            <td class="px-3 py-3">{{ collect($period->operational_days ?? [])->map(fn ($day) => $dayLabels[$day] ?? $day)->join(', ') ?: '-' }}<div class="text-xs text-slate-500">{{ $period->daily_start_time ?: '-' }} - {{ $period->daily_end_time ?: '-' }}</div></td>
                            <td class="px-3 py-3">{{ str($period->status)->headline() }}</td>
                            <td class="px-3 py-3 text-right">@if($period->status !== 'cancelled')<form method="POST" action="{{ route('management.pkpa-program-sites.availability.cancel', [$programSite, $period]) }}">@csrf<button class="rounded-lg border border-rose-200 px-3 py-1.5 text-xs font-bold text-rose-700">Batalkan</button></form>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-3 py-8 text-center text-slate-500">Belum ada availability.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <h3 class="text-lg font-black text-slate-950">Tambah Pembimbing Lapangan</h3>
            <form method="POST" action="{{ route('management.pkpa-program-sites.field-supervisors.store', $programSite) }}" class="mt-4 space-y-3">
                @csrf
                <div><label class="text-xs font-black uppercase tracking-widest text-slate-500">Core User ID</label><input name="core_user_id" required placeholder="CORE-FIELD-001" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"></div>
                <div><label class="text-xs font-black uppercase tracking-widest text-slate-500">Jabatan</label><input name="position_title" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"></div>
                <div class="grid gap-3 md:grid-cols-2">
                    <div><label class="text-xs font-black uppercase tracking-widest text-slate-500">Beban Maks</label><input type="number" name="maximum_active_students" min="0" value="0" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"></div>
                    <div><label class="text-xs font-black uppercase tracking-widest text-slate-500">Status</label><select name="status" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">@foreach(\App\Models\PkpaSiteFieldSupervisor::STATUSES as $status)<option value="{{ $status }}" @selected($status === 'active')>{{ str($status)->headline() }}</option>@endforeach</select></div>
                </div>
                <div class="grid gap-3 md:grid-cols-2">
                    <div><label class="text-xs font-black uppercase tracking-widest text-slate-500">Efektif Mulai</label><input type="date" name="effective_start_date" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"></div>
                    <div><label class="text-xs font-black uppercase tracking-widest text-slate-500">Efektif Selesai</label><input type="date" name="effective_end_date" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"></div>
                </div>
                <label class="flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm font-bold text-slate-700"><input type="checkbox" name="is_primary_contact" value="1"> Kontak utama</label>
                <button class="rounded-xl bg-cyan-700 px-4 py-2 text-sm font-black text-white">Tambah dari Core</button>
            </form>
        </div>
    </div>

    <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <h3 class="text-lg font-black text-slate-950">Pembimbing Lapangan Terhubung</h3>
        <div class="mt-4 grid gap-4 lg:grid-cols-2">
            @forelse($programSite->practiceSite?->fieldSupervisors ?? [] as $supervisor)
                <div class="rounded-xl border border-slate-200 p-4">
                    <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                        <div>
                            <p class="font-black text-slate-950">{{ $supervisor->name_snapshot ?: $supervisor->core_user_id }}</p>
                            <p class="text-xs text-slate-500">{{ $supervisor->core_user_id }} / {{ $supervisor->email_snapshot ?: '-' }}</p>
                            <p class="mt-2 text-sm text-slate-600">{{ $supervisor->position_title ?: 'Jabatan belum diisi' }} - beban maks {{ $supervisor->maximum_active_students ?: 'tidak dibatasi' }}</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <form method="POST" action="{{ route('management.pkpa-program-sites.field-supervisors.sync', [$programSite, $supervisor]) }}">@csrf<button class="rounded-lg border border-cyan-200 px-3 py-1.5 text-xs font-bold text-cyan-700">Sinkronkan</button></form>
                            <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-black text-slate-600">{{ str($supervisor->status)->headline() }}</span>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('management.pkpa-program-sites.field-supervisors.unavailability.store', [$programSite, $supervisor]) }}" class="mt-4 grid gap-3 md:grid-cols-[1fr_1fr_1.4fr_auto]">
                        @csrf
                        <input type="date" name="start_date" required class="rounded-xl border border-slate-300 px-3 py-2 text-sm">
                        <input type="date" name="end_date" required class="rounded-xl border border-slate-300 px-3 py-2 text-sm">
                        <input name="reason" required placeholder="Alasan tidak tersedia" class="rounded-xl border border-slate-300 px-3 py-2 text-sm">
                        <button class="rounded-xl border border-slate-300 px-3 py-2 text-xs font-black">Blokir</button>
                    </form>
                    @if($supervisor->unavailabilityPeriods->count())
                        <div class="mt-3 space-y-2 text-xs">
                            @foreach($supervisor->unavailabilityPeriods as $period)
                                <div class="flex items-center justify-between gap-2 rounded-lg bg-slate-50 px-3 py-2">
                                    <span>{{ $period->start_date?->format('d M Y') }} - {{ $period->end_date?->format('d M Y') }} / {{ $period->reason }}</span>
                                    @if($period->status === 'active')<form method="POST" action="{{ route('management.pkpa-supervisor-unavailability.cancel', $period) }}">@csrf<button class="font-bold text-rose-700">Batalkan</button></form>@endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @empty
                <div class="rounded-xl border border-dashed border-slate-300 p-6 text-sm text-slate-500">Belum ada pembimbing lapangan dari Core untuk tempat ini.</div>
            @endforelse
        </div>
    </div>
</div>
@endsection
