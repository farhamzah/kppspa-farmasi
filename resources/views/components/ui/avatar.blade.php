@props([
    'user',
    'size' => 'md',
])

@php
    $sizes = [
        'sm' => 'h-9 w-9 text-[11px] rounded-xl',
        'md' => 'h-10 w-10 text-xs rounded-2xl',
        'lg' => 'h-16 w-16 text-lg rounded-2xl',
        'xl' => 'h-28 w-28 text-3xl rounded-[1.75rem]',
    ];
    $class = $sizes[$size] ?? $sizes['md'];
    $coreAvatarUrl = $user ? app(\App\Services\CoreProfileReadService::class)->profilePhotoUrlFor($user) : null;
    $avatarUrl = $coreAvatarUrl ?: ($user->hasAvatar() ? $user->avatarUrl() : null);
    $sessionAvatarUrls = [route('profile.avatar.show'), route('profile.core-avatar.show')];

    // Avatar endpoints read the active session, so their URL must not be reused for another user in the same browser.
    if ($avatarUrl && $user && collect($sessionAvatarUrls)->contains(fn ($url) => str_starts_with($avatarUrl, $url))) {
        $separator = str_contains($avatarUrl, '?') ? '&' : '?';
        $avatarUrl .= $separator.'viewer='.$user->getKey();
    }
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex '.$class.' flex-none items-center justify-center overflow-hidden bg-cyan-50 font-black text-cyan-700 ring-1 ring-cyan-100']) }}>
    @if($avatarUrl)
        <img src="{{ $avatarUrl }}" alt="Foto profil {{ $user->name }}" class="h-full w-full object-cover object-[center_10%]">
    @else
        {{ $user->initials() }}
    @endif
</span>
