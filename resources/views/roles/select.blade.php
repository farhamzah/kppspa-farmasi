@extends('layouts.guest')

@section('content')
@php
    $user = auth()->user();
    $userDisplayName = user_display_name($user);
    $roleMeta = [
        'admin' => ['label' => 'Admin', 'icon' => 'AD', 'tone' => 'from-slate-700 to-cyan-800', 'description' => 'Kelola pengguna, data master, pemantauan, rekap, dan ekspor PKPA.'],
        'koordinator_kp' => ['label' => 'Koordinator PKPA', 'icon' => 'KP', 'tone' => 'from-cyan-600 to-teal-600', 'description' => 'Kelola program, wahana, peserta, pembimbing, penempatan, rotasi, dan nilai PKPA.'],
        'mahasiswa' => ['label' => 'Mahasiswa', 'icon' => 'MH', 'tone' => 'from-emerald-500 to-teal-600', 'description' => 'Ikuti pendaftaran PKPA, unggah berkas, lihat penempatan, isi logbook, laporan, portofolio, dan nilai.'],
        'pembimbing_dalam' => ['label' => 'Pembimbing Dalam', 'icon' => 'PD', 'tone' => 'from-sky-600 to-cyan-600', 'description' => 'Pantau mahasiswa bimbingan, logbook, akademik rotasi, laporan, portofolio, dan penilaian PKPA.'],
        'pembimbing_lapangan' => ['label' => 'Preseptor', 'icon' => 'PR', 'tone' => 'from-teal-600 to-emerald-600', 'description' => 'Validasi presensi, logbook, capaian kegiatan, laporan, portofolio, dan penilaian lapangan PKPA.'],
        'penguji' => ['label' => 'Penguji', 'icon' => 'PG', 'tone' => 'from-indigo-600 to-sky-600', 'description' => 'Lihat jadwal ujian dan isi penilaian penguji PKPA.'],
    ];
@endphp

<div class="min-h-screen w-full bg-[radial-gradient(circle_at_top_left,rgba(14,165,233,0.18),transparent_34%),radial-gradient(circle_at_85%_10%,rgba(20,184,166,0.16),transparent_30%),linear-gradient(135deg,#f8fdff,#eef9fb_48%,#f7fbff)] px-4 py-8">
    <div class="mx-auto flex min-h-[calc(100vh-4rem)] w-full max-w-6xl flex-col justify-center">
        <section class="overflow-hidden rounded-[2rem] border border-cyan-100 bg-white/90 shadow-2xl shadow-sky-900/10 backdrop-blur">
            <div class="relative border-b border-cyan-100 p-6 md:p-8">
                <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(14,165,233,0.18),transparent_36%),linear-gradient(135deg,rgba(236,254,255,0.95),rgba(255,255,255,0.78))]"></div>
                <div class="relative flex flex-col gap-5 md:flex-row md:items-center md:justify-between">
                    <div class="flex min-w-0 items-center gap-4">
                        <x-ui.avatar :user="$user" size="lg" class="shadow-lg shadow-cyan-800/10" />
                        <div class="min-w-0">
                            <p class="text-xs font-black uppercase tracking-widest text-cyan-700">Pilih akses untuk melanjutkan</p>
                            <h1 class="mt-1 truncate text-2xl font-black tracking-tight text-slate-950 md:text-3xl">{{ $userDisplayName }}</h1>
                            <p class="mt-1 truncate text-sm font-medium text-slate-500">{{ $user->email }}</p>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('logout') }}" class="flex-none">
                        @csrf
                        <button class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 shadow-sm transition hover:bg-slate-50 hover:text-cyan-800">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                            </svg>
                            Logout
                        </button>
                    </form>
                </div>
            </div>

            <div class="p-6 md:p-8">
                <div class="mb-6 rounded-2xl border border-cyan-200 bg-cyan-50/80 px-5 py-4 text-sm text-cyan-900">
                    <div class="flex items-start gap-3">
                        <span class="mt-0.5 flex h-8 w-8 flex-none items-center justify-center rounded-xl bg-white text-cyan-700 shadow-sm ring-1 ring-cyan-100">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                        </span>
                        <div>
                            <p class="font-black">Akun Anda memiliki lebih dari satu peran.</p>
                            <p class="mt-1 leading-6 text-cyan-800">Pilih peran yang ingin digunakan pada sesi ini. Anda dapat mengganti peran kembali dari bilah atas setelah masuk dashboard.</p>
                        </div>
                    </div>
                </div>

                @if($roles->isEmpty())
                    <div class="rounded-2xl border border-amber-200 bg-amber-50 p-6 text-sm text-amber-900">
                        <p class="font-black">Akun belum memiliki peran aktif.</p>
                        <p class="mt-1">Hubungi Admin untuk mengaktifkan akses sebelum menggunakan sistem.</p>
                    </div>
                @else
                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        @foreach($roles as $role)
                            @php($meta = $roleMeta[$role->name] ?? ['label' => $role->label, 'icon' => strtoupper(substr($role->label, 0, 2)), 'tone' => 'from-cyan-600 to-teal-600', 'description' => $role->description ?: 'Akses aplikasi MY PKPA Farmasi UBP.'])
                            <form method="POST" action="{{ route('role.set', $role) }}" class="group flex min-h-[250px] flex-col rounded-3xl border border-slate-200 bg-white p-5 shadow-sm transition-all hover:-translate-y-1 hover:border-cyan-300 hover:shadow-xl hover:shadow-cyan-900/10">
                                @csrf
                                <div class="flex items-start gap-4">
                                    <div class="flex h-14 w-14 flex-none items-center justify-center rounded-2xl bg-linear-to-br {{ $meta['tone'] }} text-sm font-black text-white shadow-lg shadow-cyan-900/15">
                                        {{ $meta['icon'] }}
                                    </div>
                                    <div class="min-w-0">
                                        <span class="inline-flex rounded-full bg-cyan-50 px-3 py-1 text-[11px] font-black uppercase tracking-widest text-cyan-700">Akses</span>
                                        <h2 class="mt-3 text-lg font-black text-slate-950">{{ $meta['label'] }}</h2>
                                    </div>
                                </div>
                                <p class="mt-4 flex-1 text-sm leading-6 text-slate-600">{{ $meta['description'] }}</p>
                                <button class="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-2xl bg-linear-to-r from-teal-600 to-cyan-700 px-4 py-3 text-sm font-black text-white shadow-lg shadow-cyan-800/18 transition hover:from-teal-700 hover:to-cyan-800 focus:outline-none focus:ring-4 focus:ring-cyan-200">
                                    Masuk
                                    <svg class="h-4 w-4 transition group-hover:translate-x-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                                    </svg>
                                </button>
                            </form>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>
    </div>
</div>
@endsection
