<?php

namespace App\Http\Controllers;

use App\Services\CoreFarmasiClient;
use App\Services\CoreProfileReadService;
use App\Services\KpCoreBridgeProvisioningService;
use App\Models\KpPlace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\File;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProfileController extends Controller
{
    public function __construct(
        private readonly CoreFarmasiClient $coreFarmasi,
        private readonly CoreProfileReadService $coreProfiles,
    ) {
    }

    public function show(Request $request): View
    {
        $user = $request->user()->load(['roles', 'student', 'lecturer', 'fieldSupervisor.places']);
        $profileType = $user->activeProfileType();
        $profile = $user->profileModelForType($profileType);
        $coreOfficialProfile = $this->coreProfiles->officialProfileFor($user, $profileType);

        return view('profile.show', [
            'user' => $user,
            'profileType' => $profileType,
            'profile' => $profile,
            'coreProfileUrl' => $this->coreFarmasi->profileEditUrl(),
            'coreOfficialProfile' => $coreOfficialProfile,
            'operationalAttributes' => $this->operationalAttributes($profile, $profileType, (bool) $coreOfficialProfile),
        ]);
    }

    public function edit(Request $request): View
    {
        $user = $request->user()->load(['roles', 'student', 'lecturer', 'fieldSupervisor.places']);
        $profileType = $user->activeProfileType();

        return view('profile.edit', [
            'user' => $user,
            'profileType' => $profileType,
            'profile' => $user->profileModelForType($profileType),
            'coreProfileUrl' => $this->coreFarmasi->profileEditUrl(),
            'coreOfficialProfile' => $this->coreProfiles->officialProfileFor($user, $profileType),
            'availablePlaces' => $profileType === 'pembimbing_lapangan'
                ? KpPlace::query()->where('status', 'aktif')->orderBy('name')->get()
                : collect(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user()->load(['roles', 'student', 'lecturer', 'fieldSupervisor']);
        $type = $user->activeProfileType();
        $coreManaged = (bool) $this->coreProfiles->officialProfileFor($user, $type);

        $validated = match ($type) {
            'mahasiswa' => $request->validate($coreManaged
                ? [
                    'semester' => ['nullable', 'integer', 'min:1', 'max:14'],
                    'class_name' => ['nullable', 'string', 'max:50'],
                ]
                : [
                    'phone' => ['nullable', 'string', 'max:30'],
                    'address' => ['nullable', 'string', 'max:1000'],
                    'gender' => ['nullable', 'string', 'max:30'],
                    'birth_place' => ['nullable', 'string', 'max:255'],
                    'birth_date' => ['nullable', 'date'],
                    'study_program' => ['nullable', 'string', 'max:255'],
                    'semester' => ['nullable', 'integer', 'min:1', 'max:14'],
                    'class_name' => ['nullable', 'string', 'max:50'],
                ]),
            'dosen' => $request->validate($coreManaged
                ? [
                    'expertise' => ['nullable', 'string', 'max:255'],
                ]
                : [
                    'phone' => ['nullable', 'string', 'max:30'],
                    'address' => ['nullable', 'string', 'max:1000'],
                    'study_program' => ['nullable', 'string', 'max:255'],
                    'department' => ['nullable', 'string', 'max:255'],
                    'expertise' => ['nullable', 'string', 'max:255'],
                ]),
            'pembimbing_lapangan' => $request->validate([
                'phone' => ['nullable', 'string', 'max:30'],
                'address' => ['nullable', 'string', 'max:1000'],
                'institution_name' => ['nullable', 'string', 'max:255'],
                'position' => ['nullable', 'string', 'max:255'],
                'place_ids' => ['nullable', 'array'],
                'place_ids.*' => ['integer', 'exists:kp_places,id'],
            ]),
            default => $coreManaged
                ? []
                : $request->validate([
                    'name' => ['required', 'string', 'max:255'],
                    'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id],
                ]),
        };

        if ($type === 'admin') {
            $user->update($validated);
        } else {
            $profile = $user->profileModelForType($type);

            if (! $profile) {
                $profile = match ($type) {
                    'mahasiswa' => $user->student()->create([]),
                    'dosen' => $user->lecturer()->create([]),
                    'pembimbing_lapangan' => $user->fieldSupervisor()->create([]),
                };
            }

            $placeIds = $validated['place_ids'] ?? [];
            unset($validated['place_ids']);

            $profile->update($validated);

            if ($type === 'pembimbing_lapangan') {
                $placeIds = collect($placeIds)
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values()
                    ->all();

                $profile->places()->syncWithPivotValues($placeIds, [
                    'status' => 'aktif',
                    'created_by' => $user->id,
                ]);
            }
        }

        $user->refresh()->load(['roles', 'student', 'lecturer', 'fieldSupervisor']);
        $complete = $coreManaged || $user->isProfileCompleteForType($type);
        $profile = $user->profileModelForType($type);

        if ($profile && $complete && blank($profile->profile_completed_at)) {
            $profile->update(['profile_completed_at' => now()]);
        }

        $user->update(['profile_completed' => $complete]);

        return redirect()->route('profile.show')->with('status', 'Profil berhasil diperbarui.');
    }

    public function syncCore(Request $request, KpCoreBridgeProvisioningService $service): RedirectResponse
    {
        $report = $service->execute($request->user()->email);

        if ($report['blockers'] !== []) {
            return back()->withErrors(['core_sync' => implode(' ', $report['blockers'])]);
        }

        $user = $request->user()->fresh('roles');
        $activeRole = $request->session()->get('active_role');

        if ($activeRole && ! $user->roles->contains('name', $activeRole)) {
            $request->session()->forget('active_role');

            return redirect()->route('role.select')->with('status', 'Profil berhasil disinkronkan dari Core. Pilih ulang role karena akses berubah.');
        }

        return redirect()->route('profile.show')->with('status', 'Profil berhasil disinkronkan dari Core.');
    }

    /**
     * @return array<string, string|null>
     */
    private function operationalAttributes(?Model $profile, string $profileType, bool $coreManaged): array
    {
        if (! $profile) {
            return [];
        }

        if ($coreManaged) {
            return match ($profileType) {
                'mahasiswa' => $this->onlyFilled($profile, [
                    'semester' => 'Semester KP',
                    'class_name' => 'Kelas KP',
                ]),
                'dosen' => $this->onlyFilled($profile, [
                    'expertise' => 'Bidang Keahlian KP',
                ]),
                'pembimbing_lapangan' => $this->onlyFilled($profile, [
                    'institution_name' => 'Institusi Tempat KP',
                    'position' => 'Jabatan',
                    'phone' => 'Telepon Operasional',
                    'address' => 'Alamat Operasional',
                    'partner_places_label' => 'Tempat KP Terkait',
                ]),
                default => [],
            };
        }

        if ($profileType === 'pembimbing_lapangan') {
            return $this->onlyFilled($profile, [
                'institution_name' => 'Institusi Tempat KP',
                'position' => 'Jabatan',
                'phone' => 'Telepon Operasional',
                'address' => 'Alamat Operasional',
                'partner_places_label' => 'Tempat KP Terkait',
            ]);
        }

        return collect($profile->getAttributes())
            ->except(['id', 'user_id', 'created_at', 'updated_at'])
            ->mapWithKeys(fn ($value, string $key): array => [str_replace('_', ' ', strtoupper($key)) => $this->displayValue($value)])
            ->all();
    }

    /**
     * @param  array<string, string>  $fields
     * @return array<string, string|null>
     */
    private function onlyFilled(Model $profile, array $fields): array
    {
        return collect($fields)
            ->mapWithKeys(fn (string $label, string $field): array => [$label => $this->displayValue($profile->{$field} ?? null)])
            ->filter(fn (?string $value): bool => filled($value))
            ->all();
    }

    private function displayValue(mixed $value): ?string
    {
        if (is_array($value)) {
            return collect($value)->filter()->implode(', ');
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return filled($value) ? (string) $value : null;
    }

    public function avatar(Request $request): StreamedResponse
    {
        $user = $request->user();

        abort_if(($user->avatar_disk ?? 'local') === 'remote', 404);
        abort_if(! $user->avatar_path || ! Storage::disk($user->avatar_disk ?? 'local')->exists($user->avatar_path), 404);

        return Storage::disk($user->avatar_disk ?? 'local')->response(
            $user->avatar_path,
            $user->avatar_original_filename,
            [
                'Content-Type' => $user->avatar_mime ?: 'image/jpeg',
                'Cache-Control' => 'private, max-age=300',
            ]
        );
    }

    public function coreAvatar(Request $request): BinaryFileResponse
    {
        $response = $this->coreProfiles->profilePhotoResponseFor($request->user());

        abort_if(! $response, 404);

        return $response;
    }

    public function updateAvatar(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'avatar' => [
                'required',
                File::image()
                    ->types(['jpg', 'jpeg', 'png', 'webp'])
                    ->max(2048),
            ],
        ], [
            'avatar.required' => 'Pilih file foto profil terlebih dahulu.',
            'avatar.image' => 'Foto profil harus berupa gambar JPG, PNG, atau WebP.',
            'avatar.mimes' => 'Foto profil harus berupa JPG, PNG, atau WebP.',
            'avatar.max' => 'Ukuran foto profil maksimal 2MB.',
        ]);

        $user = $request->user();
        $file = $validated['avatar'];
        $disk = 'local';
        $directory = 'avatars/'.$user->id;
        $filename = Str::uuid().'.'.$file->extension();
        $path = $file->storeAs($directory, $filename, $disk);

        $oldDisk = $user->avatar_disk ?: $disk;
        $oldPath = $user->avatar_path;

        $user->update([
            'avatar_path' => $path,
            'avatar_disk' => $disk,
            'avatar_original_filename' => $file->getClientOriginalName(),
            'avatar_mime' => $file->getMimeType(),
            'avatar_size' => $file->getSize(),
        ]);

        if ($oldPath) {
            rescue(fn () => $oldDisk === 'remote' ? null : Storage::disk($oldDisk)->delete($oldPath), report: false);
        }

        return back()->with('status', 'Foto profil berhasil diperbarui.');
    }

    public function deleteAvatar(Request $request): RedirectResponse
    {
        $user = $request->user();
        $oldDisk = $user->avatar_disk ?: 'local';
        $oldPath = $user->avatar_path;

        $user->update([
            'avatar_path' => null,
            'avatar_disk' => 'local',
            'avatar_original_filename' => null,
            'avatar_mime' => null,
            'avatar_size' => null,
        ]);

        if ($oldPath) {
            rescue(fn () => $oldDisk === 'remote' ? null : Storage::disk($oldDisk)->delete($oldPath), report: false);
        }

        return back()->with('status', 'Foto profil berhasil dihapus.');
    }
}
