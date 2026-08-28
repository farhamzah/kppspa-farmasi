@extends('layouts.app')
@section('title', 'Verifikasi Pendaftaran PKPA - '.config('app.name'))
@section('page_title', 'Verifikasi Pendaftaran PKPA')
@section('content')
<div class="space-y-5">
    @if(session('status'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif

    <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <form method="GET" class="grid gap-3 md:grid-cols-[1fr_220px_180px_auto]">
            <div>
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Cari Mahasiswa</label>
                <input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Nama, NIM, atau email" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Periode</label>
                <select name="period" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Semua Periode</option>
                    @foreach($periods as $period)
                        <option value="{{ $period->id }}" @selected(($filters['period'] ?? '') == $period->id)>{{ $period->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status</label>
                <select name="status" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Semua Status</option>
                    @foreach(['draft' => 'Draf', 'menunggu_verifikasi' => 'Menunggu Verifikasi', 'revisi' => 'Revisi', 'terverifikasi' => 'Terverifikasi', 'ditolak' => 'Ditolak', 'dibatalkan' => 'Dibatalkan'] as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <button class="self-end rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Filter</button>
        </form>
    </section>

    <section class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Nomor</th>
                        <th class="px-4 py-3">Mahasiswa</th>
                        <th class="px-4 py-3">Periode</th>
                        <th class="px-4 py-3">Kemajuan</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Dikirim</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($registrations as $registration)
                        @php($studentDisplay = app(\App\Services\KpMasterDataReadService::class)->getStudentDisplayData($registration->student))
                        <tr>
                            <td class="px-4 py-4 font-semibold text-slate-900">{{ $registration->registration_number ?: '-' }}</td>
                            <td class="px-4 py-4">
                                <div class="font-semibold text-slate-950">{{ $studentDisplay->name }}</div>
                                <div class="text-xs text-slate-500">{{ $studentDisplay->studentNumber ?: '-' }} - {{ $studentDisplay->email }}</div>
                            </td>
                            <td class="px-4 py-4">{{ $registration->period->name }}</td>
                            <td class="px-4 py-4">
                                <div class="h-2 w-32 overflow-hidden rounded-full bg-slate-100">
                                    <div class="h-full rounded-full bg-teal-600" style="width: {{ $registration->progressPercentage() }}%"></div>
                                </div>
                                <span class="mt-1 block text-xs text-slate-500">{{ $registration->progressPercentage() }}%</span>
                            </td>
                            <td class="px-4 py-4"><span class="rounded-full {{ $registration->statusBadgeClass() }} px-2.5 py-1 text-xs font-semibold">{{ $registration->statusLabel() }}</span></td>
                            <td class="px-4 py-4 text-slate-600">
                                @if($registration->submitted_at)
                                    {{ $registration->submitted_at->format('d M Y H:i') }}
                                @else
                                    <span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700">Belum dikirim</span>
                                @endif
                            </td>
                            <td class="px-4 py-4 text-right">
                                <a href="{{ route('management.pkpa-registrations.show', ['registration' => $registration, 'return_url' => request()->fullUrl()]) }}" class="rounded-lg border border-teal-200 px-3 py-1.5 text-xs font-semibold text-teal-700">Periksa</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center text-slate-500">Belum ada pendaftaran PKPA yang perlu ditampilkan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-200 px-4 py-3">{{ $registrations->links() }}</div>
    </section>
</div>
@endsection
