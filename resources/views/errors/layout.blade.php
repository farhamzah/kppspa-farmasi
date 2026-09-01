<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Terjadi Kesalahan' }} - MY PKPA</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
    <main class="flex min-h-screen items-center justify-center px-4 py-10">
        <section class="w-full max-w-xl rounded-2xl border border-slate-200 bg-white p-8 text-center shadow-sm">
            <div class="mx-auto mb-5 flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-50 text-2xl font-bold text-emerald-700">
                {{ $code ?? '!' }}
            </div>
            <p class="text-sm font-semibold uppercase tracking-wide text-emerald-700">MY PKPA</p>
            <h1 class="mt-2 text-2xl font-bold text-slate-950">{{ $title ?? 'Terjadi Kesalahan' }}</h1>
            <p class="mt-3 text-sm leading-6 text-slate-600">{{ $message ?? 'Permintaan belum dapat diproses. Silakan coba kembali.' }}</p>
            <div class="mt-6 flex flex-col justify-center gap-3 sm:flex-row">
                <a href="{{ $primaryUrl ?? route('dashboard') }}" class="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700">
                    {{ $primaryLabel ?? 'Kembali ke Dashboard' }}
                </a>
                <a href="{{ url('/') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                    Ke Beranda
                </a>
            </div>
        </section>
    </main>
</body>
</html>
