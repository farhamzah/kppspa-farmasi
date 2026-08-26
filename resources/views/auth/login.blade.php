@extends('layouts.guest')

@section('content')
@php($heroImage = asset('images/my-pkpa/hero-pkpa-workspace-v2.png'))
<div class="relative flex min-h-screen w-full items-center justify-center overflow-hidden bg-slate-950 px-4 py-5 text-slate-900 sm:px-6 lg:h-screen lg:px-8 lg:py-4">
    <div class="absolute inset-0 bg-[linear-gradient(135deg,rgba(13,148,136,0.42),rgba(15,23,42,0.82)_42%,rgba(20,184,166,0.24))]"></div>
    <div class="absolute inset-x-0 top-0 h-32 bg-linear-to-b from-white/10 to-transparent"></div>
    <div class="absolute bottom-0 left-0 right-0 h-40 bg-linear-to-t from-slate-950 to-transparent"></div>

    <div class="relative grid w-full max-w-6xl overflow-hidden rounded-3xl border border-white/15 bg-white shadow-2xl shadow-black/35 lg:max-h-[calc(100vh-2rem)] lg:grid-cols-[1.04fr_0.96fr]">
        <section class="relative flex min-h-100 flex-col justify-between overflow-hidden bg-slate-900 px-6 py-7 text-white sm:px-8 lg:min-h-0 lg:px-9 lg:py-8 xl:px-10">
            <div class="absolute inset-0 bg-[linear-gradient(145deg,rgba(20,184,166,0.25),rgba(15,23,42,0.16)_40%,rgba(37,99,235,0.18))]"></div>
            <div class="absolute inset-0 bg-[image:linear-gradient(rgba(255,255,255,.65)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,.65)_1px,transparent_1px)] bg-[size:28px_28px] opacity-[0.08]"></div>

            <div class="relative">
                <div class="flex items-center gap-4">
                    <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-white p-2 shadow-xl shadow-teal-950/30">
                        <img src="{{ asset('images/logo-fakultas-farmasi-ubp.png') }}" alt="Logo Fakultas Farmasi UBP" class="h-full w-full object-contain">
                    </div>
                    <div>
                        <p class="text-sm font-bold uppercase text-teal-100">MY PKPA</p>
                        <p class="mt-1 text-sm text-slate-300">Sistem Informasi Praktik Kerja Profesi Apoteker</p>
                    </div>
                </div>

                <div class="mt-8 grid items-start gap-5 xl:mt-10 xl:grid-cols-[minmax(0,1fr)_210px]">
                    <div class="min-w-0">
                    <p class="inline-flex rounded-full border border-teal-300/30 bg-teal-300/10 px-3 py-1 text-xs font-semibold uppercase text-teal-100">Portal PKPA Farmasi</p>
                    <h1 class="mt-4 max-w-lg text-3xl font-black leading-tight text-white sm:text-4xl lg:text-5xl">Masuk ke MY PKPA</h1>
                    <p class="mt-4 max-w-lg text-sm leading-6 text-slate-200">
                        Akses dashboard sesuai peran Anda untuk mengelola peserta, tempat praktik, pembimbing, logbook, laporan, ujian, dan nilai PKPA.
                    </p>
                    </div>

                    <div class="hidden w-full rounded-2xl border border-white/12 bg-white/10 p-4 shadow-2xl shadow-slate-950/25 backdrop-blur sm:max-w-sm lg:block xl:max-w-none">
                        <div class="flex items-center justify-between border-b border-white/10 pb-3">
                            <span class="text-xs font-bold uppercase text-teal-100">Kesiapan PKPA</span>
                            <span class="rounded-full bg-emerald-300 px-2 py-1 text-[11px] font-black text-emerald-950">Aktif</span>
                        </div>
                        <div class="mt-4 space-y-3">
                            <div>
                                <div class="flex justify-between text-xs text-slate-200">
                                    <span>Data peserta</span>
                                    <span>Core</span>
                                </div>
                                <div class="mt-2 h-2 overflow-hidden rounded-full bg-white/15">
                                    <div class="h-full w-3/4 rounded-full bg-teal-300"></div>
                                </div>
                            </div>
                            <div>
                                <div class="flex justify-between text-xs text-slate-200">
                                    <span>Penempatan</span>
                                    <span>Koordinator</span>
                                </div>
                                <div class="mt-2 h-2 overflow-hidden rounded-full bg-white/15">
                                    <div class="h-full w-2/3 rounded-full bg-sky-300"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <figure class="relative mt-6 hidden overflow-hidden rounded-2xl border border-white/12 bg-white/10 shadow-2xl shadow-slate-950/25 lg:block">
                <img src="{{ $heroImage }}" alt="Mahasiswa dan dosen farmasi mengelola kesiapan PKPA" class="h-52 w-full object-cover">
                <figcaption class="absolute inset-x-0 bottom-0 bg-linear-to-t from-slate-950/80 to-transparent px-4 pb-4 pt-10 text-sm font-bold text-white">
                    Ruang kerja PKPA untuk lima wahana utama praktik.
                </figcaption>
            </figure>

            <div class="relative mt-7">
                <div class="grid gap-3 sm:grid-cols-3">
                    @foreach ([
                        ['01', 'Peserta'],
                        ['02', 'Penempatan'],
                        ['03', 'Ujian & Nilai'],
                    ] as [$number, $label])
                        <div class="rounded-xl border border-white/12 bg-white/10 p-3 shadow-lg shadow-slate-950/10 backdrop-blur">
                            <p class="text-xs font-black text-teal-200">{{ $number }}</p>
                            <p class="mt-2 text-sm font-bold text-white">{{ $label }}</p>
                        </div>
                    @endforeach
                </div>

                <div class="mt-3 hidden gap-3 xl:grid xl:grid-cols-2">
                @foreach ([
                    'Mahasiswa, Admin, Koordinator, Pembimbing, Penguji',
                    'Berkas, logbook, laporan, dan nilai tersusun rapi',
                ] as $feature)
                    <div class="flex items-center gap-3 rounded-xl border border-white/12 bg-white/10 px-4 py-3 text-sm font-semibold text-slate-100 shadow-lg shadow-slate-950/10 backdrop-blur">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-teal-400 text-slate-950">
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 0 1 .006 1.414l-7.2 7.27a1 1 0 0 1-1.42 0L4.29 10.14a1 1 0 0 1 1.42-1.41l3.09 3.116 6.49-6.55a1 1 0 0 1 1.414-.006Z" clip-rule="evenodd" />
                            </svg>
                        </span>
                        <span>{{ $feature }}</span>
                    </div>
                @endforeach
                </div>
            </div>
        </section>

        <section class="flex items-center bg-white px-6 py-7 sm:px-10 lg:px-12">
            <div class="w-full">
                <div class="mb-5">
                    <p class="text-sm font-semibold uppercase text-teal-700">Akses MY PKPA</p>
                    <h2 class="mt-2 text-3xl font-black text-slate-950">Masuk ke Portal PKPA</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-500">Gunakan akun Core Farmasi. Dashboard akan mengikuti peran yang diberikan untuk Anda.</p>
                </div>

                @if(session('status'))
                    <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                        <div class="flex gap-3">
                            <svg class="mt-0.5 h-5 w-5 shrink-0" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.707-9.293a1 1 0 0 0-1.414-1.414L9 10.586 7.707 9.293a1 1 0 0 0-1.414 1.414l2 2a1 1 0 0 0 1.414 0l4-4Z" clip-rule="evenodd"/>
                            </svg>
                            <span>{{ session('status') }}</span>
                        </div>
                    </div>
                @endif

                @if($errors->any())
                    <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                        <div class="flex gap-3">
                            <svg class="mt-0.5 h-5 w-5 shrink-0" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16ZM8.707 7.293a1 1 0 0 0-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 1 0 1.414 1.414L10 11.414l1.293 1.293a1 1 0 0 0 1.414-1.414L11.414 10l1.293-1.293a1 1 0 0 0-1.414-1.414L10 8.586 8.707 7.293Z" clip-rule="evenodd"/>
                            </svg>
                            <span>{{ $errors->first() }}</span>
                        </div>
                    </div>
                @endif

                <form method="POST" action="{{ route('login.store') }}" class="space-y-4">
                    @csrf

                    <div>
                        <label for="email" class="mb-2 block text-sm font-bold text-slate-800">Email akun Core Farmasi</label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus placeholder="nama@ubp.ac.id" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-3 text-sm font-medium text-slate-950 outline-none transition placeholder:text-slate-400 hover:border-slate-400 focus:border-teal-600 focus:bg-white focus:ring-4 focus:ring-teal-600/15">
                        @error('email')
                            <p class="mt-2 text-sm font-medium text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="password" class="mb-2 block text-sm font-bold text-slate-800">Password</label>
                        <div class="flex min-h-12 items-center rounded-xl border border-slate-300 bg-slate-50 shadow-sm transition hover:border-slate-400 focus-within:border-teal-600 focus-within:bg-white focus-within:ring-4 focus-within:ring-teal-600/15">
                            <input id="password" name="password" type="password" required placeholder="Masukkan kata sandi" class="min-w-0 flex-1 rounded-xl border-0 bg-transparent px-4 py-3 text-sm font-medium text-slate-950 outline-none placeholder:text-slate-400 focus:ring-0">
                            <button type="button" id="toggle-password" class="mr-2 inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 shadow-sm transition hover:border-teal-200 hover:bg-teal-50 hover:text-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-600/30" aria-label="Tampilkan kata sandi" aria-pressed="false" title="Tampilkan kata sandi">
                                <svg id="password-eye-open" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12 18 18.75 12 18.75 2.25 12 2.25 12Z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 15.25A3.25 3.25 0 1 0 12 8.75a3.25 3.25 0 0 0 0 6.5Z"/>
                                </svg>
                                <svg id="password-eye-closed" class="hidden h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.58 10.58A2 2 0 0 0 12 14a2 2 0 0 0 1.42-.58"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M7.3 7.53C4.18 9.18 2.25 12 2.25 12S6 18.75 12 18.75c1.64 0 3.14-.5 4.43-1.2"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.38 5.58C18.94 6.72 21.75 12 21.75 12s-.78 1.4-2.23 2.86"/>
                                </svg>
                            </button>
                        </div>
                        @error('password')
                            <p class="mt-2 text-sm font-medium text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex items-center justify-between gap-4">
                        <label class="flex cursor-pointer items-center gap-3 text-sm font-medium text-slate-600">
                            <input type="checkbox" name="remember" class="h-4 w-4 rounded border-slate-300 text-teal-600 focus:ring-2 focus:ring-teal-500">
                            <span>Ingat perangkat ini</span>
                        </label>
                    </div>

                    <button type="submit" class="group flex w-full items-center justify-center gap-2 rounded-xl bg-teal-700 px-4 py-3 text-sm font-black text-white shadow-xl shadow-teal-700/25 transition hover:bg-teal-800 focus:outline-none focus:ring-4 focus:ring-teal-600/20 focus:ring-offset-2">
                        <span>Masuk ke Dashboard</span>
                        <svg class="h-4 w-4 transition group-hover:translate-x-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                        </svg>
                    </button>
                </form>

                <div class="mt-5 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                    <p>Belum punya akun, lupa password, atau peran PKPA belum sesuai? Kelola melalui <span class="font-bold text-slate-900">Core Farmasi</span>.</p>
                    @php($accountUrls = config('my_pkpa.account_urls', []))
                    <div class="mt-3 flex flex-wrap gap-2">
                        @if($accountUrls['register'] ?? null)
                            <a href="{{ $accountUrls['register'] }}" class="rounded-lg border border-teal-200 bg-white px-3 py-1.5 text-xs font-bold text-teal-700">Daftar akun</a>
                        @endif
                        @if($accountUrls['forgot_password'] ?? null)
                            <a href="{{ $accountUrls['forgot_password'] }}" class="rounded-lg border border-teal-200 bg-white px-3 py-1.5 text-xs font-bold text-teal-700">Lupa password</a>
                        @endif
                        @if($accountUrls['manage'] ?? null)
                            <a href="{{ $accountUrls['manage'] }}" class="rounded-lg border border-teal-200 bg-white px-3 py-1.5 text-xs font-bold text-teal-700">Kelola akun</a>
                        @endif
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const passwordInput = document.getElementById('password');
        const toggleButton = document.getElementById('toggle-password');
        const eyeOpen = document.getElementById('password-eye-open');
        const eyeClosed = document.getElementById('password-eye-closed');

        toggleButton?.addEventListener('click', () => {
            const shouldShow = passwordInput.type === 'password';
            passwordInput.type = shouldShow ? 'text' : 'password';
            toggleButton.setAttribute('aria-label', shouldShow ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
            toggleButton.setAttribute('aria-pressed', shouldShow ? 'true' : 'false');
            toggleButton.setAttribute('title', shouldShow ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
            eyeOpen.classList.toggle('hidden', shouldShow);
            eyeClosed.classList.toggle('hidden', ! shouldShow);
            passwordInput.focus();
        });
    });
</script>
@endsection
