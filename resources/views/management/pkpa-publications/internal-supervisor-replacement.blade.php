@extends('layouts.app')
@section('title', 'Ganti Pembimbing Dalam - '.config('app.name'))
@section('page_title', 'Ganti Pembimbing Dalam')

@section('content')
@php
    $groups = $publication->assignments->groupBy(function ($assignment) {
        $internal = $assignment->supervisors->firstWhere('supervisor_type', 'internal');
        return ($assignment->practice_domain_name_snapshot ?: 'Wahana lainnya').'|'.($internal?->core_user_id ?: 'tanpa-pembimbing');
    });
@endphp
<div class="space-y-5">
    @if($errors->any())
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-bold text-rose-800">{{ $errors->first() }}</div>
    @endif

    <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <p class="text-xs font-black uppercase tracking-widest text-cyan-700">Revisi jadwal resmi</p>
        <div class="mt-2 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <h2 class="text-2xl font-black text-slate-950">Alihkan mahasiswa ke dosen pengganti</h2>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">Persetujuan, komentar, dan penilaian yang sudah dibuat dosen lama tidak dihapus. Form nilai yang masih kosong langsung dialihkan; nilai draf yang sudah berisi diteruskan kepada dosen baru untuk ditinjau sebelum dikirim.</p>
            </div>
            <a href="{{ route('management.pkpa-publications.show', $publication) }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-200 px-4 py-2 text-sm font-bold text-slate-700">Kembali</a>
        </div>
    </section>

    <form method="POST" action="{{ route('management.pkpa-internal-supervisor-replacements.store', $publication) }}" class="space-y-5">
        @csrf
        <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <div class="grid gap-4 lg:grid-cols-2">
                <label class="block">
                    <span class="text-xs font-black uppercase tracking-widest text-slate-500">Dosen pengganti</span>
                    <select name="replacement_core_user_id" class="mt-2 min-h-12 w-full rounded-xl border border-slate-300 px-3 text-sm" required>
                        <option value="">Pilih Pembimbing Dalam</option>
                        @foreach($internalSupervisors as $supervisor)
                            <option value="{{ $supervisor['core_user_id'] }}" @selected(old('replacement_core_user_id') === $supervisor['core_user_id'])>
                                {{ $supervisor['name'] }} · {{ implode(', ', $supervisor['domains']) }}
                            </option>
                        @endforeach
                    </select>
                    <span class="mt-2 block text-xs text-slate-500">Dosen harus aktif pada setiap wahana mahasiswa yang dipilih.</span>
                </label>
                <label class="block">
                    <span class="text-xs font-black uppercase tracking-widest text-slate-500">Tanggal serah terima</span>
                    <input type="date" name="effective_date" value="{{ old('effective_date', now()->toDateString()) }}" class="mt-2 min-h-12 w-full rounded-xl border border-slate-300 px-3 text-sm" required>
                    <span class="mt-2 block text-xs text-slate-500">Untuk wahana yang belum dimulai, sistem otomatis memakai tanggal mulai wahana.</span>
                </label>
                <label class="block lg:col-span-2">
                    <span class="text-xs font-black uppercase tracking-widest text-slate-500">Alasan penggantian</span>
                    <textarea name="reason" rows="3" class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" placeholder="Contoh: pembimbing lama mengundurkan diri dan dilakukan serah terima kepada dosen pengganti" required>{{ old('reason') }}</textarea>
                </label>
            </div>
        </section>

        @foreach($groups as $groupKey => $assignments)
            @php
                $firstAssignment = $assignments->first();
                $domainName = $firstAssignment?->practice_domain_name_snapshot ?: 'Wahana lainnya';
                $groupSupervisor = $firstAssignment?->supervisors->firstWhere('supervisor_type', 'internal');
                $domainKey = 'group-'.md5($groupKey);
            @endphp
            <section class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
                <div class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-xs font-black uppercase tracking-widest text-cyan-700">Wahana PKPA</p>
                        <h3 class="mt-1 text-xl font-black text-slate-950">{{ $domainName }}</h3>
                        <p class="mt-1 text-sm text-slate-500">Pembimbing saat ini: <span class="font-bold text-slate-700">{{ $groupSupervisor?->display_name ?: 'Belum ditentukan' }}</span> · {{ $assignments->count() }} mahasiswa</p>
                    </div>
                    <button type="button" data-select-domain="{{ $domainKey }}" class="min-h-10 rounded-xl border border-cyan-200 px-4 py-2 text-sm font-bold text-cyan-700">Pilih semua bimbingan</button>
                </div>
                <div class="divide-y divide-slate-100">
                    @foreach($assignments as $assignment)
                        @php
                            $current = $assignment->supervisors->firstWhere('supervisor_type', 'internal');
                            $ongoing = $assignment->start_date?->lte(today()) && $assignment->end_date?->gte(today());
                            $upcoming = $assignment->start_date?->gt(today());
                            $finished = $assignment->end_date?->lt(today());
                            $hasPartialAssessment = $partialAssessmentRequirementIds->contains($assignment->pkpa_enrollment_requirement_id);
                        @endphp
                        <label class="grid cursor-pointer gap-3 px-5 py-4 hover:bg-slate-50 md:grid-cols-[32px_1.3fr_1fr_1fr_auto] md:items-center">
                            <input type="checkbox" name="assignment_ids[]" value="{{ $assignment->id }}" data-domain="{{ $domainKey }}" class="h-5 w-5 rounded border-slate-300 text-cyan-700" @checked(in_array($assignment->id, old('assignment_ids', []))) @disabled($finished)>
                            <span>
                                <span class="block font-black text-slate-950">{{ $assignment->student_name_snapshot }}</span>
                                <span class="mt-1 block text-xs text-slate-500">{{ $assignment->student_number_snapshot }}</span>
                            </span>
                            <span>
                                <span class="block text-xs font-bold uppercase text-slate-500">Tempat</span>
                                <span class="mt-1 block text-sm font-semibold text-slate-800">{{ $assignment->practice_site_name_snapshot }}</span>
                            </span>
                            <span>
                                <span class="block text-xs font-bold uppercase text-slate-500">Pembimbing saat ini</span>
                                <span class="mt-1 block text-sm font-semibold text-slate-800">{{ $current?->display_name ?: '-' }}</span>
                            </span>
                            <span class="justify-self-start rounded-full px-3 py-1 text-xs font-bold {{ $ongoing ? 'bg-amber-50 text-amber-700' : ($upcoming ? 'bg-sky-50 text-sky-700' : 'bg-slate-100 text-slate-600') }}">
                                {{ $ongoing ? 'Sedang berjalan' : ($upcoming ? 'Belum mulai' : 'Selesai') }}
                                @if($hasPartialAssessment)<span class="mt-1 block">Nilai draf dialihkan</span>@endif
                            </span>
                        </label>
                    @endforeach
                </div>
            </section>
        @endforeach

        <section class="sticky bottom-4 rounded-2xl border border-cyan-100 bg-white p-4 shadow-lg shadow-cyan-950/10">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <label class="flex items-start gap-3 text-sm text-slate-700">
                    <input type="checkbox" name="confirmation" value="1" class="mt-0.5 h-5 w-5 rounded border-slate-300 text-cyan-700" required>
                    <span>Saya sudah memeriksa mahasiswa, dosen pengganti, dan tanggal serah terima. Saya memahami nilai draf akan diteruskan kepada dosen baru untuk ditinjau, bukan dianggap sebagai nilai final.</span>
                </label>
                <button class="min-h-12 rounded-xl bg-cyan-700 px-6 py-3 text-sm font-black text-white">Tinjau Penggantian</button>
            </div>
        </section>
    </form>
</div>

@push('scripts')
<script>
document.querySelectorAll('[data-select-domain]').forEach((button) => {
    button.addEventListener('click', () => {
        const checkboxes = [...document.querySelectorAll(`[data-domain="${button.dataset.selectDomain}"]`)]
            .filter((checkbox) => !checkbox.disabled);
        const shouldCheck = checkboxes.some((checkbox) => !checkbox.checked);
        checkboxes.forEach((checkbox) => checkbox.checked = shouldCheck);
        button.textContent = shouldCheck ? 'Batalkan pilihan' : 'Pilih semua bimbingan';
    });
});
</script>
@endpush
@endsection
