<?php

namespace App\Services;

use App\Models\PkpaPlacementPublication;
use App\Models\PkpaPublishedAssignment;
use App\Models\PkpaScheduleAcknowledgement;
use App\Models\User;
use Illuminate\Http\Request;

class PkpaScheduleAcknowledgementService
{
    public function __construct(private readonly PkpaAuditService $audit)
    {
    }

    public function record(PkpaPlacementPublication $publication, ?PkpaPublishedAssignment $assignment, User $user, string $audienceType, string $type, ?Request $request = null): PkpaScheduleAcknowledgement
    {
        $ack = PkpaScheduleAcknowledgement::firstOrCreate(
            [
                'pkpa_placement_publication_id' => $publication->id,
                'pkpa_published_assignment_id' => $assignment?->id,
                'core_user_id' => $user->core_user_id,
                'audience_type' => $audienceType,
                'acknowledgement_type' => $type,
            ],
            [
                'acknowledged_at' => now(),
                'ip_address_hash' => $request?->ip() ? hash('sha256', $request->ip()) : null,
                'user_agent_summary' => $request?->userAgent() ? str($request->userAgent())->limit(160)->toString() : null,
            ]
        );

        if (! $ack->wasRecentlyCreated && blank($ack->acknowledged_at)) {
            $ack->update(['acknowledged_at' => now()]);
        }

        $this->audit->record($user, 'schedule_'.$type, $ack, null, ['audience_type' => $audienceType]);

        return $ack;
    }
}
