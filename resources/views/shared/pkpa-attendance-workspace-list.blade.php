@php
    $attendanceGroups = $attendanceRecords->getCollection()->groupBy(
        fn ($record) => $record->rotationRun?->practice_domain_id ?: 'lainnya'
    );
@endphp

<div class="space-y-8">
    @forelse($attendanceGroups as $domainId => $domainRecords)
        @php
            $domain = $domainRecords->first()?->rotationRun?->practiceDomain;
            $studentGroups = $domainRecords->groupBy('pkpa_rotation_run_id');
        @endphp
        <x-pkpa.domain-group :name="$domain?->name ?? 'Wahana lainnya'" :code="$domain?->code" :count="$domainRecords->count()" :anchor="'presensi-'.$domainId">
            <div class="space-y-4">
                @foreach($studentGroups as $studentRecords)
                    @php
                        $run = $studentRecords->first()?->rotationRun;
                    @endphp
                    <article class="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-slate-200">
                        <header class="flex flex-col gap-2 border-b border-slate-100 bg-slate-50 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                            <div>
                                <h3 class="text-lg font-black text-slate-950">{{ $run?->studentDisplayName() }}</h3>
                                <p class="mt-1 text-sm text-slate-500">{{ $run?->studentDisplaySecondary() }} · {{ $run?->practiceSite?->name }}</p>
                            </div>
                            <span class="w-fit text-xs font-bold text-slate-500">{{ $studentRecords->count() }} presensi</span>
                        </header>
                        <div class="divide-y divide-slate-100">
                            @foreach($studentRecords as $record)
                                <div class="flex flex-col gap-3 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                                    <div>
                                        <p class="font-bold text-slate-950">Presensi {{ optional($record->attendance_date)->translatedFormat('d M Y') }}</p>
                                        <p class="mt-1 text-sm text-slate-500">{{ str($record->attendance_type)->replace('_', ' ')->headline() }} · {{ $record->check_in_time ?: '-' }} - {{ $record->check_out_time ?: '-' }}</p>
                                    </div>
                                    <a href="{{ route('field-supervisor.pkpa-operations.show', ['run' => $run, 'attendance' => $record->id]) }}" class="inline-flex min-h-10 shrink-0 items-center justify-center rounded-lg bg-cyan-700 px-4 py-2 text-sm font-bold text-white">Periksa & Validasi</a>
                                </div>
                            @endforeach
                        </div>
                    </article>
                @endforeach
            </div>
        </x-pkpa.domain-group>
    @empty
        <div class="rounded-lg bg-white px-5 py-8 text-center shadow-sm ring-1 ring-slate-200">
            <p class="font-bold text-slate-700">Belum ada presensi yang perlu divalidasi.</p>
            <p class="mt-1 text-sm text-slate-500">Presensi akan muncul setelah dikirim oleh mahasiswa.</p>
        </div>
    @endforelse
</div>

@if($attendanceRecords->hasPages())
    <div class="mt-5">{{ $attendanceRecords->links() }}</div>
@endif
