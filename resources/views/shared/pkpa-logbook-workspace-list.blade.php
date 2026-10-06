@php
    $entryGroups = $logbookEntries->getCollection()->groupBy(
        fn ($entry) => $entry->rotationRun?->practice_domain_id ?: 'lainnya'
    );
    $statusLabels = [
        'submitted' => config('my_pkpa.preceptor_document_validation_enabled') ? 'Menunggu Preseptor' : 'Siap Divalidasi',
        'field_approved' => 'Disetujui Preseptor',
        'approved' => 'Siap Validasi Akhir',
        'internal_approved' => 'Validasi Selesai',
        'revision_requested' => 'Perlu Revisi',
        'rejected' => 'Ditolak',
    ];
    $statusClasses = [
        'submitted' => 'bg-amber-50 text-amber-700',
        'field_approved' => 'bg-cyan-50 text-cyan-700',
        'approved' => 'bg-cyan-50 text-cyan-700',
        'internal_approved' => 'bg-emerald-50 text-emerald-700',
        'revision_requested' => 'bg-orange-50 text-orange-700',
        'rejected' => 'bg-rose-50 text-rose-700',
    ];
@endphp

<div class="space-y-8">
    @forelse($entryGroups as $domainId => $domainEntries)
        @php
            $domain = $domainEntries->first()?->rotationRun?->practiceDomain;
            $studentGroups = $domainEntries->groupBy('pkpa_rotation_run_id');
        @endphp
        <x-pkpa.domain-group :name="$domain?->name ?? 'Wahana lainnya'" :code="$domain?->code" :count="$domainEntries->count()" :anchor="'logbook-'.$domainId">
            <div class="space-y-4">
                @foreach($studentGroups as $studentEntries)
                    @php
                        $run = $studentEntries->first()?->rotationRun;
                    @endphp
                    <article class="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-slate-200">
                        <header class="flex flex-col gap-2 border-b border-slate-100 bg-slate-50 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                            <div>
                                <h3 class="text-lg font-black text-slate-950">{{ $run?->studentDisplayName() }}</h3>
                                <p class="mt-1 text-sm text-slate-500">{{ $run?->studentDisplaySecondary() }} · {{ $run?->practiceSite?->name }}</p>
                            </div>
                            <div class="flex shrink-0 flex-wrap items-center gap-3">
                                <span class="text-xs font-bold text-slate-500">{{ $studentEntries->count() }} logbook di halaman ini</span>
                                @if($bulkSelection ?? false)
                                    <label class="flex min-h-11 cursor-pointer items-center gap-2 text-sm font-semibold text-cyan-800"><input type="checkbox" data-bulk-group-select="{{ $run->id }}" aria-label="Pilih semua logbook {{ $run->studentDisplayName() }} di halaman ini" class="h-5 w-5 accent-cyan-700">Pilih mahasiswa ini</label>
                                @endif
                            </div>
                        </header>
                        <div class="divide-y divide-slate-100">
                            @foreach($studentEntries as $entry)
                                <div class="flex flex-wrap items-start gap-3 px-4 py-3 transition-colors hover:bg-slate-50 has-[:checked]:bg-cyan-50/60 sm:items-center sm:px-5">
                                    @if($bulkSelection ?? false)
                                        <label class="flex h-11 w-8 shrink-0 cursor-pointer items-center"><input type="checkbox" name="ids[]" value="{{ $entry->id }}" form="bulk-validation" data-bulk-group="{{ $run->id }}" aria-label="Pilih {{ $entry->title }}" class="h-5 w-5 accent-cyan-700"></label>
                                    @endif
                                    <div class="min-w-0 flex-1 basis-[180px]">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <p class="break-words font-bold text-slate-950">{{ $entry->title }}</p>
                                            <span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $statusClasses[$entry->status] ?? 'bg-slate-100 text-slate-600' }}">{{ $statusLabels[$entry->status] ?? str($entry->status)->replace('_', ' ')->headline() }}</span>
                                        </div>
                                        <p class="mt-1 text-sm text-slate-500">{{ optional($entry->entry_date)->translatedFormat('d M Y') }}</p>
                                    </div>
                                    <a href="{{ route($routePrefix.'.pkpa-operations.show', ['run' => $run, 'logbook' => $entry->id]) }}" class="inline-flex min-h-10 shrink-0 items-center justify-center rounded-lg bg-cyan-700 px-4 py-2 text-sm font-bold text-white">{{ $actionLabel }}</a>
                                </div>
                            @endforeach
                        </div>
                    </article>
                @endforeach
            </div>
        </x-pkpa.domain-group>
    @empty
        <div class="rounded-lg bg-white px-5 py-12 text-center shadow-sm ring-1 ring-slate-200">
            <p class="font-bold text-slate-700">{{ $emptyTitle }}</p>
            <p class="mt-1 text-sm text-slate-500">{{ $emptyDescription }}</p>
        </div>
    @endforelse
</div>

@if($logbookEntries->hasPages())
    <div class="mt-5">{{ $logbookEntries->links() }}</div>
@endif
