<?php

namespace App\Console\Commands;

use App\Models\PkpaPlacementPublication;
use App\Models\PkpaPracticeDomain;
use App\Models\PkpaPracticeSite;
use App\Models\PkpaRotationAssignmentSupervisor;
use App\Models\PkpaSiteFieldSupervisor;
use App\Models\User;
use App\Services\PkpaAuditService;
use App\Services\PkpaPlacementPublicationService;
use App\Services\PkpaRotationPublicationSyncService;
use App\Services\PkpaSupervisorCoreResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RevisePkpaPbfPlacement2026Command extends Command
{
    protected $signature = 'pkpa:revise-pbf-placement-2026
        {--program=PKPA-2026-G1 : Kode program PKPA}
        {--apply : Terapkan setelah hasil preview diperiksa}';

    protected $description = 'Merevisi kota dan Preseptor penempatan PBF 05 Oktober-06 November 2026 secara transaksional.';

    public function handle(
        PkpaSupervisorCoreResolver $resolver,
        PkpaPlacementPublicationService $publications,
        PkpaRotationPublicationSyncService $runtimeSync,
        PkpaAuditService $audit,
    ): int {
        $actor = User::query()->where('status', 'active')->whereHas('roles', fn ($query) => $query->where('name', 'koordinator_kp'))->first();
        $domain = PkpaPracticeDomain::query()->where('code', 'PBF')->where('is_active', true)->first();
        $publication = PkpaPlacementPublication::query()
            ->whereHas('program', fn ($query) => $query->where('code', $this->option('program')))
            ->current()
            ->with(['assignments.supervisors', 'assignments.sourceAssignment.supervisors', 'assignments.rotationRuns.currentPortfolio'])
            ->first();

        if (! $actor || ! $domain || ! $publication) {
            $this->error('Koordinator aktif, wahana PBF, atau publikasi current tidak ditemukan. Tidak ada perubahan yang dibuat.');

            return self::FAILURE;
        }

        $pbfAssignments = $publication->assignments->where('practice_domain_id', $domain->id)->values();
        $prepared = collect($this->roster())->map(function (array $item) use ($domain, $resolver, $pbfAssignments): array {
            $site = PkpaPracticeSite::query()
                ->where('practice_domain_id', $domain->id)
                ->whereIn('name', $item['aliases'])
                ->first();
            $resolved = $resolver->resolveField(['q' => $item['email']]);
            $assignmentCount = $site ? $pbfAssignments->where('practice_site_id', $site->id)->count() : 0;

            return $item + [
                'site' => $site,
                'resolved' => $resolved,
                'assignment_count' => $assignmentCount,
                'blocker' => ! $site || ! ($resolved['ok'] ?? false) || $assignmentCount === 0,
            ];
        });

        $coveredSiteIds = $prepared->pluck('site.id')->filter()->map(fn ($id) => (int) $id)->unique();
        $uncovered = $pbfAssignments->reject(fn ($assignment) => $coveredSiteIds->contains((int) $assignment->practice_site_id));

        $this->table(
            ['Tempat resmi', 'Kota', 'Preseptor', 'Akun Core', 'Mahasiswa', 'Status'],
            $prepared->map(fn (array $row) => [
                $row['name'],
                $row['city'],
                $row['preceptor_name'],
                ($row['resolved']['ok'] ?? false) ? $row['email'] : ($row['resolved']['message'] ?? 'Tidak ditemukan'),
                $row['assignment_count'],
                $row['blocker'] ? 'BLOCKER' : 'Siap',
            ])->all()
        );
        $this->line("Publikasi: {$publication->code}; penempatan PBF: {$pbfAssignments->count()}; tempat PBF: {$prepared->count()}.");

        if ($pbfAssignments->count() !== 36 || $prepared->contains('blocker', true) || $uncovered->isNotEmpty()) {
            $this->error('Preview belum aman: harus tepat 36 penempatan, 10 tempat, seluruh akun Core valid, dan tidak ada tempat yang terlewat. Tidak ada perubahan yang dibuat.');

            return self::FAILURE;
        }

        if (! $this->option('apply')) {
            $this->warn('Ini hanya preview. Tidak ada data yang diubah. Jalankan ulang dengan --apply jika seluruh data sudah benar.');

            return self::SUCCESS;
        }

        $result = DB::transaction(function () use ($prepared, $pbfAssignments, $publication, $actor, $publications, $runtimeSync, $audit): array {
            $replacements = [];

            foreach ($prepared as $item) {
                /** @var PkpaPracticeSite $site */
                $site = PkpaPracticeSite::query()->lockForUpdate()->findOrFail($item['site']->id);
                $beforeSite = $site->only(['name', 'legal_name', 'city', 'province', 'contact_person_name', 'contact_person_phone']);
                $site->update([
                    'name' => $item['name'],
                    'legal_name' => $item['name'],
                    'city' => $item['city'],
                    'province' => $item['province'],
                    'contact_person_name' => $item['preceptor_name'],
                    'contact_person_phone' => $item['phone'],
                    'updated_by_core_user_id' => $actor->core_user_id,
                ]);
                $audit->record($actor, 'pbf_practice_site_corrected', $site, $beforeSite, $site->only(array_keys($beforeSite)));

                $person = $item['resolved']['person'];
                $field = PkpaSiteFieldSupervisor::withTrashed()
                    ->where('practice_site_id', $site->id)
                    ->where('core_user_id', $person['core_user_id'])
                    ->first() ?: new PkpaSiteFieldSupervisor;
                if ($field->exists && $field->trashed()) {
                    $field->restore();
                }
                $field->fill([
                    'practice_site_id' => $site->id,
                    'core_user_id' => $person['core_user_id'],
                    'name_snapshot' => $person['name'],
                    'email_snapshot' => $person['email'],
                    'professional_id_snapshot' => $person['professional_id'],
                    'core_account_status_snapshot' => $person['account_status'],
                    'role_snapshot' => $person['role_snapshot'],
                    'position_title' => 'Preseptor PKPA',
                    'is_primary_contact' => true,
                    'maximum_active_students' => $item['assignment_count'],
                    'effective_start_date' => null,
                    'effective_end_date' => null,
                    'status' => 'active',
                    'notes' => 'Preseptor PBF periode 05 Oktober-06 November 2026.',
                    'last_core_synced_at' => now(),
                    'last_core_sync_status' => 'success',
                    'last_core_sync_message' => null,
                    'created_by_core_user_id' => $field->exists ? $field->created_by_core_user_id : $actor->core_user_id,
                    'updated_by_core_user_id' => $actor->core_user_id,
                ])->save();

                foreach ($pbfAssignments->where('practice_site_id', $site->id) as $publishedAssignment) {
                    $source = $publishedAssignment->sourceAssignment;
                    if ($source) {
                        $source->supervisors()->where('supervisor_type', 'field')->where('status', 'active')->delete();
                        PkpaRotationAssignmentSupervisor::query()->create([
                            'pkpa_rotation_assignment_id' => $source->id,
                            'supervisor_type' => 'field',
                            'site_field_supervisor_id' => $field->id,
                            'core_user_id' => $field->core_user_id,
                            'name_snapshot' => $field->name_snapshot,
                            'role_snapshot' => $field->role_snapshot,
                            'effective_start_date' => $source->start_date?->toDateString(),
                            'effective_end_date' => $source->end_date?->toDateString(),
                            'status' => 'active',
                            'is_primary' => true,
                            'created_by_core_user_id' => $actor->core_user_id,
                            'updated_by_core_user_id' => $actor->core_user_id,
                        ]);
                    }

                    $replacements[$publishedAssignment->id] = array_merge($publishedAssignment->toArray(), [
                        'practice_site_name_snapshot' => $site->name,
                        'practice_site_address_snapshot' => $site->address,
                        'site_field_supervisor_id' => $field->id,
                    ]);
                }
            }

            $revision = $publications->createRevisionFromPublication($publication, $replacements, $actor, 'placement_pbf_preceptor_revised');
            $sync = $runtimeSync->sync($revision, $actor);

            return ['publication' => $revision->code, 'sync' => $sync];
        });

        $this->info('Revisi PBF berhasil diterbitkan: '.$result['publication']);
        $this->line('Sinkronisasi runtime: '.json_encode($result['sync'], JSON_UNESCAPED_UNICODE));
        $this->info('Penempatan Apotek, presensi, logbook, portofolio, dan status baca yang tidak berubah tetap dipertahankan.');

        return self::SUCCESS;
    }

    private function roster(): array
    {
        return [
            $this->site('PT. Bina San Prima Bekasi', ['PT. Bina San Prima Bekasi'], 'Bekasi', 'Jawa Barat', 'apt. Chindy Dwi Martinah, S.Farm.', 'chindy@preseptor.safaubp.com', '085710992245'),
            $this->site('PT. Penta Valent Cirebon', ['PT. Penta Valent Cirebon'], 'Cirebon', 'Jawa Barat', 'apt. Marthyana Ayuningtyas, S.Farm.', 'marthyana@preseptor.safaubp.com', '082126501214'),
            $this->site('PT. Alida Bandung', ['PT. Alida', 'PT. Alida Bandung'], 'Bandung', 'Jawa Barat', 'apt. Dra. Siti Asniar Farisya, S.Si., M.Kes', 'sitiasniar@preseptor.safaubp.com', '081320471374'),
            $this->site('PT. Bina San Prima Bogor', ['PT. Bina San Prima Bogor'], 'Bogor', 'Jawa Barat', 'apt. Adela Adam Abdullah, S.Farm.', 'adela@preseptor.safaubp.com', '087852599790'),
            $this->site('PT. Bina San Prima Pulogadung', ['PT. Bina San Prima Pulogadung'], 'Jakarta Timur', 'DKI Jakarta', 'apt. Nurul Istimala, S.Farm.', 'nurul@preseptor.safaubp.com', '085210249025'),
            $this->site('PT. Bina San Prima Karawang', ['PT. Bina San Prima Karawang'], 'Karawang', 'Jawa Barat', 'apt. Dara Cynthia Utami, S.Farm.', 'dara@preseptor.safaubp.com', '085695252673'),
            $this->site('PT. Bina San Prima Tangerang', ['PT. Bina San Prima Tangerang'], 'Tangerang', 'Banten', 'apt. Fathimah Nurmajdina, S.Farm.', 'fathimah@preseptor.safaubp.com', '085773845313'),
            $this->site('PT. Bina San Prima Depok', ['PT. Bina San Prima Depok'], 'Depok', 'Jawa Barat', 'apt. Dona Waras Sakti, S.Farm.', 'dona@preseptor.safaubp.com', '087872700169'),
            $this->site('PT. Bina San Prima Tasikmalaya', ['PT. Bina San Prima Tasikmalaya'], 'Tasikmalaya', 'Jawa Barat', 'apt. Ari Suwanda Johari, S.Farm.', 'arisuwanda@preseptor.safaubp.com', '085223449954'),
            $this->site('PT. Bina San Prima Cirebon', ['PT. Bina San Prima Cirebon'], 'Cirebon', 'Jawa Barat', 'apt. Budiyawan, S.Farm.', 'budiyawan@preseptor.safaubp.com', '087728833322'),
        ];
    }

    private function site(string $name, array $aliases, string $city, string $province, string $preceptorName, string $email, string $phone): array
    {
        return compact('name', 'aliases', 'city', 'province', 'email', 'phone') + [
            'preceptor_name' => $preceptorName,
        ];
    }
}
