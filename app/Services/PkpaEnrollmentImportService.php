<?php

namespace App\Services;

use App\Models\PkpaEnrollment;
use App\Models\PkpaEnrollmentImportBatch;
use App\Models\PkpaProgram;
use App\Models\PkpaStudentGroup;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;

class PkpaEnrollmentImportService
{
    public const HEADERS = ['core_user_id', 'npm', 'group_code', 'notes'];

    public function __construct(
        private readonly PkpaCoreStudentResolver $resolver,
        private readonly PkpaEnrollmentService $enrollmentService,
        private readonly PkpaAuditService $audit,
    ) {
    }

    public function templateCsv(): string
    {
        return implode(',', self::HEADERS)."\nCORE-001,231001,KEL-01,Catatan opsional\n";
    }

    public function preview(PkpaProgram $program, UploadedFile $file, ?User $actor): PkpaEnrollmentImportBatch
    {
        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, ['csv', 'txt', 'xlsx', 'xls'], true)) {
            throw ValidationException::withMessages(['file' => 'File harus CSV, TXT, XLS, atau XLSX.']);
        }

        $rows = $this->readRows($file->getRealPath(), $extension);
        if ($rows === []) {
            throw ValidationException::withMessages(['file' => 'File import tidak memiliki data.']);
        }

        $headers = array_map(fn ($value) => str((string) $value)->trim()->lower()->toString(), array_shift($rows));
        if (collect(['password', 'role', 'roles'])->intersect($headers)->isNotEmpty()) {
            throw ValidationException::withMessages(['file' => 'File import tidak boleh memuat kolom password atau role.']);
        }

        if (! collect(self::HEADERS)->every(fn ($header) => in_array($header, $headers, true))) {
            throw ValidationException::withMessages(['file' => 'Header wajib: '.implode(', ', self::HEADERS)]);
        }

        return DB::transaction(function () use ($program, $file, $rows, $headers, $actor) {
            $stored = $file->store('pkpa-enrollment-imports', 'local');
            $batch = PkpaEnrollmentImportBatch::create([
                'pkpa_program_id' => $program->id,
                'original_filename' => $file->getClientOriginalName(),
                'stored_filename' => $stored,
                'status' => 'validating',
                'started_at' => now(),
                'created_by_core_user_id' => $actor?->core_user_id,
            ]);

            $seen = [];
            foreach ($rows as $offset => $row) {
                $payload = array_combine($headers, array_slice(array_pad($row, count($headers), null), 0, count($headers)));
                $payload = collect($payload)->map(fn ($value) => is_string($value) ? trim($value) : $value)->all();
                if (collect($payload)->filter(fn ($value) => filled($value))->isEmpty()) {
                    continue;
                }

                $messages = [];
                $status = 'valid';
                $coreUserId = $payload['core_user_id'] ?? null;
                $studentNumber = $payload['npm'] ?? null;
                $groupCode = filled($payload['group_code'] ?? null) ? strtoupper((string) $payload['group_code']) : null;
                $dedupeKey = filled($coreUserId) ? 'core:'.$coreUserId : 'npm:'.$studentNumber;

                if (blank($coreUserId) && blank($studentNumber)) {
                    $status = 'invalid';
                    $messages[] = 'Core user ID atau NPM wajib diisi.';
                } elseif (isset($seen[$dedupeKey])) {
                    $status = 'duplicate_file';
                    $messages[] = 'Duplikat dalam file import.';
                }
                $seen[$dedupeKey] = true;

                $resolvedStudent = null;
                if ($status === 'valid') {
                    $resolved = $this->resolver->resolve(['core_user_id' => $coreUserId, 'student_number' => $studentNumber]);
                    if (! ($resolved['ok'] ?? false)) {
                        $status = $resolved['reason'] ?? 'invalid';
                        $messages[] = $resolved['message'] ?? 'Mahasiswa tidak valid.';
                        $resolvedStudent = $resolved['student'] ?? null;
                    } else {
                        $resolvedStudent = $resolved['student'];
                    }
                }

                if ($status === 'valid' && PkpaEnrollment::withTrashed()->where('pkpa_program_id', $program->id)->where('core_user_id', $resolvedStudent['core_user_id'])->exists()) {
                    $status = 'duplicate_database';
                    $messages[] = 'Sudah terdaftar pada program ini.';
                }

                if ($status === 'valid' && filled($groupCode)) {
                    $group = PkpaStudentGroup::where('pkpa_program_id', $program->id)->where('code', $groupCode)->first();
                    if (! $group) {
                        $status = 'group_not_found';
                        $messages[] = 'Kelompok tidak ditemukan.';
                    } elseif ($group->maximum_members && $group->activeMembers()->count() >= $group->maximum_members) {
                        $status = 'group_full';
                        $messages[] = 'Kelompok penuh.';
                    }
                }

                $batch->rows()->create([
                    'row_number' => $offset + 2,
                    'core_user_id' => $coreUserId,
                    'student_number' => $studentNumber,
                    'student_name' => $resolvedStudent['name'] ?? null,
                    'email' => $resolvedStudent['email'] ?? null,
                    'group_code' => $groupCode,
                    'raw_payload' => $payload,
                    'validation_status' => $status,
                    'validation_messages' => $messages ?: ['Siap diimpor.'],
                    'resolved_core_user_id' => $resolvedStudent['core_user_id'] ?? null,
                ]);
            }

            $this->summarize($batch, 'ready');
            $this->audit->record($actor, 'enrollment_import_validated', $batch, null, $batch->only(['total_rows', 'valid_rows', 'invalid_rows']));

            return $batch->load('rows');
        });
    }

    public function importValidRows(PkpaEnrollmentImportBatch $batch, ?User $actor): PkpaEnrollmentImportBatch
    {
        return DB::transaction(function () use ($batch, $actor) {
            $batch->update(['status' => 'importing']);
            $imported = 0;
            $skipped = 0;

            foreach ($batch->rows()->where('validation_status', 'valid')->whereNull('resolved_enrollment_id')->get() as $row) {
                try {
                    $group = filled($row->group_code)
                        ? PkpaStudentGroup::where('pkpa_program_id', $batch->pkpa_program_id)->where('code', $row->group_code)->first()
                        : null;
                    $enrollment = $this->enrollmentService->create($batch->program, [
                        'core_user_id' => $row->resolved_core_user_id,
                        'student_number' => $row->student_number,
                        'notes' => $row->raw_payload['notes'] ?? null,
                    ], $group, $actor);
                    $row->update(['resolved_enrollment_id' => $enrollment->id, 'imported_at' => now()]);
                    $imported++;
                } catch (\Throwable $exception) {
                    $row->update(['validation_status' => 'import_failed', 'validation_messages' => ['Gagal import row valid.']]);
                    $skipped++;
                }
            }

            $batch->update([
                'imported_rows' => $batch->imported_rows + $imported,
                'skipped_rows' => $batch->skipped_rows + $skipped,
                'status' => 'completed',
                'completed_at' => now(),
            ]);
            $this->summarize($batch, 'completed');
            $this->audit->record($actor, 'enrollment_import_completed', $batch, null, $batch->only(['imported_rows', 'skipped_rows']));

            return $batch->refresh()->load('rows');
        });
    }

    private function readRows(string $path, string $extension): array
    {
        if (in_array($extension, ['csv', 'txt'], true)) {
            $handle = fopen($path, 'r');
            $rows = [];
            while (($row = fgetcsv($handle)) !== false) {
                $rows[] = $row;
            }
            fclose($handle);

            return $rows;
        }

        $sheet = IOFactory::load($path)->getActiveSheet();

        return $sheet->toArray(null, true, true, false);
    }

    private function summarize(PkpaEnrollmentImportBatch $batch, string $status): void
    {
        $rows = $batch->rows();
        $batch->update([
            'status' => $status,
            'total_rows' => (clone $rows)->count(),
            'valid_rows' => (clone $rows)->where('validation_status', 'valid')->count(),
            'invalid_rows' => (clone $rows)->where('validation_status', '!=', 'valid')->count(),
        ]);
    }
}
