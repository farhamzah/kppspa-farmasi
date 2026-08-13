@extends('layouts.app')

@section('title', 'Pembuat Portofolio')

@section('content')
<div class="space-y-6">
    <section class="rounded-3xl border border-slate-100 bg-white p-6 shadow-sm">
        <p class="text-sm font-bold uppercase tracking-wide text-cyan-700">Pembuat Portofolio</p>
        <h1 class="mt-2 text-3xl font-black text-slate-950">Pola Dokumen, Pratinjau, Versi, Aktivasi, dan Pemantauan</h1>
        <p class="mt-2 max-w-3xl text-sm text-slate-600">Pola Apotek dan Rumah Sakit aktif sebagai acuan awal. Pembuat portofolio siap ditambah wahana lain tanpa mengubah modul yang sudah ada.</p>
    </section>
    @if(session('status'))<div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-bold text-emerald-800">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm font-bold text-rose-800">{{ $errors->first() }}</div>@endif
    <section class="grid gap-4 lg:grid-cols-2">
        @foreach($templates as $template)
            <article class="rounded-3xl border border-slate-100 bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-3"><div><p class="font-black text-slate-950">{{ $template->name }}</p><p class="text-sm text-slate-500">{{ $template->code }} v{{ $template->version_number }} - {{ $template->practiceDomain?->name }}</p></div><span class="rounded-full bg-cyan-50 px-3 py-1 text-xs font-bold text-cyan-700">{{ $template->status === 'active' ? 'Aktif' : $template->status }}</span></div>
                <p class="mt-4 text-sm text-slate-600">{{ $template->sections->count() }} bagian. Format unduhan: {{ strtoupper(implode(', ', data_get($template->export_configuration, 'formats', []))) }}</p>
            </article>
        @endforeach
    </section>
    <section class="rounded-3xl border border-slate-100 bg-white p-5 shadow-sm">
        <h2 class="text-lg font-black text-slate-950">Pemantauan Portofolio</h2>
        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="text-xs uppercase text-slate-500"><tr><th class="p-3">Mahasiswa</th><th class="p-3">Wahana</th><th class="p-3">Status</th><th class="p-3">Kemajuan</th><th class="p-3">Aksi</th></tr></thead>
                <tbody>
                    @foreach($portfolios as $portfolio)
                        <tr class="border-t border-slate-100">
                            <td class="p-3 font-bold">{{ data_get($portfolio->identity_snapshot, 'student_name') }}</td>
                            <td class="p-3">{{ $portfolio->practiceDomain?->name }}</td>
                            <td class="p-3">{{ $portfolio->statusLabel() }}</td>
                            <td class="p-3">{{ count(data_get($portfolio->progress_snapshot, 'blocking', [])) }} catatan</td>
                            <td class="p-3"><div class="flex flex-wrap gap-2"><form method="POST" action="{{ route('management.pkpa-portfolios.exports.store', [$portfolio, 'docx']) }}">@csrf<button class="rounded-xl bg-slate-900 px-3 py-2 text-xs font-bold text-white">Unduh DOCX</button></form><form method="POST" action="{{ route('management.pkpa-portfolios.exports.store', [$portfolio, 'pdf']) }}">@csrf<button class="rounded-xl bg-slate-900 px-3 py-2 text-xs font-bold text-white">Unduh PDF</button></form><form method="POST" action="{{ route('management.pkpa-portfolios.publish', $portfolio) }}">@csrf<button class="rounded-xl bg-cyan-700 px-3 py-2 text-xs font-bold text-white">Terbitkan</button></form></div></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $portfolios->links() }}</div>
    </section>
</div>
@endsection
