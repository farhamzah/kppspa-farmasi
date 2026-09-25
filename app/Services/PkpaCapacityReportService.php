<?php

namespace App\Services;

use App\Models\KpPeriod;
use App\Models\KpPlace;
use App\Models\KpPlaceQuota;
use App\Models\PkpaPracticeDomain;
use App\Models\PkpaPracticeSite;
use App\Models\PkpaProgram;
use App\Models\PkpaProgramSite;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class PkpaCapacityReportService
{
    public const TYPES = ['practice-sites', 'program-sites', 'quotas'];

    public function __construct(
        private readonly LegacyKpCatalogSyncService $catalogSync,
        private readonly PkpaPracticeSiteService $practiceSiteService,
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

        if ($type === 'practice-sites') {
            return $this->practiceSiteService->query($request->only([
                'q',
                'practice_domain_id',
                'practice_domain_option_id',
                'city',
                'province',
                'status',
                'active',
                'cooperation',
            ]))
                ->orderBy('practice_domain_id')
                ->orderBy('name');
        }

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
            if ($type === 'practice-sites') {
                return [
                    'No' => $index + 1,
                    'Kode' => $item->code,
                    'Tempat Praktik' => $item->name,
                    'Wahana' => $item->practiceDomain?->name ?? '-',
                    'Jenis' => $item->practiceDomainOption?->name ?? '-',
                    'Kota' => $item->city ?? '-',
                    'Provinsi' => $item->province ?? '-',
                    'Kerja Sama' => $item->cooperationStatusLabel(),
                    'Status' => $item->statusLabel(),
                ];
            }

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
        return match ($type) {
            'practice-sites' => 'Daftar Master Tempat Praktik PKPA',
            'program-sites' => 'Daftar Tempat Tersedia PKPA',
            default => 'Kapasitas Tempat PKPA',
        };
    }

    public function filename(string $type): string
    {
        return match ($type) {
            'practice-sites' => 'tempat-praktik-pkpa-',
            'program-sites' => 'tempat-tersedia-pkpa-',
            default => 'kapasitas-tempat-pkpa-',
        }.now()->format('Ymd-His');
    }

    public function filterSummary(string $type, Request $request): array
    {
        if ($type === 'practice-sites') {
            return [
                'Wahana' => $request->filled('practice_domain_id') ? PkpaPracticeDomain::find($request->integer('practice_domain_id'))?->name ?? 'Tidak ditemukan' : 'Semua wahana',
                'Status' => $request->filled('status') ? str($request->status)->headline()->toString() : 'Semua status',
                'Aktif' => match ((string) $request->active) {
                    '1' => 'Ya',
                    '0' => 'Tidak',
                    default => 'Semua',
                },
                'Kerja Sama' => match ($request->cooperation) {
                    'valid' => 'Berlaku',
                    'expired' => 'Berakhir',
                    default => 'Semua',
                },
                'Pencarian' => $request->filled('q') ? trim((string) $request->q) : '-',
            ];
        }

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

    public function coverage(?PkpaProgram $program = null): array
    {
        $program ??= PkpaProgram::query()
            ->where('is_active', true)
            ->whereIn('status', ['ready', 'active'])
            ->orderByDesc('id')
            ->first()
            ?? PkpaProgram::query()->whereIn('status', ['ready', 'active'])->orderByDesc('id')->first();

        $domains = PkpaPracticeDomain::query()->where('is_active', true)->orderBy('sort_order')->get();

        $rows = $domains->map(function (PkpaPracticeDomain $domain) use ($program) {
            $masterSites = PkpaPracticeSite::query()
                ->where('practice_domain_id', $domain->id)
                ->where('is_active', true)
                ->where('status', 'active')
                ->get(['id', 'name']);

            $programSites = $program
                ? PkpaProgramSite::query()
                    ->with('practiceSite:id,name')
                    ->where('pkpa_program_id', $program->id)
                    ->where('practice_domain_id', $domain->id)
                    ->where('is_active', true)
                    ->whereIn('status', ['ready', 'active'])
                    ->get()
                : collect();

            $capacitySites = $programSites->filter(fn (PkpaProgramSite $site) => $site->availabilityPeriods()
                ->whereIn('status', ['available', 'full'])
                ->where('maximum_students', '>', 0)
                ->exists());
            $programSiteIds = $programSites->pluck('practice_site_id');
            $capacitySiteIds = $capacitySites->pluck('practice_site_id');

            return [
                'domain' => $domain,
                'master' => $masterSites->count(),
                'program' => $programSites->unique('practice_site_id')->count(),
                'capacity' => $capacitySites->unique('practice_site_id')->count(),
                'missing_program' => $masterSites->whereNotIn('id', $programSiteIds)->pluck('name')->values(),
                'missing_capacity' => $programSites->whereNotIn('practice_site_id', $capacitySiteIds)->pluck('practiceSite.name')->filter()->values(),
            ];
        });

        return [
            'program' => $program,
            'rows' => $rows,
            'consistent' => $rows->every(fn (array $row) => $row['master'] === $row['program'] && $row['program'] === $row['capacity']),
        ];
    }
}
