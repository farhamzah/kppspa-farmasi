@extends('layouts.app')
@section('title', 'Publikasi Penempatan PKPA - '.config('app.name'))
@section('page_title', 'Publikasi Penempatan PKPA')

@section('content')
@php
    $publicationStatusLabels = ['publishing' => 'Diproses', 'published' => 'Diterbitkan', 'superseded' => 'Digantikan', 'withdrawn' => 'Ditarik'];
    $changeStatusLabels = ['draft' => 'Draf', 'submitted' => 'Diajukan', 'under_review' => 'Diperiksa', 'approved' => 'Disetujui', 'rejected' => 'Ditolak', 'applied' => 'Diterapkan', 'failed' => 'Gagal'];
    $notificationStatusLabels = ['sent' => 'Terkirim', 'pending' => 'Menunggu', 'failed' => 'Gagal', 'skipped' => 'Dilewati'];
    $currentPublication = $publications->sortByDesc(fn ($publication) => (int) $publication->is_current)->first();
@endphp
<div class="space-y-5">
    @if(session('status'))<div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700">{{ $errors->first() }}</div>@endif

    <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <form method="GET" class="flex flex-col gap-3 md:flex-row md:items-end">
            <div class="flex-1">
                <label class="text-xs font-black uppercase tracking-widest text-slate-500">Program</label>
                <select name="program_id" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Program terbaru</option>
                    @foreach($programs as $item)
                        <option value="{{ $item->id }}" @selected($program?->id === $item->id)>{{ $item->code }} - {{ $item->name }}</option>
                    @endforeach
                </select>
            </div>
            <button class="rounded-xl bg-slate-950 px-4 py-2 text-sm font-black text-white">Tampilkan</button>
        </form>
    </section>

    @if(! $program)
        <section class="rounded-2xl bg-white p-8 text-center text-sm font-semibold text-slate-500 shadow-sm ring-1 ring-slate-200">Belum ada Program PKPA.</section>
    @else
        <section class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_380px]">
            <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <p class="text-xs font-black uppercase tracking-widest text-cyan-700">Pemeriksaan final</p>
                <h2 class="mt-1 text-2xl font-black text-slate-950">{{ $program->code }} - {{ $program->name }}</h2>
                <p class="mt-1 text-sm text-slate-500">Halaman ini menampilkan rancangan current/terbaru untuk pemeriksaan final. Publikasi resmi baru bisa dilakukan setelah seluruh rancangan lulus validasi dan dikunci.</p>
                @if($plan)
                    <div class="mt-5 grid gap-3 md:grid-cols-4">
                        <div class="rounded-xl bg-slate-50 p-4"><p class="text-xs font-black uppercase text-slate-500">Rancangan</p><p class="mt-1 font-black text-slate-950">v{{ $plan->version_number }}</p></div>
                        <div class="rounded-xl bg-slate-50 p-4"><p class="text-xs font-black uppercase text-slate-500">Status</p><p class="mt-1 font-black text-slate-950">{{ $plan->statusLabel() }}</p></div>
                        <div class="rounded-xl bg-slate-50 p-4"><p class="text-xs font-black uppercase text-slate-500">Terisi</p><p class="mt-1 font-black text-slate-950">{{ $review['filled_assignments'] ?? 0 }} / {{ $review['required_assignments'] ?? 0 }}</p></div>
                        <div class="rounded-xl {{ ($review['ready'] ?? false) ? 'bg-emerald-50 text-emerald-800' : 'bg-amber-50 text-amber-800' }} p-4"><p class="text-xs font-black uppercase">Status Pemeriksaan</p><p class="mt-1 font-black">{{ ($review['ready'] ?? false) ? 'Siap Publikasi' : 'Belum Siap' }}</p></div>
                    </div>
                    <div class="mt-5 flex flex-wrap gap-2">
                        <a href="{{ route('management.pkpa-placement-plans.final-review', $plan) }}" class="rounded-xl border border-cyan-200 px-4 py-2 text-sm font-black text-cyan-700">Periksa Ulang</a>
                        @if(auth()->user()->hasRole('koordinator_kp'))
                            <form method="POST" action="{{ route('management.pkpa-placement-plans.publication-lock', $plan) }}">@csrf<button class="rounded-xl border border-amber-200 px-4 py-2 text-sm font-black text-amber-700">Kunci untuk Publikasi</button></form>
                        @endif
                    </div>
                    <div class="mt-5 rounded-2xl border {{ ($review['ready'] ?? false) ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-amber-200 bg-amber-50 text-amber-900' }} px-4 py-4">
                        <p class="text-xs font-black uppercase tracking-widest">{{ ($review['ready'] ?? false) ? 'Status saat ini' : 'Perhatian sebelum publikasi' }}</p>
                        <p class="mt-1 text-sm font-semibold">
                            @if($review['ready'] ?? false)
                                Rancangan ini sudah layak diterbitkan. Lanjutkan ke panel kanan untuk membuat jadwal resmi.
                            @else
                                Rancangan ini sudah punya sebagian assignment valid, tetapi publikasi final tetap menunggu seluruh checklist program lulus. Buka pemeriksaan ulang dan selesaikan butir yang belum lulus lebih dulu.
                            @endif
                        </p>
                    </div>
                @else
                    <div class="mt-5 rounded-xl bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-800">Belum ada rancangan penempatan untuk program ini.</div>
                @endif
            </div>

            <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <h3 class="text-lg font-black text-slate-950">Terbitkan Jadwal</h3>
                @if($plan && auth()->user()->hasRole('koordinator_kp'))
                    <form method="POST" action="{{ route('management.pkpa-placement-plans.publish', $plan) }}" class="mt-4 space-y-3">
                        @csrf
                        <input name="title" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" placeholder="Judul publikasi">
                        <textarea name="note" rows="3" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" placeholder="Catatan publikasi"></textarea>
                        <input name="effective_at" type="datetime-local" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                        <div>
                            <label class="text-xs font-black uppercase tracking-widest text-slate-500">Ketik kode program: {{ $program->code }}</label>
                            <input name="confirmation" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" placeholder="{{ $program->code }}">
                        </div>
                        <button class="w-full rounded-xl bg-emerald-700 px-4 py-3 text-sm font-black text-white">Publikasikan Jadwal Resmi</button>
                    </form>
                @else
                    <p class="mt-3 text-sm text-slate-500">Hanya Koordinator PKPA yang dapat melakukan publikasi final.</p>
                @endif
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-4">
            <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200"><p class="text-xs font-black uppercase tracking-widest text-slate-500">Versi publikasi</p><p class="mt-2 text-3xl font-black text-slate-950">{{ $publications->count() }}</p></div>
            <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200"><p class="text-xs font-black uppercase tracking-widest text-slate-500">Publikasi aktif</p><p class="mt-2 text-3xl font-black text-slate-950">{{ $publications->where('status', 'published')->count() }}</p></div>
            <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200"><p class="text-xs font-black uppercase tracking-widest text-slate-500">Permintaan perubahan</p><p class="mt-2 text-3xl font-black text-slate-950">{{ $changeRequests->count() }}</p></div>
            <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200"><p class="text-xs font-black uppercase tracking-widest text-slate-500">Notifikasi terakhir</p><p class="mt-2 text-3xl font-black text-slate-950">{{ $notifications->count() }}</p></div>
        </section>

        <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <h3 class="text-lg font-black text-slate-950">Riwayat Publikasi</h3>
                <form method="POST" action="{{ route('management.pkpa-notifications.retry') }}">@csrf<button class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-black text-slate-700">Proses Ulang Notifikasi</button></form>
            </div>
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-[900px] w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs font-black uppercase tracking-widest text-slate-500"><tr><th class="px-3 py-3">Kode</th><th>Status</th><th>Penempatan</th><th>Terkini</th><th>Dipublikasikan</th><th>Aksi</th></tr></thead>
                    <tbody>
                        @forelse($publications as $publication)
                            <tr class="border-t border-slate-200">
                                <td class="px-3 py-3 font-black text-slate-950">{{ $publication->code }}</td>
                                <td class="px-3 py-3">{{ $publicationStatusLabels[$publication->status] ?? $publication->status }}</td>
                                <td class="px-3 py-3">{{ $publication->assignments_count }}</td>
                                <td class="px-3 py-3">{{ $publication->is_current ? 'Ya' : 'Tidak' }}</td>
                                <td class="px-3 py-3">{{ optional($publication->published_at)->format('d M Y H:i') ?: '-' }}</td>
                                <td class="px-3 py-3"><a href="{{ route('management.pkpa-publications.show', $publication) }}" class="font-black text-cyan-700">Detail</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-3 py-8 text-center text-sm text-slate-500">Belum ada publikasi.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="grid gap-4 lg:grid-cols-2">
            <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <h3 class="text-lg font-black text-slate-950">Permintaan Perubahan</h3>
                <div class="mt-3 space-y-2">
                    @forelse($changeRequests as $change)
                        <a href="{{ route('management.pkpa-change-requests.show', $change) }}" class="block rounded-xl border border-slate-200 px-4 py-3 text-sm">
                            <span class="font-black text-slate-950">{{ $change->request_number }}</span>
                            <span class="ml-2 text-slate-500">{{ $changeStatusLabels[$change->status] ?? $change->status }}</span>
                            <span class="mt-1 block text-xs text-slate-500">{{ $change->reason ?: 'Tanpa alasan tambahan' }}</span>
                        </a>
                    @empty
                        <p class="text-sm text-slate-500">Belum ada permintaan perubahan.</p>
                    @endforelse
                </div>
            </div>
            <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <h3 class="text-lg font-black text-slate-950">Notifikasi Terakhir</h3>
                <div class="mt-3 space-y-2">
                    @forelse($notifications as $delivery)
                        <div class="rounded-xl border border-slate-200 px-4 py-3 text-sm">
                            <span class="font-black">{{ $delivery->channel === 'mail' ? 'Email' : 'Dalam Aplikasi' }}</span>
                            ke {{ $delivery->recipient_name_snapshot ?: $delivery->recipient_core_user_id }}
                            <span class="text-slate-500">({{ $notificationStatusLabels[$delivery->status] ?? $delivery->status }})</span>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">Belum ada notifikasi.</p>
                    @endforelse
                </div>
            </div>
        </section>
    @endif
</div>
@endsection
