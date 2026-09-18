@php
    $totalAssignments = $assignments->count();
@endphp

<div class="space-y-6">
    <section class="flex flex-col gap-4 border-b border-sky-100 pb-5 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-xs font-black uppercase tracking-widest text-cyan-700">Daftar per wahana</p>
            <h2 class="mt-1 text-xl font-black text-slate-950">{{ $totalAssignments }} penempatan mahasiswa</h2>
            <p class="mt-1 text-sm text-slate-500">Mahasiswa dikelompokkan berdasarkan wahana agar periode dan tempat praktik lebih mudah diperiksa.</p>
        </div>

        @if($assignmentGroups->isNotEmpty())
            <nav class="flex max-w-full gap-2 overflow-x-auto pb-1" aria-label="Pilih wahana">
                @foreach($assignmentGroups as $domainId => $domainAssignments)
                    @php
                        $firstAssignment = $domainAssignments->first();
                        $domainName = $firstAssignment?->practiceDomain?->name ?: $firstAssignment?->practice_domain_name_snapshot ?: 'Wahana lainnya';
                        $domainAnchor = 'wahana-'.str($domainId)->slug();
                    @endphp
                    <a href="#{{ $domainAnchor }}" class="inline-flex min-h-10 shrink-0 items-center gap-2 rounded-lg border border-sky-200 bg-white px-3 py-2 text-sm font-bold text-slate-700 transition hover:border-cyan-500 hover:text-cyan-700">
                        <span>{{ $domainName }}</span>
                        <span class="rounded-full bg-sky-50 px-2 py-0.5 text-xs text-cyan-700">{{ $domainAssignments->count() }}</span>
                    </a>
                @endforeach
            </nav>
        @endif
    </section>

    @forelse($assignmentGroups as $domainId => $domainAssignments)
        @php
            $firstAssignment = $domainAssignments->first();
            $domainName = $firstAssignment?->practiceDomain?->name ?: $firstAssignment?->practice_domain_name_snapshot ?: 'Wahana lainnya';
            $domainCode = $firstAssignment?->practiceDomain?->code;
            $domainAnchor = 'wahana-'.str($domainId)->slug();
        @endphp
        <section id="{{ $domainAnchor }}" class="scroll-mt-6 overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-slate-200">
            <header class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-slate-50 px-5 py-4">
                <div>
                    @if($domainCode)
                        <p class="text-xs font-black uppercase tracking-widest text-cyan-700">{{ $domainCode }}</p>
                    @endif
                    <h3 class="mt-0.5 text-lg font-black text-slate-950">{{ $domainName }}</h3>
                </div>
                <span class="rounded-full bg-white px-3 py-1 text-xs font-bold text-slate-600 ring-1 ring-slate-200">
                    {{ $domainAssignments->count() }} mahasiswa
                </span>
            </header>

            <div class="overflow-x-auto">
                <table class="si-data-table">
                    <thead>
                        <tr>
                            <th>Mahasiswa</th>
                            <th>Periode</th>
                            <th>Tempat Praktik</th>
                            <th>{{ $counterpartLabel }}</th>
                            <th class="text-center">Status</th>
                            <th class="text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($domainAssignments as $assignment)
                            @php($counterpart = $assignment->supervisors->firstWhere('supervisor_type', $counterpartType))
                            <tr>
                                <td>
                                    <div class="font-semibold text-slate-950">{{ $assignment->student_name_snapshot }}</div>
                                    <div class="mt-0.5 text-xs text-slate-500">{{ $assignment->student_number_snapshot ?: '-' }}</div>
                                </td>
                                <td class="whitespace-nowrap">{{ $assignment->start_date?->format('d M Y') }} - {{ $assignment->end_date?->format('d M Y') }}</td>
                                <td>{{ $assignment->practice_site_name_snapshot }}</td>
                                <td>{{ $counterpart?->display_name ?: 'Belum ditentukan' }}</td>
                                <td class="text-center">
                                    <span class="inline-flex rounded-full bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-700">Aktif di portal</span>
                                </td>
                                <td class="whitespace-nowrap text-right">
                                    <a href="{{ route($detailRoute, $assignment) }}" class="si-table-action">Detail</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @empty
        <section class="rounded-lg bg-white px-5 py-12 text-center shadow-sm ring-1 ring-slate-200">
            <p class="font-bold text-slate-700">{{ $emptyMessage }}</p>
            <p class="mt-1 text-sm text-slate-500">Data akan tampil setelah penempatan resmi dipublikasikan.</p>
        </section>
    @endforelse
</div>
