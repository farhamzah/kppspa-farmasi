@extends('layouts.app')
@section('title', 'Penyusunan Penempatan PKPA - '.config('app.name'))
@section('page_title', 'Penyusunan Penempatan PKPA')

@php
    $assignmentByRequirement = $plan?->assignments->keyBy('pkpa_enrollment_requirement_id') ?? collect();
    $siteOptions = $programSites->groupBy('practice_domain_id');
    $internalOptions = $internalSupervisors->groupBy('practice_domain_id');
    $fieldOptions = $fieldSupervisors->groupBy('practice_site_id');
@endphp

@section('content')
<div class="space-y-5">
    @if($errors->any())
        <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700">{{ $errors->first() }}</div>
    @endif

    <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <form method="GET" class="grid gap-3 lg:grid-cols-[1.4fr_1.1fr_1fr_auto] lg:items-end">
            <div>
                <label class="text-xs font-black uppercase tracking-widest text-slate-500">Program</label>
                <select name="program_id" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Program terbaru</option>
                    @foreach($programs as $item)
                        <option value="{{ $item->id }}" @selected($program?->id === $item->id)>{{ $item->code }} - {{ $item->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-xs font-black uppercase tracking-widest text-slate-500">Rancangan</label>
                <select name="plan_id" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Current plan</option>
                    @foreach($plans as $item)
                        <option value="{{ $item->id }}" @selected($plan?->id === $item->id)>v{{ $item->version_number }} - {{ $item->name }}{{ $item->is_current ? ' (current)' : '' }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-xs font-black uppercase tracking-widest text-slate-500">Cari NPM/Nama</label>
                <input name="q" value="{{ $filters['q'] ?? '' }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" placeholder="Ketik nama atau NPM">
            </div>
            <button class="rounded-xl bg-slate-950 px-4 py-2 text-sm font-black text-white">Terapkan</button>
        </form>
    </div>

    @if(! $program)
        <div class="rounded-2xl bg-white p-8 text-center text-sm font-semibold text-slate-500 shadow-sm ring-1 ring-slate-200">Belum ada Program PKPA.</div>
    @else
        <div class="grid gap-4 xl:grid-cols-[1.2fr_.8fr]">
            <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <p class="text-xs font-black uppercase tracking-widest text-cyan-700">Program PKPA</p>
                        <h2 class="mt-1 text-2xl font-black text-slate-950">{{ $program->code }} - {{ $program->name }}</h2>
                        <p class="mt-1 text-sm text-slate-500">Rancangan internal. Belum dipublikasikan ke mahasiswa atau pembimbing.</p>
                    </div>
                    @if($plan)
                        <div class="flex flex-wrap gap-2">
                            <a href="{{ route('management.pkpa-placement-plans.timeline', $plan) }}" class="rounded-xl border border-cyan-200 px-3 py-2 text-xs font-black text-cyan-700">Timeline Rotasi</a>
                            <a href="{{ route('management.pkpa-placement-plans.export', $plan) }}" class="rounded-xl border border-emerald-200 px-3 py-2 text-xs font-black text-emerald-700">Ekspor Internal</a>
                            <form method="POST" action="{{ route('management.pkpa-placement-plans.validate', $plan) }}">@csrf<button class="rounded-xl bg-cyan-700 px-3 py-2 text-xs font-black text-white">Validasi Seluruh Rancangan</button></form>
                        </div>
                    @endif
                </div>

                @if($plan)
                    <div class="mt-5 grid gap-3 md:grid-cols-5">
                        <div class="rounded-xl bg-slate-50 p-4"><p class="text-xs font-black uppercase text-slate-500">Versi</p><p class="text-xl font-black">v{{ $plan->version_number }}</p></div>
                        <div class="rounded-xl bg-slate-50 p-4"><p class="text-xs font-black uppercase text-slate-500">Status</p><p class="text-xl font-black">{{ $plan->statusLabel() }}</p></div>
                        <div class="rounded-xl bg-slate-50 p-4"><p class="text-xs font-black uppercase text-slate-500">Terisi</p><p class="text-xl font-black">{{ $progress['filled'] }} / {{ $progress['required'] }}</p></div>
                        <div class="rounded-xl bg-slate-50 p-4"><p class="text-xs font-black uppercase text-slate-500">Valid</p><p class="text-xl font-black">{{ $progress['valid'] }}</p></div>
                        <div class="rounded-xl bg-slate-50 p-4"><p class="text-xs font-black uppercase text-slate-500">Kemajuan</p><p class="text-xl font-black">{{ $progress['percent'] }}%</p></div>
                    </div>
                @endif
            </div>

            <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <h3 class="text-base font-black text-slate-950">Versi Rancangan</h3>
                <form method="POST" action="{{ route('management.pkpa-placement-planner.plans.store') }}" class="mt-3 grid gap-2">
                    @csrf
                    <input type="hidden" name="pkpa_program_id" value="{{ $program->id }}">
                    <input name="name" class="rounded-xl border border-slate-300 px-3 py-2 text-sm" placeholder="Nama rancangan baru">
                    <button class="rounded-xl bg-slate-950 px-3 py-2 text-sm font-black text-white">Buat Rancangan</button>
                </form>
                @if($plan)
                    <div class="mt-4 flex flex-wrap gap-2">
                        <form method="POST" action="{{ route('management.pkpa-placement-plans.clone', $plan) }}">@csrf<input type="hidden" name="copy_assignments" value="1"><button class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-black text-slate-700">Clone Versi</button></form>
                        <form method="POST" action="{{ route('management.pkpa-placement-plans.current', $plan) }}">@csrf<button class="rounded-xl border border-cyan-200 px-3 py-2 text-xs font-black text-cyan-700">Jadikan Current</button></form>
                        <form method="POST" action="{{ route('management.pkpa-placement-plans.lock', $plan) }}">@csrf<button class="rounded-xl border border-amber-200 px-3 py-2 text-xs font-black text-amber-700">Kunci</button></form>
                    </div>
                @endif
            </div>
        </div>

        @if(! $plan)
            <div class="rounded-2xl bg-white p-8 text-center shadow-sm ring-1 ring-slate-200">
                <p class="text-sm font-semibold text-slate-500">Program belum memiliki rancangan penempatan.</p>
            </div>
        @else
            <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
                <section class="min-w-0 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
                    <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <h3 class="text-lg font-black text-slate-950">Matriks Mahasiswa x Enam Wahana</h3>
                            <p class="text-sm text-slate-500">Scroll horizontal hanya di area matriks. Simpan perubahan melalui tombol pada setiap sel.</p>
                        </div>
                        <div class="flex flex-wrap gap-2 text-xs font-bold">
                            <span class="rounded-full bg-slate-100 px-3 py-1 text-slate-700">Belum ditempatkan</span>
                            <span class="rounded-full bg-emerald-50 px-3 py-1 text-emerald-700">Valid</span>
                            <span class="rounded-full bg-amber-50 px-3 py-1 text-amber-700">Peringatan</span>
                            <span class="rounded-full bg-rose-50 px-3 py-1 text-rose-700">Konflik</span>
                        </div>
                    </div>

                    <div class="hidden overflow-x-auto rounded-xl border border-slate-200 lg:block" tabindex="0" aria-label="Matriks penempatan PKPA">
                        <table class="min-w-[1280px] border-collapse text-left text-sm">
                            <thead class="sticky top-0 z-10 bg-slate-50 text-xs font-black uppercase tracking-widest text-slate-500">
                                <tr>
                                    <th class="sticky left-0 z-20 w-72 border-b border-r border-slate-200 bg-slate-50 px-3 py-3">Mahasiswa</th>
                                    @foreach($domains as $domain)
                                        <th class="w-64 border-b border-slate-200 px-3 py-3">{{ $domain->practiceDomain?->name }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($enrollments as $enrollment)
                                    <tr class="align-top">
                                        <th class="sticky left-0 z-10 border-r border-t border-slate-200 bg-white px-3 py-3">
                                            <label class="flex items-start gap-2">
                                                <input type="checkbox" form="bulk-form" name="enrollment_ids[]" value="{{ $enrollment->id }}" class="mt-1 rounded border-slate-300">
                                                <span>
                                                    <span class="block font-black text-slate-950">{{ $enrollment->student_number ?: '-' }}</span>
                                                    <span class="block text-sm font-bold text-slate-700">{{ $enrollment->student_name_snapshot }}</span>
                                                    <span class="block text-xs text-slate-500">{{ $enrollment->activeGroupMembership?->group?->name ?: 'Tanpa kelompok' }} / {{ $enrollment->statusLabel() }}</span>
                                                </span>
                                            </label>
                                        </th>
                                        @foreach($domains as $domain)
                                            @php
                                                $requirement = $enrollment->requirements->firstWhere('practice_domain_id', $domain->practice_domain_id);
                                                $assignment = $requirement ? $assignmentByRequirement->get($requirement->id) : null;
                                            @endphp
                                            <td id="cell-{{ $requirement?->id }}" class="border-t border-slate-200 px-3 py-3">
                                                @include('management.pkpa-placement-planner.partials.assignment-cell', compact('assignment', 'requirement', 'domain', 'siteOptions', 'internalOptions', 'fieldOptions', 'plan'))
                                            </td>
                                        @endforeach
                                    </tr>
                                @empty
                                    <tr><td colspan="{{ $domains->count() + 1 }}" class="px-4 py-8 text-center text-sm text-slate-500">Belum ada peserta sesuai filter.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="grid gap-3 lg:hidden">
                        @foreach($enrollments as $enrollment)
                            <article class="rounded-2xl border border-slate-200 p-4">
                                <label class="flex items-start gap-2">
                                    <input type="checkbox" form="bulk-form" name="enrollment_ids[]" value="{{ $enrollment->id }}" class="mt-1 rounded border-slate-300">
                                    <span>
                                        <span class="block font-black text-slate-950">{{ $enrollment->student_name_snapshot }}</span>
                                        <span class="block text-xs text-slate-500">{{ $enrollment->student_number }} / {{ $enrollment->activeGroupMembership?->group?->name ?: 'Tanpa kelompok' }}</span>
                                    </span>
                                </label>
                                <div class="mt-3 grid gap-3">
                                    @foreach($domains as $domain)
                                        @php
                                            $requirement = $enrollment->requirements->firstWhere('practice_domain_id', $domain->practice_domain_id);
                                            $assignment = $requirement ? $assignmentByRequirement->get($requirement->id) : null;
                                        @endphp
                                        <div class="rounded-xl bg-slate-50 p-3">
                                            <p class="text-xs font-black uppercase text-slate-500">{{ $domain->practiceDomain?->name }}</p>
                                            @include('management.pkpa-placement-planner.partials.assignment-cell', compact('assignment', 'requirement', 'domain', 'siteOptions', 'internalOptions', 'fieldOptions', 'plan'))
                                        </div>
                                    @endforeach
                                </div>
                            </article>
                        @endforeach
                    </div>
                    <div class="mt-4">{{ $enrollments->links() }}</div>
                </section>

                <aside class="space-y-4">
                    <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                        <h3 class="text-base font-black text-slate-950">Bulk Placement</h3>
                        <form id="bulk-form" method="POST" action="{{ route('management.pkpa-placement-plans.bulk.preview', $plan) }}" class="mt-3 grid gap-3">
                            @csrf
                            <select name="practice_domain_id" class="rounded-xl border border-slate-300 px-3 py-2 text-sm" required>
                                <option value="">Pilih wahana</option>
                                @foreach($domains as $domain)<option value="{{ $domain->practice_domain_id }}">{{ $domain->practiceDomain?->name }}</option>@endforeach
                            </select>
                            <select name="pkpa_program_site_id" class="rounded-xl border border-slate-300 px-3 py-2 text-sm" required>
                                <option value="">Pilih tempat</option>
                                @foreach($programSites as $site)<option value="{{ $site->id }}">{{ $site->practiceDomain?->name }} - {{ $site->practiceSite?->name }}</option>@endforeach
                            </select>
                            <select name="pkpa_site_availability_period_id" class="rounded-xl border border-slate-300 px-3 py-2 text-sm" required>
                                <option value="">Pilih availability</option>
                                @foreach($programSites as $site)
                                    @foreach($site->availabilityPeriods as $period)
                                        <option value="{{ $period->id }}">{{ $site->practiceSite?->name }} / {{ $period->start_date->format('d M Y') }} - {{ $period->end_date->format('d M Y') }}</option>
                                    @endforeach
                                @endforeach
                            </select>
                            <div class="grid grid-cols-2 gap-2"><input type="date" name="start_date" class="rounded-xl border border-slate-300 px-3 py-2 text-sm" required><input type="date" name="end_date" class="rounded-xl border border-slate-300 px-3 py-2 text-sm" required></div>
                            <select name="internal_supervisor_eligibility_id" class="rounded-xl border border-slate-300 px-3 py-2 text-sm" required><option value="">Pembimbing Dalam</option>@foreach($internalSupervisors as $supervisor)<option value="{{ $supervisor->id }}">{{ $supervisor->name_snapshot }} - {{ $supervisor->practiceDomain?->name }}</option>@endforeach</select>
                            <select name="site_field_supervisor_id" class="rounded-xl border border-slate-300 px-3 py-2 text-sm" required><option value="">Pembimbing Lapangan</option>@foreach($fieldSupervisors as $supervisor)<option value="{{ $supervisor->id }}">{{ $supervisor->name_snapshot }} - {{ $supervisor->practiceSite?->name }}</option>@endforeach</select>
                            <select name="overwrite_mode" class="rounded-xl border border-slate-300 px-3 py-2 text-sm"><option value="empty_only">Hanya isi yang kosong</option><option value="overwrite_draft">Timpa assignment draft</option></select>
                            <button class="rounded-xl bg-slate-950 px-3 py-2 text-sm font-black text-white">Pratinjau Massal</button>
                        </form>
                    </section>

                    @if($latestBatch)
                        <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                            <h3 class="text-base font-black text-slate-950">Pratinjau Terakhir</h3>
                            <p class="mt-1 text-sm text-slate-500">{{ $latestBatch->items->where('result_status', 'valid')->count() }} valid / {{ $latestBatch->items->where('result_status', 'invalid')->count() }} invalid</p>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <form method="POST" action="{{ route('management.pkpa-placement-batches.apply', $latestBatch) }}">@csrf<button class="rounded-xl bg-cyan-700 px-3 py-2 text-xs font-black text-white">Terapkan Semua Jika Valid</button></form>
                                <form method="POST" action="{{ route('management.pkpa-placement-batches.apply', $latestBatch) }}">@csrf<input type="hidden" name="valid_only" value="1"><button class="rounded-xl border border-cyan-200 px-3 py-2 text-xs font-black text-cyan-700">Terapkan Baris Valid</button></form>
                                <form method="POST" action="{{ route('management.pkpa-placement-batches.undo', $latestBatch) }}">@csrf<button class="rounded-xl border border-rose-200 px-3 py-2 text-xs font-black text-rose-700">Batalkan Aksi</button></form>
                            </div>
                        </section>
                    @endif

                    <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                        <h3 class="text-base font-black text-slate-950">Masalah Validasi</h3>
                        @if($latestRun)
                            <p class="mt-1 text-sm text-slate-500">{{ $latestRun->error_count }} error / {{ $latestRun->warning_count }} peringatan</p>
                            <div class="mt-3 max-h-96 space-y-2 overflow-y-auto pr-1">
                                @forelse($latestRun->issues as $issue)
                                    <a href="#cell-{{ $issue->pkpa_enrollment_requirement_id }}" class="block rounded-xl border border-slate-200 p-3 text-sm hover:bg-slate-50">
                                        <span class="font-black {{ $issue->severity === 'error' ? 'text-rose-700' : 'text-amber-700' }}">{{ strtoupper($issue->severity) }} - {{ $issue->issue_code }}</span>
                                        <span class="mt-1 block text-slate-700">{{ $issue->message }}</span>
                                        <span class="mt-1 block text-xs text-slate-500">{{ $issue->suggested_action }}</span>
                                    </a>
                                @empty
                                    <p class="text-sm text-slate-500">Tidak ada masalah aktif.</p>
                                @endforelse
                            </div>
                        @else
                            <p class="mt-2 text-sm text-slate-500">Belum ada validasi untuk rancangan ini.</p>
                        @endif
                    </section>
                </aside>
            </div>
        @endif
    @endif
</div>
@endsection
