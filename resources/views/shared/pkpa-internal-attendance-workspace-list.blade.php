@php
    $attendanceGroups = $attendances->getCollection()->groupBy(fn ($record) => $record->rotationRun?->practice_domain_id ?: 'lainnya');
    $attendanceTypes = ['present' => 'Hadir', 'sick' => 'Sakit', 'permit' => 'Izin', 'institution_closed' => 'Tempat tutup'];
    $attendanceLabels = ['submitted' => 'Perlu diperiksa', 'approved' => 'Disetujui', 'revision_requested' => 'Perlu revisi', 'rejected' => 'Ditolak'];
@endphp
<div class="mt-4 space-y-5">
    @forelse($attendanceGroups as $domainId => $domainRecords)
        <x-pkpa.domain-group :name="$domainRecords->first()->rotationRun?->practiceDomain?->name ?? 'Wahana lainnya'" :count="$domainRecords->count()" :anchor="'presensi-'.$domainId">
            @foreach($domainRecords->groupBy('pkpa_rotation_run_id') as $runId => $records)
                @php($attendanceRun = $records->first()->rotationRun)
                <section class="mb-4 border-y border-slate-200 bg-white">
                    <header class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-slate-50 p-4">
                        <div class="min-w-0"><h3 class="font-bold">{{ $attendanceRun->studentDisplayName() }}</h3><p class="mt-1 text-sm text-slate-600">{{ $attendanceRun->studentDisplaySecondary() }} · {{ $attendanceRun->practiceSite?->name }}</p></div>
                        @if($records->contains('submission_status', 'submitted'))
                            <label class="flex min-h-11 cursor-pointer items-center gap-2 text-sm font-semibold text-cyan-800"><input type="checkbox" data-bulk-group-select="{{ $runId }}" class="h-5 w-5 accent-cyan-700">Pilih presensi mahasiswa ini</label>
                        @endif
                    </header>
                    <div class="divide-y divide-slate-100">
                        @foreach($records as $record)
                            <article class="p-4 has-[:checked]:bg-cyan-50/60">
                                <div class="flex flex-wrap items-center gap-3">
                                    @if($record->submission_status === 'submitted')
                                        <input type="checkbox" name="ids[]" value="{{ $record->id }}" form="bulk-validation" data-bulk-group="{{ $runId }}" aria-label="Pilih presensi {{ $attendanceRun->studentDisplayName() }} {{ $record->attendance_date?->format('d M Y') }}" class="h-5 w-5 shrink-0 accent-cyan-700">
                                    @endif
                                    <div class="min-w-0 flex-1 basis-[160px]"><p class="font-bold">{{ $record->attendance_date?->translatedFormat('d M Y') }} · {{ $attendanceTypes[$record->attendance_type] ?? $record->attendance_type }}</p><p class="mt-1 text-sm text-slate-600">{{ $record->check_in_time ? substr($record->check_in_time, 0, 5) : '-' }}–{{ $record->check_out_time ? substr($record->check_out_time, 0, 5) : '-' }} @if($record->calculated_minutes !== null) · {{ $record->calculated_minutes }} menit @endif</p></div>
                                    <span class="rounded-full px-3 py-1 text-xs font-bold {{ $record->submission_status === 'approved' ? 'bg-emerald-50 text-emerald-800' : 'bg-amber-50 text-amber-800' }}">{{ $attendanceLabels[$record->submission_status] ?? $record->submission_status }}</span>
                                </div>
                                <details class="mt-3">
                                    <summary class="cursor-pointer text-sm font-semibold text-cyan-800">{{ $record->submission_status === 'submitted' ? 'Periksa & Beri Keputusan' : 'Lihat catatan pemeriksaan' }}</summary>
                                    <p class="mt-3 whitespace-pre-line break-words text-sm text-slate-600">{{ $record->student_notes ?: 'Tidak ada catatan mahasiswa.' }}</p>
                                    @if($record->submission_status === 'submitted')
                                        <form method="POST" action="{{ route('internal-supervisor.pkpa-attendance.review', $record) }}" class="mt-3 grid gap-3">
                                            @csrf
                                            <label class="grid gap-2 text-sm font-semibold">Catatan pemeriksaan<textarea name="notes" maxlength="1000" rows="2" class="w-full border border-slate-300 p-3 text-sm"></textarea></label>
                                            <div class="flex flex-wrap gap-2"><button name="action" value="approved" class="min-h-11 rounded-lg bg-cyan-700 px-4 text-sm font-bold text-white">Setujui</button><button name="action" value="revision_requested" class="min-h-11 rounded-lg border border-amber-300 px-4 text-sm font-bold text-amber-800">Minta Revisi</button><button name="action" value="rejected" class="min-h-11 rounded-lg border border-rose-300 px-4 text-sm font-bold text-rose-700">Tolak</button></div>
                                        </form>
                                    @else
                                        <p class="mt-2 whitespace-pre-line break-words text-sm text-slate-600">{{ $record->field_supervisor_notes ?: 'Tidak ada catatan pemeriksa.' }}</p>
                                        <p class="mt-2 text-xs text-slate-500">Diperiksa {{ $record->reviewed_at?->format('d M Y H:i') ?: '-' }}. Keputusan tidak dapat diulang.</p>
                                    @endif
                                    @foreach($record->correctionRequests->where('status', 'submitted') as $correction)
                                        <form method="POST" action="{{ route('internal-supervisor.pkpa-attendance.corrections.review', $correction) }}" class="mt-4 grid gap-3 border-t border-slate-200 pt-4">
                                            @csrf
                                            <p class="text-sm font-bold">Permintaan koreksi</p><p class="whitespace-pre-line text-sm">{{ $correction->reason }}</p>
                                            <dl class="grid gap-1 text-sm">@foreach($correction->proposed_snapshot ?? [] as $key => $value)<div><dt class="inline font-semibold">{{ ['attendance_date' => 'Tanggal', 'attendance_type' => 'Kehadiran', 'check_in_time' => 'Jam masuk', 'check_out_time' => 'Jam pulang', 'student_notes' => 'Catatan'][$key] ?? $key }}:</dt><dd class="inline break-words"> {{ is_scalar($value) ? $value : '-' }}</dd></div>@endforeach</dl>
                                            <label class="grid gap-2 text-sm">Catatan koreksi<input name="notes" maxlength="1000" class="h-11 border border-slate-300 px-3"></label>
                                            <div class="flex gap-2"><button name="action" value="approved" class="min-h-11 rounded-lg bg-cyan-700 px-3 text-sm font-bold text-white">Setujui Koreksi</button><button name="action" value="rejected" class="min-h-11 rounded-lg border border-rose-300 px-3 text-sm font-bold text-rose-700">Tolak Koreksi</button></div>
                                        </form>
                                    @endforeach
                                </details>
                            </article>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </x-pkpa.domain-group>
    @empty
        <p class="border-y border-slate-200 bg-white p-5 text-sm text-slate-600">Tidak ada presensi pada filter ini.</p>
    @endforelse
</div>
@if($attendances->hasPages())<div class="mt-4">{{ $attendances->links() }}</div>@endif
