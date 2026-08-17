<?php

namespace App\Services\Integration;

use App\Models\KpExternalDocumentReference;
use Illuminate\Support\Arr;

class KpExternalDocumentReferenceService
{
    public function previewFromTuPayload(array $payload): array
    {
        $documents = collect($payload['documents'] ?? []);

        return [
            'source_app' => $this->sourceApp(),
            'external_app' => 'tu-farmasi',
            'contract_version' => 'kp-tu-external-reference-v1',
            'dry_run' => true,
            'external_request_sent' => false,
            'generated_at' => now()->toIso8601String(),
            'summary' => [
                'documents_scanned' => $documents->count(),
                'references_previewed' => $documents->count(),
                'local_persistence_performed' => false,
            ],
            'references' => $documents
                ->map(fn (array $document) => $this->draftReference($document))
                ->values()
                ->all(),
            'validation_warnings' => $documents->isEmpty() ? ['No TU document payload found for reference preview.'] : [],
        ];
    }

    public function persistLocalDrafts(array $referencePreview, ?int $userId = null): array
    {
        $references = collect($referencePreview['references'] ?? []);

        $created = 0;
        $updated = 0;
        $models = $references->map(function (array $draft) use ($userId, &$created, &$updated): KpExternalDocumentReference {
            $attributes = Arr::except($draft, ['dry_run', 'local_draft_only', 'safe_to_persist']);
            $attributes['created_by'] = $userId;
            $attributes['updated_by'] = $userId;

            $model = KpExternalDocumentReference::query()->updateOrCreate([
                'external_app' => $attributes['external_app'],
                'document_type' => $attributes['document_type'],
                'source_reference_type' => $attributes['source_reference_type'],
                'source_reference_id' => $attributes['source_reference_id'],
            ], $attributes);

            $model->wasRecentlyCreated ? $created++ : $updated++;

            return $model;
        });

        return [
            'persisted' => true,
            'created' => $created,
            'updated' => $updated,
            'created_or_updated' => $models->count(),
            'reference_ids' => $models->pluck('id')->all(),
        ];
    }

    private function draftReference(array $document): array
    {
        [$referenceType, $referenceId] = $this->parseSourceReference((string) ($document['source_reference_id'] ?? 'unknown:missing'));

        return [
            'source_app' => $this->sourceApp(),
            'external_app' => 'tu-farmasi',
            'document_type' => (string) ($document['document_type'] ?? 'unknown'),
            'service_code' => (string) ($document['service_code'] ?? 'UNKNOWN'),
            'source_module' => (string) ($document['source_module'] ?? 'unknown'),
            'source_reference_type' => $referenceType,
            'source_reference_id' => $referenceId,
            'external_document_id' => null,
            'external_document_number' => null,
            'external_status' => 'draft',
            'reference_url' => null,
            'file_hash' => null,
            'metadata' => [
                'payload_status' => $document['status'] ?? null,
                'validation_warnings' => $document['validation_warnings'] ?? [],
                'generated_from' => 'kp-tu-doc-v1-preview',
            ],
            'last_payload_snapshot' => $this->sanitizePayloadSnapshot($document),
            'last_error' => null,
            'synced_at' => null,
            'dry_run' => true,
            'local_draft_only' => true,
            'safe_to_persist' => true,
        ];
    }

    private function parseSourceReference(string $reference): array
    {
        [$type, $id] = array_pad(explode(':', $reference, 2), 2, 'missing');

        return [$type ?: 'unknown', $id ?: 'missing'];
    }

    private function sourceApp(): string
    {
        return (string) config('core_farmasi.app_code', 'kppspa-farmasi');
    }

    private function sanitizePayloadSnapshot(array $payload): array
    {
        return $this->removeSensitiveData($payload);
    }

    private function removeSensitiveData(mixed $value): mixed
    {
        if (is_string($value)) {
            return preg_match('/token|password|secret|storage\/app|private|signed-url|signed_url/i', $value) ? '[redacted]' : $value;
        }

        if (! is_array($value)) {
            return $value;
        }

        $sanitized = [];

        foreach ($value as $key => $item) {
            $keyName = is_string($key) ? strtolower($key) : '';

            if ($keyName !== '' && preg_match('/token|password|secret|signed|meeting_link|file_path|storage_path|internal_path/', $keyName)) {
                continue;
            }

            $sanitized[$key] = $this->removeSensitiveData($item);
        }

        return $sanitized;
    }
}
