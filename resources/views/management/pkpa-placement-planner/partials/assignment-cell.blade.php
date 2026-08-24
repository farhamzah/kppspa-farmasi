@if(! $requirement)
    <div class="rounded-xl bg-rose-50 p-3 text-xs font-bold text-rose-700">Requirement tidak ditemukan</div>
@else
    @php
        $badge = $assignment?->validation_status === 'error' ? 'bg-rose-50 text-rose-700 ring-rose-100' : ($assignment?->validation_status === 'warning' ? 'bg-amber-50 text-amber-700 ring-amber-100' : ($assignment ? 'bg-emerald-50 text-emerald-700 ring-emerald-100' : 'bg-slate-100 text-slate-600 ring-slate-200'));
    @endphp
    <div class="space-y-2">
        <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-black ring-1 {{ $badge }}" aria-label="Status penempatan">
            {{ $assignment?->statusLabel() ?? 'Belum ditempatkan' }}
        </span>
        @if($assignment)
            <div class="text-xs leading-relaxed text-slate-700">
                <p class="font-black text-slate-950">{{ $assignment->practiceSite?->name }}</p>
                <p>{{ $assignment->start_date?->format('d M Y') }} - {{ $assignment->end_date?->format('d M Y') }}</p>
                @if($assignment->selectedOption)<p>{{ $assignment->practiceDomain?->name }} / {{ $assignment->selectedOption?->name }}</p>@endif
                <p>PD: {{ $assignment->supervisors->firstWhere('supervisor_type', 'internal')?->name_snapshot ?: '-' }}</p>
                <p>PL: {{ $assignment->supervisors->firstWhere('supervisor_type', 'field')?->name_snapshot ?: '-' }}</p>
            </div>
        @else
            <p class="text-xs text-slate-500">Belum ditempatkan</p>
        @endif

        <details class="rounded-xl border border-slate-200 bg-white p-2">
            <summary class="cursor-pointer text-xs font-black text-cyan-700">Isi / Ubah</summary>
            <form method="POST" action="{{ route('management.pkpa-placement-plans.assignments.store', $plan) }}" class="mt-3 grid gap-2" data-placement-assignment-form>
                @csrf
                <input type="hidden" name="pkpa_enrollment_requirement_id" value="{{ $requirement->id }}">
                <input type="hidden" name="row_version" value="{{ $assignment?->row_version }}">
                <select name="pkpa_program_site_id" class="w-full rounded-lg border border-slate-300 px-2 py-2 text-xs" data-program-site-select required>
                    <option value="">Tempat Praktik</option>
                    @foreach(($siteOptions[$requirement->practice_domain_id] ?? collect()) as $site)
                        <option value="{{ $site->id }}" data-practice-site-id="{{ $site->practice_site_id }}" @selected($assignment?->pkpa_program_site_id === $site->id)>{{ $site->practiceSite?->name }}{{ $site->practiceDomainOption ? ' / '.$site->practiceDomainOption->name : '' }}</option>
                    @endforeach
                </select>
                <select name="pkpa_site_availability_period_id" class="w-full rounded-lg border border-slate-300 px-2 py-2 text-xs" data-availability-select required>
                    <option value="">Availability</option>
                    @foreach(($siteOptions[$requirement->practice_domain_id] ?? collect()) as $site)
                        @foreach($site->availabilityPeriods as $period)
                            <option value="{{ $period->id }}" data-program-site-id="{{ $site->id }}" data-start-date="{{ $period->start_date?->toDateString() }}" data-end-date="{{ $period->end_date?->toDateString() }}" @selected($assignment?->pkpa_site_availability_period_id === $period->id)>{{ $site->practiceSite?->name }} / {{ $period->start_date->format('d M') }} - {{ $period->end_date->format('d M Y') }} / {{ $period->plannedAvailableSlots() }} slot</option>
                        @endforeach
                    @endforeach
                </select>
                <div class="grid grid-cols-2 gap-2">
                    <input type="date" name="start_date" value="{{ $assignment?->start_date?->toDateString() }}" class="rounded-lg border border-slate-300 px-2 py-2 text-xs" data-start-date-input required>
                    <input type="date" name="end_date" value="{{ $assignment?->end_date?->toDateString() }}" class="rounded-lg border border-slate-300 px-2 py-2 text-xs" data-end-date-input required>
                </div>
                <select name="internal_supervisor_eligibility_id" class="w-full rounded-lg border border-slate-300 px-2 py-2 text-xs" required>
                    <option value="">Pembimbing Dalam</option>
                    @foreach(($internalOptions[$requirement->practice_domain_id] ?? collect()) as $supervisor)
                        <option value="{{ $supervisor->id }}" @selected($assignment?->supervisors->firstWhere('supervisor_type', 'internal')?->internal_supervisor_eligibility_id === $supervisor->id)>{{ $supervisor->name_snapshot }} / max {{ $supervisor->maximum_active_students ?? '?' }}</option>
                    @endforeach
                </select>
                <select name="site_field_supervisor_id" class="w-full rounded-lg border border-slate-300 px-2 py-2 text-xs" data-field-supervisor-select required>
                    <option value="">Pembimbing Lapangan</option>
                    @foreach(($siteOptions[$requirement->practice_domain_id] ?? collect()) as $site)
                        @foreach(($fieldOptions[$site->practice_site_id] ?? collect()) as $supervisor)
                            <option value="{{ $supervisor->id }}" data-practice-site-id="{{ $site->practice_site_id }}" @selected($assignment?->supervisors->firstWhere('supervisor_type', 'field')?->site_field_supervisor_id === $supervisor->id)>{{ $site->practiceSite?->name }} - {{ $supervisor->name_snapshot }} / max {{ $supervisor->maximum_active_students ?? '?' }}</option>
                        @endforeach
                    @endforeach
                </select>
                <p class="text-[11px] text-slate-500">Setelah tempat dipilih, availability dan pembimbing lapangan akan otomatis disaring ke tempat tersebut.</p>
                <textarea name="notes" class="rounded-lg border border-slate-300 px-2 py-2 text-xs" rows="2" placeholder="Catatan">{{ $assignment?->notes }}</textarea>
                <div class="flex gap-2">
                    <button class="flex-1 rounded-lg bg-cyan-700 px-2 py-2 text-xs font-black text-white">Simpan</button>
                    @if($assignment)
                        <button form="delete-assignment-{{ $assignment->id }}" class="rounded-lg border border-rose-200 px-2 py-2 text-xs font-black text-rose-700">Hapus</button>
                    @endif
                </div>
            </form>
            @if($assignment)
                <form id="delete-assignment-{{ $assignment->id }}" method="POST" action="{{ route('management.pkpa-rotation-assignments.destroy', $assignment) }}">
                    @csrf
                    @method('DELETE')
                </form>
            @endif
        </details>
    </div>
@endif
