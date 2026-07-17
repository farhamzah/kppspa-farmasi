<?php

namespace App\Services;

use App\Models\PkpaProgramDomain;
use App\Models\PkpaRotationOperationRule;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PkpaRotationOperationRuleService
{
    public function __construct(private readonly PkpaAuditService $audit)
    {
    }

    public function save(PkpaProgramDomain $programDomain, array $data, ?User $actor): PkpaRotationOperationRule
    {
        if (! $actor?->hasAnyRole(['admin', 'koordinator_kp'])) {
            throw ValidationException::withMessages(['authorization' => 'Hanya Admin atau Koordinator PKPA yang dapat mengelola aturan operasional.']);
        }

        $this->validate($data);

        return DB::transaction(function () use ($programDomain, $data, $actor) {
            PkpaRotationOperationRule::where('pkpa_program_domain_id', $programDomain->id)
                ->where('is_active', true)
                ->update(['is_active' => false, 'active_key' => null, 'updated_by_core_user_id' => $actor?->core_user_id]);

            $rule = PkpaRotationOperationRule::create(array_merge($this->defaults(), $data, [
                'pkpa_program_domain_id' => $programDomain->id,
                'is_active' => true,
                'active_key' => 'PROGRAM_DOMAIN:'.$programDomain->id,
                'created_by_core_user_id' => $actor?->core_user_id,
                'updated_by_core_user_id' => $actor?->core_user_id,
            ]));

            $this->audit->record($actor, 'rotation_operation_rule_saved', $rule, null, $rule->only(['pkpa_program_domain_id', 'logbook_frequency']));

            return $rule;
        });
    }

    public function ensureDefault(PkpaProgramDomain $programDomain, ?User $actor = null): PkpaRotationOperationRule
    {
        return PkpaRotationOperationRule::where('pkpa_program_domain_id', $programDomain->id)->where('is_active', true)->first()
            ?: $this->save($programDomain, [], $actor);
    }

    public function validate(array $data): void
    {
        $frequency = $data['logbook_frequency'] ?? 'daily';
        if (! in_array($frequency, ['daily', 'weekly', 'flexible'], true)) {
            throw ValidationException::withMessages(['logbook_frequency' => 'Frekuensi logbook tidak valid.']);
        }

        foreach (['minimum_logbook_entries', 'minimum_approved_attendance_days', 'maximum_backdate_days', 'submission_deadline_days'] as $field) {
            if (array_key_exists($field, $data) && ! is_null($data[$field]) && (int) $data[$field] < 0) {
                throw ValidationException::withMessages([$field => 'Nilai minimum tidak boleh negatif.']);
            }
        }
    }

    public function defaults(): array
    {
        return [
            'attendance_required' => true,
            'require_check_in' => true,
            'require_check_out' => true,
            'allow_manual_attendance_time' => true,
            'logbook_required' => true,
            'logbook_frequency' => 'daily',
            'minimum_logbook_entries' => 0,
            'minimum_approved_attendance_days' => 0,
            'maximum_backdate_days' => null,
            'submission_deadline_days' => null,
            'field_supervisor_approval_required' => true,
            'internal_supervisor_monitoring_enabled' => true,
            'allow_student_edit_after_submit' => false,
            'completion_requires_all_approved' => true,
        ];
    }
}
