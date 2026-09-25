<?php

namespace App\Services;

use App\Models\KpPeriod;
use App\Models\KpPlace;
use App\Models\KpPlaceQuota;
use App\Models\PkpaPracticeDomain;
use App\Models\PkpaProgram;
use App\Models\PkpaProgramSite;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class PkpaCapacityReportService
{
    public const TYPES = ['program-sites', 'quotas'];

    public function __construct(
        private readonly LegacyKpCatalogSyncService $catalogSync,
    ) {}

    public function prepare(string $type, Request $request): void
    {
        if ($type === 'quotas') {
            $this->catalogSync->sync($request->user());
        }
    }

    public function query(string $type, Request $request): Builder
    {
        abort_unless(in_array($type, self::TYPES, true), 404);

        if ($type === 'program-sites') {
            return PkpaProgramSite::query()
                ->with([
                    'program',
                    'practiceSite.fieldSupervisors' => fn ($query) => $query->where('status', 'active'),
                    'practiceDomain',
                    'practiceDomainOption',
                    'availabilityPeriods' => fn ($query) => $query
                        ->whereIn('status', ['available', 'full'])
                        ->withCount('rotationAssignments'),
                ])
                ->search($request->input('q'))
                ->when($request->filled('program_id'), fn (Builder $query) => $query->where('pkpa_program_id', $request->integer('program_id')))
                ->when($request->filled('practice_domain_id'), fn (Builder $query) => $query->where('practice_domain_id', $request->integer('practice_domain_id')))
                ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->status))
                ->orderBy('practice_domain_id')
                ->orderByDesc('pkpa_program_id')
                ->orderBy('practice_site_id');
        }

        return KpPlaceQuota::query()
            ->with(['period', 'place'])
            ->when($request->filled('period'), fn (Builder $query) => $query->where('kp_period_id', $request->integer('period')))
            ->when($request->filled('type'), fn (Builder $query) => $query->whereHas('place', fn (Builder $place) => $place->where('type', $request->type)))
            ->when($request->filled('status'), fn (Builder $query) => $query->where('is_open', $request->status === 'open'))
            ->when($request->filled('q'), fn (Builder $query) => $query->whereHas('place', fn (Builder $place) => $place->where('name', 'like', '%'.trim((string) $request->q).'%')))
            ->orderBy(KpPlace::select('type')->whereColumn('kp_places.id', 'kp_place_quotas.kp_place_id'))
            ->orderByDesc('kp_period_id')
            ->orderBy(KpPlace::select('name')->whereColumn('kp_places.id', 'kp_place_quotas.kp_place_id'));
    }

    public function rows(string $type, Request $request): Collection
    {
        return $this->query($type, $request)->get()->values()->map(function ($item, int $index) use ($type) {
            if ($type === 'quotas') {
                return [
                    'No' => $index + 1,
                    'Program/Periode' => $item->period?->name ?? '-',
                    'Wahana' => $item->place?->typeLabel() ?? '-',
                    'Tempat Praktik' => $item->place?->name ?? '-',
                    'Kapasitas' => $item->quota,
                    'Terisi' => $item->filledCount(),
                    'Sisa' => $item->remainingQuota(),
                    'Status' => $item->statusLabel(),
                ];
            }

            $periods = $item->availabilityPeriods;
            $capacity = (int) $periods->sum('maximum_students');
            $reserved = (int) $periods->sum('reserved_slots');
            $filled = (int) $periods->sum('rotation_assignments_count');

            return [
                'No' => $index + 1,
                'Program' => $item->program?->name ?? '-',
                'Wahana' => $item->practiceDomain?->name ?? '-',
                'Tempat Praktik' => $item->practiceSite?->name ?? '-',
                'Kota' => $item->practiceSite?->city ?? '-',
                'Periode Tersedia' => $periods->map(fn ($period) => $period->start_date?->format('d/m/Y').' - '.$period->end_date?->format('d/m/Y'))->implode('; ') ?: 'Belum diatur',
                'Kapasitas' => $capacity,
                'Terisi' => $filled,
                'Sisa' => max(0, $capacity - $reserved - $filled),
                'Preseptor' => $item->practiceSite?->fieldSupervisors?->count() ?? 0,
                'Status' => $item->statusLabel(),
            ];
        });
    }

    public function title(string $type): string
    {
        return $type === 'program-sites'
            ? 'Daftar Tempat Tersedia PKPA'
            : 'Kapasitas Tempat PKPA';
    }

    public function filename(string $type): string
    {
        return ($type === 'program-sites' ? 'tempat-tersedia-pkpa-' : 'kapasitas-tempat-pkpa-').now()->format('Ymd-His');
    }

    public function filterSummary(string $type, Request $request): array
    {
        if ($type === 'program-sites') {
            return [
                'Program' => $request->filled('program_id') ? PkpaProgram::find($request->integer('program_id'))?->name ?? 'Tidak ditemukan' : 'Semua program',
                'Wahana' => $request->filled('practice_domain_id') ? PkpaPracticeDomain::find($request->integer('practice_domain_id'))?->name ?? 'Tidak ditemukan' : 'Semua wahana',
                'Status' => $request->filled('status') ? str($request->status)->headline()->toString() : 'Semua status',
                'Pencarian' => $request->filled('q') ? trim((string) $request->q) : '-',
            ];
        }

        return [
            'Program/Periode' => $request->filled('period') ? KpPeriod::find($request->integer('period'))?->name ?? 'Tidak ditemukan' : 'Semua periode',
            'Wahana' => $request->filled('type') ? (new KpPlace(['type' => $request->type]))->typeLabel() : 'Semua wahana',
            'Status' => match ($request->status) {
                'open' => 'Dibuka',
                'closed' => 'Ditutup',
                default => 'Semua status',
            },
            'Pencarian' => $request->filled('q') ? trim((string) $request->q) : '-',
        ];
    }
}
