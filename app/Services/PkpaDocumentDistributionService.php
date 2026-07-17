<?php

namespace App\Services;

use App\Models\PkpaDocumentRecipient;
use App\Models\PkpaGeneratedDocument;
use App\Models\User;

class PkpaDocumentDistributionService
{
    public function ensurePortalRecipient(PkpaGeneratedDocument $document, array $recipient): PkpaDocumentRecipient
    {
        return PkpaDocumentRecipient::updateOrCreate([
            'pkpa_generated_document_id' => $document->id,
            'recipient_type' => $recipient['recipient_type'],
            'core_user_id' => $recipient['core_user_id'] ?? null,
        ], [
            'name_snapshot' => $recipient['name_snapshot'] ?? null,
            'email_snapshot' => $recipient['email_snapshot'] ?? null,
            'organization_snapshot' => $recipient['organization_snapshot'] ?? null,
            'access_scope' => $recipient['access_scope'] ?? 'portal',
            'status' => 'active',
        ]);
    }

    public function markPublished(PkpaGeneratedDocument $document, ?User $actor): void
    {
        foreach ($document->recipients as $recipient) {
            $document->distributionLogs()->firstOrCreate([
                'recipient_id' => $recipient->id,
                'channel' => 'portal',
            ], [
                'status' => 'sent',
                'attempt_count' => 1,
                'sent_at' => now(),
                'distributed_by_core_user_id' => $actor?->core_user_id,
            ]);

            $document->distributionLogs()->firstOrCreate([
                'recipient_id' => $recipient->id,
                'channel' => 'email',
            ], [
                'status' => config('my_pspa.document_email_enabled') ? 'pending' : 'skipped',
                'attempt_count' => 0,
                'failure_message' => config('my_pspa.document_email_enabled') ? null : 'Email dokumen belum diaktifkan.',
                'distributed_by_core_user_id' => $actor?->core_user_id,
            ]);
        }
    }
}
