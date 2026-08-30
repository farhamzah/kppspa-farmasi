<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $sheet['title'] }} - MY PKPA</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: #fff !important; }
            .paper-shell { padding: 0 !important; }
            .paper-card {
                box-shadow: none !important;
                border: none !important;
                margin: 0 !important;
                border-radius: 0 !important;
            }
        }
    </style>
</head>
<body class="bg-slate-100 text-slate-900">
    <div class="paper-shell mx-auto max-w-6xl px-4 py-6 sm:px-6">
        <div class="no-print mb-5 flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-sm font-bold uppercase tracking-[0.24em] text-cyan-700">MY PKPA</p>
                <h1 class="mt-1 text-2xl font-black text-slate-950">{{ $sheet['title'] }}</h1>
                <p class="mt-1 text-sm text-slate-600">Lembar ini dapat dicetak atau disimpan sebagai PDF dari browser.</p>
            </div>
            <div class="flex flex-wrap gap-3">
                @if($interactive ?? false)
                    <a href="{{ route('student.pkpa-portfolios.show', $portfolio) }}" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold text-slate-700">Kembali ke Portofolio</a>
                @endif
                <button type="button" onclick="window.print()" class="rounded-2xl bg-cyan-700 px-4 py-3 text-sm font-bold text-white">Cetak / Simpan PDF</button>
            </div>
        </div>

        <article class="paper-card mx-auto max-w-[210mm] rounded-[28px] border border-slate-200 bg-white px-7 py-8 shadow-xl shadow-slate-300/30 sm:px-10 sm:py-10">
            <header class="border-b border-slate-200 pb-6">
                <div class="flex flex-col gap-6 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0 flex-1">
                        <h1 class="text-center text-2xl font-black uppercase tracking-wide text-slate-950 sm:text-left">{{ $sheet['title'] }}</h1>
                        <p class="mt-3 text-sm leading-7 text-slate-700">Yang bertanda tangan di bawah ini:</p>
                        <dl class="mt-4 grid gap-x-6 gap-y-2 text-sm leading-7 sm:grid-cols-[180px_1fr]">
                            <dt class="font-bold text-slate-900">Nama</dt><dd>: {{ $sheet['student_name'] }}</dd>
                            <dt class="font-bold text-slate-900">NIM</dt><dd>: {{ $sheet['student_number'] }}</dd>
                            <dt class="font-bold text-slate-900">No. Hp</dt><dd>: {{ $sheet['student_phone'] }}</dd>
                            <dt class="font-bold text-slate-900">Email</dt><dd>: {{ $sheet['student_email'] }}</dd>
                            <dt class="font-bold text-slate-900">Wahana PKPA</dt><dd>: {{ $sheet['practice_domain'] }}</dd>
                            <dt class="font-bold text-slate-900">Tempat PKPA</dt><dd>: {{ $sheet['practice_site'] }}</dd>
                            <dt class="font-bold text-slate-900">Periode PKPA</dt><dd>: {{ $sheet['practice_period'] }}</dd>
                        </dl>
                    </div>

                    <aside class="w-full max-w-[220px] rounded-3xl border border-cyan-100 bg-cyan-50/70 p-4 text-center">
                        <p class="text-xs font-black uppercase tracking-[0.22em] text-cyan-700">QR Validasi</p>
                        <div class="mx-auto mt-3 flex h-40 w-40 items-center justify-center rounded-2xl bg-white p-3 shadow-sm shadow-cyan-900/5">
                            {!! $sheet['qr_markup'] !!}
                        </div>
                        <p class="mt-3 text-xs font-bold text-slate-700">{{ $sheet['validation_code'] }}</p>
                        <p class="mt-1 text-[11px] leading-5 text-slate-500">Scan untuk mengecek keaslian lembar persetujuan ini.</p>
                    </aside>
                </div>
            </header>

            <section class="pt-6">
                <h2 class="text-lg font-black text-slate-950">Pernyataan</h2>
                <p class="mt-2 text-sm leading-7 text-slate-700">Dengan ini saya menyatakan bahwa:</p>
                <ol class="mt-4 space-y-4 pl-6 text-sm leading-7 text-slate-800">
                    @foreach($sheet['statement_items'] as $item)
                        <li class="pl-1">{{ $item }}</li>
                    @endforeach
                </ol>
            </section>

            <section class="mt-8 grid gap-6 lg:grid-cols-[1.15fr_0.85fr]">
                <div class="rounded-3xl border border-slate-200 bg-slate-50 px-5 py-5">
                    <h3 class="text-sm font-black uppercase tracking-[0.2em] text-slate-700">Catatan Validasi</h3>
                    <dl class="mt-4 space-y-3 text-sm text-slate-700">
                        <div class="flex flex-col gap-1 sm:flex-row sm:justify-between sm:gap-4">
                            <dt class="font-bold text-slate-900">Status Persetujuan</dt>
                            <dd>{{ $sheet['status_label'] }}</dd>
                        </div>
                        <div class="flex flex-col gap-1 sm:flex-row sm:justify-between sm:gap-4">
                            <dt class="font-bold text-slate-900">Waktu Persetujuan</dt>
                            <dd>{{ $sheet['signed_at_label'] ?: 'Belum ada persetujuan elektronik' }}</dd>
                        </div>
                        <div class="flex flex-col gap-1 sm:flex-row sm:justify-between sm:gap-4">
                            <dt class="font-bold text-slate-900">Tautan Validasi</dt>
                            <dd class="break-all text-right text-cyan-700">{{ $sheet['verification_url'] }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="rounded-3xl border border-slate-200 bg-white px-5 py-5">
                    <p class="text-right text-sm leading-7 text-slate-700">{{ $sheet['city'] }}, {{ $sheet['signed_date_label'] }}</p>
                    <p class="mt-1 text-right text-sm leading-7 text-slate-700">Yang Membuat Pernyataan</p>
                    <div class="mt-16 border-b border-slate-400"></div>
                    <p class="mt-3 text-right text-sm font-bold text-slate-900">({{ $sheet['student_name'] }})</p>
                    <p class="mt-2 text-right text-xs leading-5 text-slate-500">Ruang ini disiapkan untuk tanda tangan mahasiswa. Jika diminta, dokumen dapat diberi tanda tangan basah setelah dicetak.</p>
                </div>
            </section>

            @if($interactive ?? false)
                <section class="no-print mt-8 rounded-3xl border border-slate-200 bg-slate-50 px-5 py-5">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <h3 class="text-lg font-black text-slate-950">Persetujuan Mahasiswa</h3>
                            <p class="mt-1 text-sm leading-6 text-slate-600">Pilih setuju bila isi pakta ini sudah sesuai. Jika belum, Anda bisa kembali ke portofolio lebih dulu.</p>
                        </div>
                        <div class="flex flex-wrap gap-3">
                            <form method="POST" action="{{ route('student.pkpa-portfolios.integrity.decline', $portfolio) }}">
                                @csrf
                                <button class="rounded-2xl border border-rose-200 bg-white px-5 py-3 text-sm font-bold text-rose-700">Tidak Setuju</button>
                            </form>
                            <form method="POST" action="{{ route('student.pkpa-portfolios.integrity.acknowledge', $portfolio) }}">
                                @csrf
                                <button class="rounded-2xl bg-slate-900 px-5 py-3 text-sm font-bold text-white">Setuju Pakta Integritas</button>
                            </form>
                        </div>
                    </div>
                </section>
            @endif
        </article>
    </div>
</body>
</html>
