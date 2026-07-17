<?php

namespace App\Services;

use App\Models\PkpaProgramSite;
use App\Models\PkpaSiteAvailabilityPeriod;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PkpaSiteAvailabilityService
{
    public function __construct(private readonly PkpaAuditService $audit)
    {
    }

    public function create(PkpaProgramSite $programSite, array $data, ?User $actor): PkpaSiteAvailabilityPeriod
    {
        $this->validate($programSite->loadMissing(['program', 'practiceSite']), $data);

        return DB::transaction(function () use ($programSite, $data, $actor) {
            $period = $programSite->availabilityPeriods()->create($this->normalize($data) + [
                'created_by_core_user_id' => $actor?->core_user_id,
                'updated_by_core_user_id' => $actor?->core_user_id,
            ]);
            $this->audit->record($actor, 'site_availability_created', $period, null, $period->only(['start_date', 'end_date', 'maximum_students', 'reserved_slots']));

            return $period;
        });
    }

    public function update(PkpaSiteAvailabilityPeriod $period, array $data, ?User $actor): PkpaSiteAvailabilityPeriod
    {
        $this->validate($period->programSite->loadMissing(['program', 'practiceSite']), $data, $period);

        return DB::transaction(function () use ($period, $data, $actor) {
            $data = $this->normalize($data);
            $old = $period->only(array_keys($data));
            $period->update($data + ['updated_by_core_user_id' => $actor?->core_user_id]);
            $this->audit->record($actor, 'site_availability_updated', $period, $old, $period->only(array_keys($data)));

            return $period->refresh();
        });
    }

    public function cancel(PkpaSiteAvailabilityPeriod $period, ?User $actor): PkpaSiteAvailabilityPeriod
    {
        $old = $period->only(['status']);
        $period->update(['status' => 'cancelled', 'updated_by_core_user_id' => $actor?->core_user_id]);
        $this->audit->record($actor, 'site_availability_cancelled', $period, $old, ['status' => 'cancelled']);

        return $period->refresh();
    }

    private function validate(PkpaProgramSite $programSite, array $data, ?PkpaSiteAvailabilityPeriod $current = null): void
    {
        if (! $programSite->is_active || ! in_array($programSite->status, ['ready', 'active'], true)) {
            throw ValidationException::withMessages(['pkpa_program_site_id' => 'Availability hanya dapat dibuat pada tempat program aktif atau ready.']);
        }

        $start = Carbon::parse($data['start_date'] ?? null);
        $end = Carbon::parse($data['end_date'] ?? null);
        if ($start->gt($end)) {
            throw ValidationException::withMessages(['end_date' => 'Tanggal selesai harus setelah atau sama dengan tanggal mulai.']);
        }

        if (($data['maximum_students'] ?? 0) <= 0) {
            throw ValidationException::withMessages(['maximum_students' => 'Kapasitas maksimum harus lebih dari nol.']);
        }

        if (($data['reserved_slots'] ?? 0) > ($data['maximum_students'] ?? 0)) {
            throw ValidationException::withMessages(['reserved_slots' => 'Reserved slot tidak boleh melebihi kapasitas maksimum.']);
        }

        $program = $programSite->program;
        if ($program->start_date && $start->lt($program->start_date)) {
            throw ValidationException::withMessages(['start_date' => 'Availability tidak boleh dimulai sebelum program dimulai.']);
        }
        if ($program->end_date && $end->gt($program->end_date)) {
            throw ValidationException::withMessages(['end_date' => 'Availability tidak boleh berakhir setelah program selesai.']);
        }

        $site = $programSite->practiceSite;
        if ($site->cooperation_start_date && $start->lt($site->cooperation_start_date)) {
            throw ValidationException::withMessages(['start_date' => 'Availability tidak boleh dimulai sebelum kerja sama berlaku.']);
        }
        if ($site->cooperation_end_date && $end->gt($site->cooperation_end_date)) {
            throw ValidationException::withMessages(['end_date' => 'Availability tidak boleh melewati akhir kerja sama mitra.']);
        }

        $days = collect($data['operational_days'] ?? [])->filter()->values();
        if ($days->diff(PkpaSiteAvailabilityPeriod::OPERATIONAL_DAYS)->isNotEmpty()) {
            throw ValidationException::withMessages(['operational_days' => 'Hari operasional tidak valid.']);
        }

        if (filled($data['daily_start_time'] ?? null) && filled($data['daily_end_time'] ?? null) && ($data['daily_end_time'] <= $data['daily_start_time'])) {
            throw ValidationException::withMessages(['daily_end_time' => 'Jam selesai harus setelah jam mulai.']);
        }

        $overlap = $programSite->availabilityPeriods()
            ->when($current, fn ($query) => $query->whereKeyNot($current->id))
            ->whereNotIn('status', ['cancelled'])
            ->whereDate('start_date', '<=', $end->toDateString())
            ->whereDate('end_date', '>=', $start->toDateString())
            ->exists();
        if ($overlap) {
            throw ValidationException::withMessages(['start_date' => 'Periode availability overlap dengan periode lain pada tempat ini.']);
        }
    }

    private function normalize(array $data): array
    {
        $data['operational_days'] = array_values(array_filter($data['operational_days'] ?? []));
        $data['reserved_slots'] = $data['reserved_slots'] ?? 0;

        return $data;
    }
}
