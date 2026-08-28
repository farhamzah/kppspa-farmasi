<?php

namespace App\Services;

use App\Exports\UserTemplateExport;
use App\Imports\UsersImport;
use App\Models\FieldSupervisor;
use App\Models\Lecturer;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use App\Models\UserImportBatch;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class UserImportService
{
    public const TYPES = ['mahasiswa', 'dosen', 'pembimbing_lapangan', 'mixed'];

    public const DOSEN_ROLES = ['koordinator_kp', 'pembimbing_dalam', 'penguji'];

    public function parseFile(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if ($extension === 'csv') {
            return $this->parseCsv($file);
        }

        $sheets = Excel::toArray(new UsersImport, $file);

        return collect($sheets[0] ?? [])
            ->map(fn (array $row) => $this->normalizeRow($row))
            ->filter(fn (array $row) => $this->rowHasValue($row))
            ->values()
            ->all();
    }

    public function preview(string $type, array $rows): array
    {
        $emailSeen = [];
        $nimSeen = [];
        $nidnSeen = [];
        $items = [];

        foreach ($rows as $index => $row) {
            $row = $this->normalizeRow($row);
            $rowNumber = $index + 2;
            $normalized = $this->mapRow($type, $row);
            $errors = $this->validateRow($normalized, $emailSeen, $nimSeen, $nidnSeen);

            if (filled($normalized['email'])) {
                $emailSeen[] = Str::lower($normalized['email']);
            }

            if ($normalized['profile_type'] === 'mahasiswa' && filled($normalized['nim'])) {
                $nimSeen[] = Str::lower($normalized['nim']);
            }

            if ($normalized['profile_type'] === 'dosen' && filled($normalized['nidn_nip'])) {
                $nidnSeen[] = Str::lower($normalized['nidn_nip']);
            }

            $items[] = [
                'row_number' => $rowNumber,
                'data' => $normalized,
                'valid' => empty($errors),
                'errors' => $errors,
            ];
        }

        return $items;
    }

    public function process(string $type, array $rows, User $importedBy, ?string $filename = null): UserImportBatch
    {
        $preview = $this->preview($type, $rows);

        return DB::transaction(function () use ($type, $preview, $importedBy, $filename) {
            $batch = UserImportBatch::create([
                'imported_by' => $importedBy->id,
                'import_type' => $type,
                'original_filename' => $filename,
                'total_rows' => count($preview),
                'success_rows' => 0,
                'failed_rows' => 0,
                'status' => 'processing',
                'notes' => 'Password awal development: password. User wajib mengganti password saat login pertama.',
            ]);

            $success = 0;
            $failed = 0;

            foreach ($preview as $item) {
                if (! $item['valid']) {
                    $failed++;
                    $this->storeError($batch, $item);

                    continue;
                }

                $this->createUserFromRow($item['data']);
                $success++;
            }

            $batch->update([
                'success_rows' => $success,
                'failed_rows' => $failed,
                'status' => $failed > 0 ? 'completed_with_errors' : 'completed',
            ]);

            return $batch->fresh(['errors', 'importedBy']);
        });
    }

    public function templateRows(string $type): array
    {
        return match ($type) {
            'mahasiswa' => [
                ['nim', 'name', 'email', 'study_program', 'semester', 'class_name', 'phone'],
                ['221010001', 'Mahasiswa Contoh', 'mahasiswa.contoh@sikp.test', 'Farmasi', '6', 'A', '081234567890'],
            ],
            'dosen' => [
                ['nidn_nip', 'employee_number', 'name', 'email', 'study_program', 'department', 'expertise', 'phone', 'roles'],
                ['0012345601', 'DOS001', 'Dosen Contoh', 'dosen.contoh@sikp.test', 'Farmasi', 'Farmasi Klinis', 'Farmakologi', '081234567891', 'pembimbing_dalam,penguji'],
            ],
            'pembimbing_lapangan' => [
                ['name', 'email', 'institution_name', 'position', 'phone'],
                ['Preseptor Contoh', 'lapangan.contoh@sikp.test', 'Apotek Sehat', 'Apoteker Penanggung Jawab', '081234567892'],
            ],
            default => [
                ['profile_type', 'identifier', 'name', 'email', 'roles', 'phone', 'study_program', 'semester', 'class_name', 'institution_name', 'position', 'nidn_nip', 'employee_number', 'department', 'expertise'],
                ['mahasiswa', '221010002', 'Mahasiswa Mixed', 'mixed.mahasiswa@sikp.test', '', '081234567893', 'Farmasi', '6', 'B', '', '', '', '', '', ''],
                ['dosen', '', 'Dosen Mixed', 'mixed.dosen@sikp.test', 'pembimbing_dalam,penguji', '081234567894', 'Farmasi', '', '', '', '', '0012345602', 'DOS002', 'Farmasi Klinis', 'Farmakologi'],
            ],
        };
    }

    public function templateExport(string $type): UserTemplateExport
    {
        return new UserTemplateExport($this->templateRows($type));
    }

    private function parseCsv(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'r');
        $headers = [];
        $rows = [];

        while (($data = fgetcsv($handle)) !== false) {
            if ($headers === []) {
                $headers = array_map(fn ($value) => Str::snake(trim((string) $value)), $data);

                continue;
            }

            $row = [];
            foreach ($headers as $index => $header) {
                $row[$header] = $data[$index] ?? null;
            }
            $row = $this->normalizeRow($row);

            if ($this->rowHasValue($row)) {
                $rows[] = $row;
            }
        }

        fclose($handle);

        return $rows;
    }

    private function normalizeRow(array $row): array
    {
        return collect($row)
            ->mapWithKeys(fn ($value, $key) => [Str::snake(trim((string) $key)) => is_string($value) ? trim($value) : $value])
            ->all();
    }

    private function rowHasValue(array $row): bool
    {
        return collect($row)->filter(fn ($value) => filled($value))->isNotEmpty();
    }

    private function mapRow(string $type, array $row): array
    {
        $profileType = $type === 'mixed' ? Str::snake((string) Arr::get($row, 'profile_type')) : $type;
        $identifier = Arr::get($row, 'identifier');

        if ($profileType === 'mahasiswa' && blank(Arr::get($row, 'nim'))) {
            $row['nim'] = $identifier;
        }

        return [
            'profile_type' => $profileType,
            'name' => Arr::get($row, 'name'),
            'email' => Str::lower((string) Arr::get($row, 'email')),
            'roles' => $this->rolesFor($profileType, Arr::get($row, 'roles')),
            'nim' => Arr::get($row, 'nim'),
            'nidn_nip' => Arr::get($row, 'nidn_nip'),
            'employee_number' => Arr::get($row, 'employee_number'),
            'study_program' => Arr::get($row, 'study_program'),
            'semester' => Arr::get($row, 'semester'),
            'class_name' => Arr::get($row, 'class_name'),
            'department' => Arr::get($row, 'department'),
            'expertise' => Arr::get($row, 'expertise'),
            'institution_name' => Arr::get($row, 'institution_name'),
            'position' => Arr::get($row, 'position'),
            'phone' => Arr::get($row, 'phone'),
        ];
    }

    private function rolesFor(string $profileType, mixed $roles): array
    {
        $parsed = collect(explode(',', (string) $roles))
            ->map(fn ($role) => Str::snake(trim($role)))
            ->filter()
            ->values()
            ->all();

        return match ($profileType) {
            'mahasiswa' => $parsed ?: ['mahasiswa'],
            'pembimbing_lapangan' => $parsed ?: ['pembimbing_lapangan'],
            'admin' => $parsed ?: ['admin'],
            'dosen' => $parsed ?: ['pembimbing_dalam'],
            default => $parsed,
        };
    }

    private function validateRow(array $row, array $emailSeen, array $nimSeen, array $nidnSeen): array
    {
        $errors = [];
        $validTypes = ['mahasiswa', 'dosen', 'pembimbing_lapangan', 'admin'];

        if (! in_array($row['profile_type'], $validTypes, true)) {
            $errors[] = 'Tipe profil tidak valid.';
        }

        if (blank($row['name'])) {
            $errors[] = 'Nama wajib diisi.';
        }

        if (blank($row['email'])) {
            $errors[] = 'Email wajib diisi.';
        } elseif (! filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Format email tidak valid.';
        } elseif (User::where('email', $row['email'])->exists()) {
            $errors[] = 'Email sudah terdaftar di database.';
        } elseif (in_array(Str::lower($row['email']), $emailSeen, true)) {
            $errors[] = 'Email duplikat di file import.';
        }

        $validRoles = Role::pluck('name')->all();
        foreach ($row['roles'] as $role) {
            if (! in_array($role, $validRoles, true)) {
                $errors[] = "Role {$role} tidak valid.";
            }
        }

        if ($row['profile_type'] === 'dosen') {
            if (empty($row['roles'])) {
                $errors[] = 'Role dosen wajib diisi.';
            }

            foreach ($row['roles'] as $role) {
                if (! in_array($role, self::DOSEN_ROLES, true)) {
                    $errors[] = 'Role dosen hanya boleh koordinator_kp, pembimbing_dalam, atau penguji.';
                    break;
                }
            }

            if (filled($row['nidn_nip'])) {
                $nidn = Str::lower($row['nidn_nip']);
                if (Lecturer::where('nidn_nip', $row['nidn_nip'])->exists()) {
                    $errors[] = 'NIDN/NIP sudah terdaftar di database.';
                } elseif (in_array($nidn, $nidnSeen, true)) {
                    $errors[] = 'NIDN/NIP duplikat di file import.';
                }
            }
        }

        if ($row['profile_type'] === 'mahasiswa') {
            if (blank($row['nim'])) {
                $errors[] = 'NIM wajib diisi untuk mahasiswa.';
            } else {
                $nim = Str::lower($row['nim']);
                if (Student::where('nim', $row['nim'])->exists()) {
                    $errors[] = 'NIM sudah terdaftar di database.';
                } elseif (in_array($nim, $nimSeen, true)) {
                    $errors[] = 'NIM duplikat di file import.';
                }
            }
        }

        if ($row['profile_type'] === 'pembimbing_lapangan' && blank($row['institution_name'])) {
            $errors[] = 'Nama institusi wajib diisi untuk preseptor.';
        }

        return $errors;
    }

    private function createUserFromRow(array $row): User
    {
        $user = User::create([
            'name' => $row['name'],
            'email' => $row['email'],
            'password' => Hash::make('password'),
            'status' => 'active',
            'must_change_password' => true,
            'profile_completed' => false,
        ]);

        $roleIds = Role::whereIn('name', $row['roles'])->pluck('id');
        $user->roles()->sync($roleIds);

        match ($row['profile_type']) {
            'mahasiswa' => Student::create([
                'user_id' => $user->id,
                'nim' => $row['nim'],
                'study_program' => $row['study_program'],
                'semester' => $row['semester'] ?: null,
                'class_name' => $row['class_name'],
                'phone' => $row['phone'],
            ]),
            'dosen' => Lecturer::create([
                'user_id' => $user->id,
                'nidn_nip' => $row['nidn_nip'],
                'employee_number' => $row['employee_number'],
                'study_program' => $row['study_program'],
                'department' => $row['department'],
                'expertise' => $row['expertise'],
                'phone' => $row['phone'],
            ]),
            'pembimbing_lapangan' => FieldSupervisor::create([
                'user_id' => $user->id,
                'institution_name' => $row['institution_name'],
                'position' => $row['position'],
                'phone' => $row['phone'],
            ]),
            default => null,
        };

        return $user;
    }

    private function storeError(UserImportBatch $batch, array $item): void
    {
        $batch->errors()->create([
            'row_number' => $item['row_number'],
            'identifier' => $item['data']['email'] ?: ($item['data']['nim'] ?: $item['data']['nidn_nip']),
            'error_message' => implode(' ', $item['errors']),
            'row_data' => $item['data'],
        ]);
    }
}
