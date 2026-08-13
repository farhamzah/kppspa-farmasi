@extends('layouts.app')

@section('title', 'Manajemen Pengguna - '.config('app.name'))
@section('page_title', 'Manajemen Pengguna')

@section('content')
<div class="space-y-5">
    @if($errors->any())
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif
    @if(session('status'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif

    <div class="flex flex-col gap-3 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 lg:flex-row lg:items-end lg:justify-between">
        <form method="GET" class="grid flex-1 gap-3 md:grid-cols-[1.4fr_1fr_1fr_auto]">
            <div>
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Cari</label>
                <input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Nama atau email" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-500 focus:ring-4 focus:ring-teal-100">
            </div>
            <div>
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Peran</label>
                <select name="role" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-500 focus:ring-4 focus:ring-teal-100">
                    <option value="">Semua peran</option>
                    @foreach($roles as $role)
                        <option value="{{ $role->name }}" @selected(($filters['role'] ?? '') === $role->name)>{{ $role->label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status</label>
                <select name="status" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-500 focus:ring-4 focus:ring-teal-100">
                    <option value="">Semua status</option>
                    <option value="active" @selected(($filters['status'] ?? '') === 'active')>Aktif</option>
                    <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Nonaktif</option>
                </select>
            </div>
            <button class="self-end rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Filter</button>
        </form>
        <div class="flex flex-col gap-2 sm:flex-row lg:flex-col xl:flex-row">
            <form method="POST" action="{{ route('admin.users.sync-core') }}" onsubmit="return confirm('Sinkronkan semua pengguna Core yang punya akses kp-farmasi ke lokal KP? Password Core tidak akan disalin.')">
                @csrf
                <button class="w-full rounded-lg border border-cyan-200 px-4 py-2 text-center text-sm font-semibold text-cyan-700 hover:bg-cyan-50">Sinkronkan Semua Core</button>
            </form>
            <a href="{{ route('admin.users.create') }}" class="rounded-lg bg-teal-600 px-4 py-2 text-center text-sm font-semibold text-white hover:bg-teal-700">Tambah Pengguna</a>
        </div>
    </div>

    <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Pengguna</th>
                        <th class="px-4 py-3">Peran</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Profil</th>
                        <th class="px-4 py-3">Login Terakhir</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($users as $user)
                        <tr class="align-top">
                            <td class="px-4 py-4">
                                <div class="font-semibold text-slate-950">{{ $user->name }}</div>
                                <div class="text-xs text-slate-500">{{ $user->email }}</div>
                            </td>
                            <td class="px-4 py-4">
                                <div class="flex flex-wrap gap-1">
                                    @foreach($user->roles as $role)
                                        <span class="rounded-full bg-teal-50 px-2 py-1 text-xs font-semibold text-teal-700">{{ $role->label }}</span>
                                    @endforeach
                                </div>
                            </td>
                            <td class="px-4 py-4">
                                <span class="rounded-full {{ $user->status === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }} px-2 py-1 text-xs font-semibold">{{ $user->status === 'active' ? 'Aktif' : 'Nonaktif' }}</span>
                            </td>
                            <td class="px-4 py-4">
                                <span class="rounded-full {{ $user->profile_completed ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }} px-2 py-1 text-xs font-semibold">{{ $user->profile_completed ? 'Lengkap' : 'Belum lengkap' }}</span>
                            </td>
                            <td class="px-4 py-4 text-slate-500">{{ $user->last_login_at?->format('d M Y H:i') ?? '-' }}</td>
                            <td class="px-4 py-4">
                                <div class="flex flex-wrap justify-end gap-2">
                                    <a href="{{ route('admin.users.show', $user) }}" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700">Detail</a>
                                    <a href="{{ route('admin.users.edit', $user) }}" class="rounded-lg border border-teal-200 px-3 py-1.5 text-xs font-semibold text-teal-700">Edit</a>
                                    <form method="POST" action="{{ route('admin.users.sync-core.single', $user) }}" onsubmit="return confirm('Sinkronkan data pengguna ini dari Core?')">
                                        @csrf
                                        <button class="rounded-lg border border-cyan-200 px-3 py-1.5 text-xs font-semibold text-cyan-700">Sinkronkan Core</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.users.reset-password', $user) }}" onsubmit="return confirm('Reset password pengguna ini ke password pengembangan?')">
                                        @csrf
                                        <button class="rounded-lg border border-amber-200 px-3 py-1.5 text-xs font-semibold text-amber-700">Reset</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.users.toggle-status', $user) }}" onsubmit="return confirm('Ubah status akun ini?')">
                                        @csrf
                                        <button class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700">{{ $user->status === 'active' ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center text-slate-500">Belum ada pengguna sesuai filter.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-200 px-4 py-3">{{ $users->links() }}</div>
    </div>
</div>
@endsection
