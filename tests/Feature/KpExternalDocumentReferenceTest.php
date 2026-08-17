<?php

namespace Tests\Feature;

use App\Models\KpExternalDocumentReference;
use App\Services\Integration\KpExternalDocumentReferenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class KpExternalDocumentReferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_external_document_reference_schema_and_model_defaults_are_valid(): void
    {
        $this->assertTrue(Schema::hasTable('kp_external_document_references'));

        foreach ([
            'uuid',
            'source_app',
            'external_app',
            'document_type',
            'service_code',
            'source_module',
            'source_reference_type',
            'source_reference_id',
            'external_document_id',
            'external_document_number',
            'external_status',
            'reference_url',
            'file_hash',
            'metadata',
            'last_payload_snapshot',
            'last_error',
            'synced_at',
            'created_by',
            'updated_by',
        ] as $column) {
            $this->assertTrue(Schema::hasColumn('kp_external_document_references', $column), $column);
        }

        $reference = KpExternalDocumentReference::create([
            'document_type' => 'placement_letter',
            'service_code' => 'KP_PLACEMENT_LETTER',
            'source_module' => 'assignment',
            'source_reference_type' => 'kp_assignment',
            'source_reference_id' => '1',
            'external_status' => 'draft',
            'metadata' => ['safe' => true],
            'last_payload_snapshot' => ['service_code' => 'KP_PLACEMENT_LETTER'],
        ]);

        $this->assertNotNull($reference->uuid);
        $this->assertSame('kppspa-farmasi', $reference->source_app);
        $this->assertSame('tu-farmasi', $reference->external_app);
        $this->assertTrue($reference->isSafeReferenceUrl());
    }

    public function test_reference_service_builds_and_persists_sanitized_local_draft_explicitly(): void
    {
        $service = app(KpExternalDocumentReferenceService::class);

        $preview = $service->previewFromTuPayload([
            'documents' => [
                [
                    'source_module' => 'assignment',
                    'source_reference_id' => 'kp_assignment:9',
                    'document_type' => 'placement_letter',
                    'service_code' => 'KP_PLACEMENT_LETTER',
                    'status' => 'ready_for_preview',
                    'student' => ['name' => 'Alya Farmasi'],
                    'file_reference' => [
                        'file_path' => 'storage/app/private/laporan.pdf',
                        'token' => 'secret-token',
                        'download_owner_app' => 'kppspa-farmasi',
                    ],
                    'validation_warnings' => [],
                ],
            ],
        ]);

        $encodedPreview = json_encode($preview);
        $this->assertStringContainsString('"dry_run":true', $encodedPreview);
        $this->assertStringContainsString('"local_persistence_performed":false', $encodedPreview);
        $this->assertStringNotContainsString('storage/app', $encodedPreview);
        $this->assertStringNotContainsString('secret-token', $encodedPreview);
        $this->assertStringNotContainsString('"token"', $encodedPreview);
        $this->assertDatabaseCount('kp_external_document_references', 0);

        $result = $service->persistLocalDrafts($preview);

        $this->assertSame(1, $result['created']);
        $this->assertSame(0, $result['updated']);
        $this->assertSame(1, $result['created_or_updated']);
        $this->assertSame([1], $result['reference_ids']);
        $this->assertDatabaseHas('kp_external_document_references', [
            'external_app' => 'tu-farmasi',
            'document_type' => 'placement_letter',
            'service_code' => 'KP_PLACEMENT_LETTER',
            'source_reference_type' => 'kp_assignment',
            'source_reference_id' => '9',
            'external_status' => 'draft',
        ]);

        $reference = KpExternalDocumentReference::firstOrFail();
        $stored = json_encode($reference->last_payload_snapshot);

        $this->assertStringNotContainsString('storage/app', $stored);
        $this->assertStringNotContainsString('secret-token', $stored);
        $this->assertStringNotContainsString('"token"', $stored);
    }

    public function test_reference_url_safety_rejects_sensitive_urls(): void
    {
        $reference = new KpExternalDocumentReference([
            'reference_url' => 'https://tu.example.test/documents/1?token=secret',
        ]);

        $this->assertFalse($reference->isSafeReferenceUrl());

        $reference->reference_url = 'https://tu.example.test/documents/1';

        $this->assertTrue($reference->isSafeReferenceUrl());
    }
}
