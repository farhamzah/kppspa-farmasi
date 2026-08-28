<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\KpPeriod;
use App\Models\KpPlaceQuota;
use App\Models\KpRegistration;
use App\Services\KpPlaceSelectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlaceSelectionController extends Controller
{
    public function index(Request $request): View
    {
        $registration = $this->latestVerifiedRegistration($request);
        $periods = KpPeriod::query()
            ->whereHas('registrations', fn ($query) => $query->where('student_id', $request->user()->student?->id)->where('status', 'terverifikasi'))
            ->latest()
            ->get();

        return view('student.place-selections.index', [
            'registration' => $registration,
            'periods' => $periods,
            'serverNow' => now(),
        ]);
    }

    public function show(Request $request, KpPeriod $period): View
    {
        $registration = KpRegistration::query()
            ->with(['period', 'student.user', 'documents', 'activePlaceSelection.place', 'waitingList'])
            ->where('kp_period_id', $period->id)
            ->where('student_id', $request->user()->student?->id)
            ->latest()
            ->first();

        abort_unless($registration, 404);

        $quotas = KpPlaceQuota::query()
            ->with(['place', 'period'])
            ->where('kp_period_id', $period->id)
            ->whereHas('place', fn ($query) => $query->where('status', 'aktif'))
            ->orderByDesc('is_open')
            ->get();

        return view('student.place-selections.show', [
            'registration' => $registration,
            'period' => $period,
            'quotas' => $quotas,
            'serverNow' => now(),
        ]);
    }

    public function select(Request $request, KpPlaceQuota $quota, KpPlaceSelectionService $service): RedirectResponse
    {
        $registration = KpRegistration::query()
            ->with(['period.documentRequirements', 'documents'])
            ->where('kp_period_id', $quota->kp_period_id)
            ->where('student_id', $request->user()->student?->id)
            ->where('status', 'terverifikasi')
            ->first();

        if (! $registration) {
            return back()->withErrors(['selection' => 'Pendaftaran Anda belum terverifikasi.']);
        }

        $selection = $service->selectPlace($request->user(), $registration, $quota, $request->ip(), $request->userAgent());

        return redirect()->route('student.place-selections.show', $selection->period)->with('status', 'Tempat PKPA berhasil dikonfirmasi. Perubahan selanjutnya dilakukan melalui admin atau koordinator.');
    }

    public function joinWaitingList(Request $request, KpPlaceSelectionService $service): RedirectResponse
    {
        $registration = $this->latestVerifiedRegistration($request);
        if (! $registration || ! $registration->isEligibleForPlaceSelection()) {
            return back()->withErrors(['selection' => 'Pendaftaran Anda belum terverifikasi.']);
        }

        $service->joinWaitingListIfNeeded($registration, $request->ip(), $request->userAgent(), $request->user());

        return back()->with('status', 'Anda sudah masuk daftar tunggu periode ini.');
    }

    private function latestVerifiedRegistration(Request $request): ?KpRegistration
    {
        return $request->user()->student?->kpRegistrations()
            ->with(['period', 'documents', 'activePlaceSelection.place', 'waitingList'])
            ->where('status', 'terverifikasi')
            ->latest()
            ->first();
    }
}
