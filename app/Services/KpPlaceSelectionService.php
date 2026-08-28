<?php

namespace App\Services;

use App\Models\KpPlaceQuota;
use App\Models\KpPlaceSelection;
use App\Models\KpRegistration;
use App\Models\KpSelectionLog;
use App\Models\KpWaitingList;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class KpPlaceSelectionService
{
    public function selectPlaceManually(User $admin, KpRegistration $registration, KpPlaceQuota $quota, string $reason): KpPlaceSelection
    {
        return DB::transaction(function () use ($admin, $registration, $quota, $reason) {
            $lockedRegistration = KpRegistration::query()
                ->with(['period.documentRequirements', 'documents', 'assignment'])
                ->lockForUpdate()
                ->findOrFail($registration->id);

            $lockedQuota = KpPlaceQuota::query()
                ->with(['place', 'period'])
                ->lockForUpdate()
                ->findOrFail($quota->id);

            if (! $lockedRegistration->isEligibleForPlaceSelection()) {
                $this->logSelection($lockedRegistration, $lockedQuota, $admin, 'manual_selection_failed_not_verified', 'failed', 'Pendaftaran belum terverifikasi lengkap.');
                throw ValidationException::withMessages(['kp_registration_id' => 'Mahasiswa belum terverifikasi atau dokumen wajib belum disetujui.']);
            }

            if ($lockedRegistration->kp_period_id !== $lockedQuota->kp_period_id) {
                $this->logSelection($lockedRegistration, $lockedQuota, $admin, 'manual_selection_failed_invalid_period', 'failed', 'Kuota tempat tidak berada pada periode pendaftaran mahasiswa.');
                throw ValidationException::withMessages(['kp_place_quota_id' => 'Kuota tempat harus berada pada periode yang sama dengan pendaftaran mahasiswa.']);
            }

            if (! $lockedQuota->is_open) {
                $this->logSelection($lockedRegistration, $lockedQuota, $admin, 'manual_selection_failed_quota_closed', 'failed', 'Kuota tujuan sedang ditutup.');
                throw ValidationException::withMessages(['kp_place_quota_id' => 'Kuota tujuan sedang ditutup.']);
            }

            if (KpPlaceSelection::where('kp_period_id', $lockedRegistration->kp_period_id)->where('student_id', $lockedRegistration->student_id)->where('status', 'aktif')->lockForUpdate()->exists()) {
                $this->logSelection($lockedRegistration, $lockedQuota, $admin, 'manual_selection_failed_already_selected', 'failed', 'Mahasiswa sudah memiliki pilihan tempat aktif.');
                throw ValidationException::withMessages(['kp_registration_id' => 'Mahasiswa sudah memiliki pilihan tempat aktif pada periode ini.']);
            }

            if ($lockedRegistration->assignment()->where('status', '!=', 'dibatalkan')->lockForUpdate()->exists()) {
                $this->logSelection($lockedRegistration, $lockedQuota, $admin, 'manual_selection_failed_assignment_exists', 'failed', 'Mahasiswa sudah memiliki penempatan PKPA aktif.');
                throw ValidationException::withMessages(['kp_registration_id' => 'Mahasiswa sudah memiliki penempatan PKPA aktif pada periode ini.']);
            }

            $filled = KpPlaceSelection::where('kp_place_quota_id', $lockedQuota->id)->where('status', 'aktif')->lockForUpdate()->count();
            if (($lockedQuota->quota - $filled) <= 0) {
                $this->logSelection($lockedRegistration, $lockedQuota, $admin, 'manual_selection_failed_quota_full', 'failed', 'Kuota tujuan sudah penuh.');
                throw ValidationException::withMessages(['kp_place_quota_id' => 'Kuota tujuan sudah penuh.']);
            }

            $selection = KpPlaceSelection::create([
                'kp_period_id' => $lockedRegistration->kp_period_id,
                'kp_registration_id' => $lockedRegistration->id,
                'student_id' => $lockedRegistration->student_id,
                'kp_place_id' => $lockedQuota->kp_place_id,
                'kp_place_quota_id' => $lockedQuota->id,
                'selected_at' => now(),
                'selected_by' => $admin->id,
                'status' => 'aktif',
                'active_key' => $this->activeKey($lockedRegistration->kp_period_id, $lockedRegistration->student_id),
                'note' => 'Penempatan manual oleh koordinator/admin: '.$reason,
            ]);

            KpWaitingList::where('kp_period_id', $lockedRegistration->kp_period_id)
                ->where('student_id', $lockedRegistration->student_id)
                ->update(['status' => 'sudah_memilih', 'resolved_at' => now(), 'note' => 'Diselesaikan melalui penempatan manual.']);

            $this->logSelection($lockedRegistration, $lockedQuota, $admin, 'selection_manual_by_koordinator', 'success', $reason, null, null, ['selection_id' => $selection->id]);

            return $selection;
        });
    }

    public function selectPlace(User $user, KpRegistration $registration, KpPlaceQuota $quota, ?string $ip = null, ?string $userAgent = null): KpPlaceSelection
    {
        if ($user->student?->id !== $registration->student_id) {
            $this->logSelection($registration, $quota, $user, 'selection_failed_invalid_period', 'failed', 'Pendaftaran tidak sesuai dengan akun mahasiswa.', $ip, $userAgent);
            throw ValidationException::withMessages(['selection' => 'Pendaftaran tidak sesuai dengan akun Anda.']);
        }

        if (! $registration->isEligibleForPlaceSelection()) {
            $this->logSelection($registration, $quota, $user, 'selection_failed_not_verified', 'failed', 'Pendaftaran Anda belum terverifikasi.', $ip, $userAgent);
            throw ValidationException::withMessages(['selection' => 'Pendaftaran Anda belum terverifikasi.']);
        }

        if ($registration->kp_period_id !== $quota->kp_period_id) {
            $this->logSelection($registration, $quota, $user, 'selection_failed_invalid_period', 'failed', 'Tempat tidak berada pada periode pendaftaran Anda.', $ip, $userAgent);
            throw ValidationException::withMessages(['selection' => 'Tempat tidak berada pada periode pendaftaran Anda.']);
        }

        if (! $registration->period->isSelectionOpen()) {
            $this->logSelection($registration, $quota, $user, 'selection_failed_not_open', 'failed', 'Jadwal pemilihan tempat belum dibuka atau sudah ditutup.', $ip, $userAgent);
            throw ValidationException::withMessages(['selection' => 'Jadwal pemilihan tempat belum dibuka atau sudah ditutup.']);
        }

        try {
            return DB::transaction(function () use ($user, $registration, $quota, $ip, $userAgent) {
                $lockedRegistration = KpRegistration::query()
                    ->with(['period.documentRequirements', 'documents'])
                    ->lockForUpdate()
                    ->findOrFail($registration->id);

                $lockedQuota = KpPlaceQuota::query()
                    ->with(['place', 'period'])
                    ->lockForUpdate()
                    ->findOrFail($quota->id);

                if (! $lockedRegistration->isEligibleForPlaceSelection()) {
                    $this->logSelection($lockedRegistration, $lockedQuota, $user, 'selection_failed_not_verified', 'failed', 'Pendaftaran Anda belum terverifikasi.', $ip, $userAgent);
                    throw ValidationException::withMessages(['selection' => 'Pendaftaran Anda belum terverifikasi.']);
                }

                if (! $lockedRegistration->period->isSelectionOpen()) {
                    $this->logSelection($lockedRegistration, $lockedQuota, $user, 'selection_failed_not_open', 'failed', 'Jadwal pemilihan tempat belum dibuka atau sudah ditutup.', $ip, $userAgent);
                    throw ValidationException::withMessages(['selection' => 'Jadwal pemilihan tempat belum dibuka atau sudah ditutup.']);
                }

                if (! $lockedQuota->is_open) {
                    $this->logSelection($lockedRegistration, $lockedQuota, $user, 'selection_failed_quota_closed', 'failed', 'Tempat ini sedang ditutup oleh Admin/Koordinator.', $ip, $userAgent);
                    throw ValidationException::withMessages(['selection' => 'Tempat ini sedang ditutup oleh Admin/Koordinator.']);
                }

                if (KpPlaceSelection::where('kp_period_id', $lockedRegistration->kp_period_id)->where('student_id', $lockedRegistration->student_id)->where('status', 'aktif')->lockForUpdate()->exists()) {
                    $this->logSelection($lockedRegistration, $lockedQuota, $user, 'selection_failed_already_selected', 'failed', 'Anda sudah memilih tempat PKPA.', $ip, $userAgent);
                    throw ValidationException::withMessages(['selection' => 'Anda sudah memilih tempat PKPA.']);
                }

                $filled = KpPlaceSelection::where('kp_place_quota_id', $lockedQuota->id)->where('status', 'aktif')->lockForUpdate()->count();
                if (($lockedQuota->quota - $filled) <= 0) {
                    $this->logSelection($lockedRegistration, $lockedQuota, $user, 'selection_failed_quota_full', 'failed', 'Kuota tempat ini baru saja penuh. Silakan pilih tempat lain.', $ip, $userAgent);
                    throw ValidationException::withMessages(['selection' => 'Kuota tempat ini baru saja penuh. Silakan pilih tempat lain.']);
                }

                $selection = KpPlaceSelection::create([
                    'kp_period_id' => $lockedRegistration->kp_period_id,
                    'kp_registration_id' => $lockedRegistration->id,
                    'student_id' => $lockedRegistration->student_id,
                    'kp_place_id' => $lockedQuota->kp_place_id,
                    'kp_place_quota_id' => $lockedQuota->id,
                    'selected_at' => now(),
                    'selected_by' => $user->id,
                    'status' => 'aktif',
                    'active_key' => $this->activeKey($lockedRegistration->kp_period_id, $lockedRegistration->student_id),
                ]);

                KpWaitingList::where('kp_period_id', $lockedRegistration->kp_period_id)
                    ->where('student_id', $lockedRegistration->student_id)
                    ->update(['status' => 'sudah_memilih', 'resolved_at' => now()]);

                $this->logSelection($lockedRegistration, $lockedQuota, $user, 'selection_success', 'success', 'Mahasiswa berhasil memilih tempat PKPA.', $ip, $userAgent, ['selection_id' => $selection->id]);

                return $selection;
            });
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first() ?: 'Pemilihan tempat PKPA gagal.';
            $action = $this->actionFromMessage($message);
            if ($action === 'selection_failed_quota_full') {
                $this->joinWaitingListIfNeeded($registration, $ip, $userAgent, $user);
            }
            $this->logSelection($registration, $quota, $user, $action, 'failed', $message, $ip, $userAgent);
            throw $exception;
        } catch (Throwable $exception) {
            $this->logSelection($registration, $quota, $user, 'selection_failed_system_error', 'failed', 'Terjadi kendala sistem saat memilih tempat PKPA.', $ip, $userAgent, ['error' => $exception->getMessage()]);
            throw ValidationException::withMessages(['selection' => 'Terjadi kendala sistem. Silakan coba lagi.']);
        }
    }

    public function cancelSelection(User $admin, KpPlaceSelection $selection, string $reason): void
    {
        DB::transaction(function () use ($admin, $selection, $reason) {
            $locked = KpPlaceSelection::query()->with('assignment')->lockForUpdate()->findOrFail($selection->id);
            if ($locked->status !== 'aktif') {
                throw ValidationException::withMessages(['selection' => 'Pilihan ini sudah tidak aktif.']);
            }

            if ($locked->assignment && $locked->assignment->status !== 'dibatalkan') {
                throw ValidationException::withMessages([
                    'selection' => 'Pilihan ini sudah menjadi penempatan PKPA. Batalkan dari halaman Penempatan PKPA agar pembimbing ikut dilepas.',
                ]);
            }

            $locked->update([
                'status' => 'dibatalkan',
                'cancelled_by' => $admin->id,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
                'active_key' => null,
            ]);

            $this->logSelection($locked->registration, $locked->quota, $admin, 'selection_cancelled_by_admin', 'info', $reason, null, null, ['selection_id' => $locked->id]);
        });
    }

    public function moveSelection(User $admin, KpPlaceSelection $selection, KpPlaceQuota $newQuota, string $reason): KpPlaceSelection
    {
        return DB::transaction(function () use ($admin, $selection, $newQuota, $reason) {
            $old = KpPlaceSelection::query()->lockForUpdate()->findOrFail($selection->id);
            if ($old->status !== 'aktif') {
                throw ValidationException::withMessages(['selection' => 'Pilihan ini sudah tidak aktif.']);
            }

            $lockedQuota = KpPlaceQuota::query()->with('place')->lockForUpdate()->findOrFail($newQuota->id);
            if ($lockedQuota->kp_period_id !== $old->kp_period_id) {
                throw ValidationException::withMessages(['kp_place_quota_id' => 'Kuota tujuan harus berada pada periode yang sama.']);
            }
            if (! $lockedQuota->is_open) {
                throw ValidationException::withMessages(['kp_place_quota_id' => 'Kuota tujuan sedang ditutup.']);
            }

            $filled = KpPlaceSelection::where('kp_place_quota_id', $lockedQuota->id)->where('status', 'aktif')->lockForUpdate()->count();
            if (($lockedQuota->quota - $filled) <= 0) {
                throw ValidationException::withMessages(['kp_place_quota_id' => 'Kuota tujuan sudah penuh.']);
            }

            $old->update([
                'status' => 'dipindahkan',
                'cancelled_by' => $admin->id,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
                'active_key' => null,
            ]);

            $new = KpPlaceSelection::create([
                'kp_period_id' => $old->kp_period_id,
                'kp_registration_id' => $old->kp_registration_id,
                'student_id' => $old->student_id,
                'kp_place_id' => $lockedQuota->kp_place_id,
                'kp_place_quota_id' => $lockedQuota->id,
                'selected_at' => now(),
                'selected_by' => $admin->id,
                'status' => 'aktif',
                'moved_from_selection_id' => $old->id,
                'active_key' => $this->activeKey($old->kp_period_id, $old->student_id),
                'note' => $reason,
            ]);

            KpWaitingList::where('kp_period_id', $old->kp_period_id)
                ->where('student_id', $old->student_id)
                ->update(['status' => 'sudah_memilih', 'resolved_at' => now()]);

            $this->logSelection($old->registration, $lockedQuota, $admin, 'selection_moved_by_admin', 'info', $reason, null, null, [
                'from_selection_id' => $old->id,
                'to_selection_id' => $new->id,
            ]);

            return $new;
        });
    }

    public function joinWaitingListIfNeeded(KpRegistration $registration, ?string $ip = null, ?string $userAgent = null, ?User $user = null): KpWaitingList
    {
        $waitingList = KpWaitingList::updateOrCreate(
            [
                'kp_period_id' => $registration->kp_period_id,
                'student_id' => $registration->student_id,
            ],
            [
                'kp_registration_id' => $registration->id,
                'joined_at' => now(),
                'status' => 'menunggu',
                'note' => 'Menunggu kapasitas tempat PKPA tersedia.',
            ]
        );

        $this->logSelection($registration, null, $user, 'waiting_list_joined', 'info', 'Mahasiswa masuk daftar tunggu.', $ip, $userAgent);

        return $waitingList;
    }

    public function logSelection(KpRegistration $registration, ?KpPlaceQuota $quota, ?User $user, string $action, string $status, string $message, ?string $ip = null, ?string $userAgent = null, array $metadata = []): void
    {
        KpSelectionLog::create([
            'kp_period_id' => $registration->kp_period_id,
            'kp_registration_id' => $registration->id,
            'student_id' => $registration->student_id,
            'kp_place_id' => $quota?->kp_place_id,
            'kp_place_quota_id' => $quota?->id,
            'user_id' => $user?->id,
            'action' => $action,
            'status' => $status,
            'message' => $message,
            'metadata' => $metadata ?: null,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
        ]);
    }

    private function activeKey(int $periodId, int $studentId): string
    {
        return $periodId.'-'.$studentId;
    }

    private function actionFromMessage(string $message): string
    {
        return match (true) {
            str_contains($message, 'belum terverifikasi') => 'selection_failed_not_verified',
            str_contains($message, 'belum dibuka') || str_contains($message, 'sudah ditutup') => 'selection_failed_not_open',
            str_contains($message, 'sudah memilih') => 'selection_failed_already_selected',
            str_contains($message, 'penuh') => 'selection_failed_quota_full',
            str_contains($message, 'ditutup') => 'selection_failed_quota_closed',
            str_contains($message, 'periode') => 'selection_failed_invalid_period',
            default => 'selection_failed_system_error',
        };
    }
}
