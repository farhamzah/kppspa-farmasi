<?php

namespace App\Console\Commands;

use App\Models\Core\CoreLecturer;
use App\Models\Core\CoreStudent;
use App\Models\Core\CoreUser;
use App\Models\Core\CoreUserAppAccess;
use App\Services\CoreIdentityService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class CoreHealthCheckCommand extends Command
{
    protected $signature = 'kp:core-health-check';

    protected $description = 'Read-only diagnostic for KP connection to Core identity and master data';

    public function handle(CoreIdentityService $coreIdentity): int
    {
        $this->info('KP Core read integration health check');
        $this->line('Mode: read-only diagnostic; no writes performed.');

        $kpConnected = $this->checkConnection('KP DB', DB::connection());
        $coreConnected = $this->checkConnection('Core DB', DB::connection('core'));

        if (! $kpConnected || ! $coreConnected) {
            return self::FAILURE;
        }

        $summary = $coreIdentity->getKpUsersSummary();
        $appCode = (string) config('core_farmasi.app_code', 'kppspa-farmasi');
        $this->newLine();
        $this->line('Core counts:');
        $this->line('  users: '.$summary['users']);
        $this->line('  students: '.$summary['students']);
        $this->line('  lecturers: '.$summary['lecturers']);
        $this->line("  user_app_accesses {$appCode}: ".$summary['kp_app_accesses']);

        $admin = $coreIdentity->findUserByEmail('admin@sikp.test');
        $adminRoles = $admin ? $coreIdentity->getUserRoles($admin->id)->pluck('name')->values()->all() : [];
        $this->newLine();
        $this->line('Role/access validation:');
        $this->line('  admin@sikp.test found: '.($admin ? 'yes' : 'no'));
        $this->line('  admin@sikp.test roles: '.($adminRoles ? implode(', ', $adminRoles) : 'none'));
        $this->line('  admin@sikp.test has admin-kp: '.(in_array('admin-kp', $adminRoles, true) ? 'yes' : 'no'));
        $this->line('  admin@sikp.test has admin-core: '.(in_array('admin-core', $adminRoles, true) ? 'yes' : 'no'));

        $student = CoreStudent::query()->with('user')->orderBy('id')->first();
        $lecturer = CoreLecturer::query()->with('user')->orderBy('id')->first();
        $fieldSupervisor = $coreIdentity->findUserByEmail('lapangan@sikp.test');
        $fieldSupervisorAccess = $fieldSupervisor
            ? $coreIdentity->userHasAppAccess($fieldSupervisor->id, $appCode, 'pembimbing-lapangan')
            : false;
        $localFieldSupervisorProfile = DB::table('field_supervisors')
            ->join('users', 'users.id', '=', 'field_supervisors.user_id')
            ->whereRaw('LOWER(TRIM(users.email)) = ?', ['lapangan@sikp.test'])
            ->exists();

        $this->newLine();
        $this->line('Samples:');
        $this->line('  student sample: '.($student ? $student->student_number.' / '.$student->user?->email : 'none'));
        $this->line('  lecturer sample: '.($lecturer ? $lecturer->lecturer_number.' / '.$lecturer->user?->email : 'none'));
        $this->line('  field supervisor Core identity: '.($fieldSupervisor ? 'yes' : 'no'));
        $this->line("  field supervisor Core {$appCode} pembimbing-lapangan access: ".($fieldSupervisorAccess ? 'yes' : 'no'));
        $this->line('  field supervisor profile remains KP-local: '.($localFieldSupervisorProfile ? 'yes' : 'no'));

        if (! $admin || ! in_array('admin-kp', $adminRoles, true) || in_array('admin-core', $adminRoles, true) || ! $fieldSupervisor || ! $fieldSupervisorAccess || ! $localFieldSupervisorProfile) {
            $this->error('Core health check failed validation.');

            return self::FAILURE;
        }

        $this->info('Core health check passed.');

        return self::SUCCESS;
    }

    private function checkConnection(string $label, $connection): bool
    {
        try {
            $connection->getPdo();
            $this->line("  {$label}: connected");

            return true;
        } catch (Throwable $exception) {
            $this->error("  {$label}: failed - {$exception->getMessage()}");

            return false;
        }
    }
}
