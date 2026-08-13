@extends('layouts.app')

@section('title', 'Detail User - '.config('app.name'))
@section('page_title', 'Detail User')

@section('content')
@if($errors->any())
    <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
@endif
@if(session('status'))
    <div class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
@endif

<div class="grid gap-5 xl:grid-cols-[0.9fr_1.1fr]">
    <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 class="text-xl font-bold text-slate-950">{{ $user->name }}</h2>
                <p class="text-sm text-slate-500">{{ $user->email }}</p>
            </div>
            <span class="rounded-full {{ $user->status === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }} px-3 py-1 text-xs font-semibold">{{ $user->status === 'active' ? 'Aktif' : 'Nonaktif' }}</span>
        </div>
        <div class="mt-5 flex flex-wrap gap-2">
            @foreach($user->roles as $role)
                <span class="rounded-full bg-teal-50 px-3 py-1 text-xs font-semibold text-teal-700">{{ $role->label }}</span>
            @endforeach
        </div>
        <dl class="mt-6 space-y-3 text-sm">
            <div class="flex justify-between border-t border-slate-100 pt-3"><dt class="text-slate-500">Tipe profil</dt><dd class="font-semibold">{{ str_replace('_', ' ', $user->primaryProfileType()) }}</dd></div>
            <div class="flex justify-between border-t border-slate-100 pt-3"><dt class="text-slate-500">Profil</dt><dd class="font-semibold">{{ $user->profile_completed ? 'Lengkap' : 'Belum lengkap' }}</dd></div>
            <div class="flex justify-between border-t border-slate-100 pt-3"><dt class="text-slate-500">Login terakhir</dt><dd class="font-semibold">{{ $user->last_login_at?->format('d M Y H:i') ?? '-' }}</dd></div>
        </dl>
        <div class="mt-6 flex flex-wrap gap-2">
            <a href="{{ route('admin.users.edit', $user) }}" class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white">Edit</a>
            <form method="POST" action="{{ route('admin.users.sync-core.single', $user) }}" onsubmit="return confirm('Sync data user ini dari Core? Password Core tidak akan disalin.')">
                @csrf
                <button class="rounded-lg border border-cyan-200 px-4 py-2 text-sm font-semibold text-cyan-700">Sinkronkan dari Core</button>
            </form>
            <form method="POST" action="{{ route('admin.users.toggle-status', $user) }}" onsubmit="return confirm('Ubah status akun ini?')">
                @csrf
                <button class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">{{ $user->status === 'active' ? 'Nonaktifkan' : 'Aktifkan' }}</button>
            </form>
            <form method="POST" action="{{ route('admin.users.destroy', $user) }}" onsubmit="return confirm('Hapus user ini?')">
                @csrf
                @method('DELETE')
                <button class="rounded-lg border border-rose-200 px-4 py-2 text-sm font-semibold text-rose-700">Hapus</button>
            </form>
        </div>
    </section>

    <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <h3 class="text-lg font-bold text-slate-950">Detail Profil</h3>
        @php($profile = $user->profileModel())
        @if($profile)
            <div class="mt-5 grid gap-3 md:grid-cols-2">
                @foreach($profile->getAttributes() as $key => $value)
                    @unless(in_array($key, ['id','user_id','created_at','updated_at'], true))
                        <div class="rounded-lg bg-slate-50 px-3 py-2">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ str_replace('_', ' ', $key) }}</p>
                            <p class="mt-1 text-sm font-medium text-slate-800">{{ $value ?: '-' }}</p>
                        </div>
                    @endunless
                @endforeach
            </div>
        @else
            <p class="mt-4 rounded-lg bg-slate-50 px-4 py-3 text-sm text-slate-500">User ini belum memiliki tabel profil khusus.</p>
        @endif
    </section>
</div>
@endsection
